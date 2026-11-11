<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\EventListener\PasswordPolicyWorkerBoundaryListener;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\PasswordPolicyTestKernel;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerStoppedEvent;
use Symfony\Component\Messenger\Worker;

use function gc_collect_cycles;
use function memory_get_usage;
use function str_replace;
use function strtolower;
use function sys_get_temp_dir;

/**
 * REQ-RUNTIME-002 worker test: one kernel kept alive, the services resetter never called. A flush that fails leaves its
 * users (with their password hashes) in the shared Doctrine password listener, because `postFlush` never runs. The
 * bundle's boundary listener drops them at the next main request, at the end of every Messenger message and when the
 * outermost console command ends, but not when a nested in-process command ends.
 */
final class PasswordPolicyKernelWorkerBoundaryTest extends TestCase
{
    /** Requests per user (2 × 500 = 1,000 requests in one kernel). */
    private const ROUNDS = 500;

    private PasswordPolicyTestKernel $kernel;

    private ContainerInterface $container;

    private PasswordEntityListener $entityListener;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-password-policy-test-kernel');

        $this->kernel = new PasswordPolicyTestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $this->container = $container;

        // DoctrineBundle builds the listener on the first flush; it is the instance the boundaries must clear.
        $listener = $this->container->get('nowo_password_policy.entity_listener.' . strtolower(str_replace('\\', '_', KernelUser::class)));
        self::assertInstanceOf(PasswordEntityListener::class, $listener);
        $this->entityListener = $listener;
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testFailedFlushStateNeverReachesTheNextRequest(): void
    {
        $httpKernel = $this->container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
        $logger = $this->container->get('logger');
        self::assertInstanceOf(BufferingLogger::class, $logger);
        $alice    = new KernelUser('REDACTED-TEST-alice');
        $bob      = new KernelUser('REDACTED-TEST-bob');
        $baseline = 0;

        for ($i = 0; $i < 2 * self::ROUNDS; ++$i) {
            $this->failedFlushOf($i % 2 === 0 ? $alice : $bob);

            $request  = Request::create('/missing-' . $i % 2);
            $response = $httpKernel->handle($request);
            if ($httpKernel instanceof TerminableInterface) {
                $httpKernel->terminate($request, $response);
            }

            // The test kernel's logger buffers every log line (the 404 of each request): drop them, not the bundle's state.
            $logger->cleanLogs();

            self::assertSame([], $this->pendingChanges(), "Request {$i}: no user of a previous failed flush is kept.");
            self::assertSame([], $this->processedPasswords(), "Request {$i}: no old hash of a previous failed flush is kept.");

            if ($i === 20) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan($baseline + 2 * 1024 * 1024, memory_get_usage(), 'Memory stays flat over 1,000 requests.');
    }

    public function testSubRequestKeepsTheMainRequestUnit(): void
    {
        $boundary = $this->boundary();
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-alice'));

        $boundary->onKernelRequest(new RequestEvent($this->kernel, Request::create('/fragment'), HttpKernelInterface::SUB_REQUEST));
        self::assertCount(1, $this->pendingChanges());

        $boundary->onKernelRequest(new RequestEvent($this->kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        self::assertSame([], $this->pendingChanges());
    }

    public function testMessengerBoundariesClearAndNestedCommandsDoNot(): void
    {
        $dispatcher = $this->dispatcher();
        $envelope   = new Envelope(new stdClass());

        foreach ([true, false] as $handled) {
            $this->failedFlushOf(new KernelUser('REDACTED-TEST-before'));
            $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
            self::assertSame([], $this->pendingChanges(), 'A message starts clean.');

            // A command run in-process from the handler must not clear the message's unit when it ends.
            $this->failedFlushOf(new KernelUser('REDACTED-TEST-in-message'));
            $this->runNestedCommand($dispatcher);
            self::assertCount(1, $this->pendingChanges(), 'A nested command inside a message keeps the message unit.');

            $dispatcher->dispatch($handled
                ? new WorkerMessageHandledEvent($envelope, 'async')
                : new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('failed')));
            self::assertSame([], $this->pendingChanges(), 'The message unit ends clean.');
        }

        // A skipped message (no handled / failed event) ends with the worker: the console boundary works again.
        $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
        $dispatcher->dispatch(new WorkerStoppedEvent($this->createStub(Worker::class)));
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-after'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges());
    }

    public function testOnlyTheOutermostCommandClears(): void
    {
        $dispatcher                 = $this->dispatcher();
        [$command, $input, $output] = $this->command();

        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-outer'));
        $this->runNestedCommand($dispatcher);
        self::assertCount(1, $this->pendingChanges(), 'The nested command keeps the outer unit.');

        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, Command::SUCCESS), ConsoleEvents::TERMINATE);
        self::assertSame([], $this->pendingChanges(), 'The outermost command ends clean.');

        // An unbalanced terminate never drives the depth below zero.
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, Command::SUCCESS), ConsoleEvents::TERMINATE);
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges());
    }

    public function testCommandRunInsideARequestKeepsTheRequestUnit(): void
    {
        $dispatcher = $this->dispatcher();
        $request    = Request::create('/reset-password');

        $dispatcher->dispatch(new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
        // A controller runs a command in-process while a flush of this request is pending.
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-in-request'));
        $this->runNestedCommand($dispatcher);
        self::assertCount(1, $this->pendingChanges(), 'A command inside a request keeps the request unit.');

        $dispatcher->dispatch(new TerminateEvent($this->kernel, $request, new Response()), KernelEvents::TERMINATE);
        self::assertSame([], $this->pendingChanges(), 'The request unit ends clean.');

        // Outside any request, the outermost command clears again.
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-after-request'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges());
    }

    public function testBoundaryNeverBuildsAListener(): void
    {
        $kernel = new PasswordPolicyTestKernel();
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $boundary = $container->get(PasswordPolicyWorkerBoundaryListener::class);
            self::assertInstanceOf(PasswordPolicyWorkerBoundaryListener::class, $boundary);
            $id = 'nowo_password_policy.entity_listener.' . strtolower(str_replace('\\', '_', KernelUser::class));

            $boundary->clear();
            self::assertFalse($container->initialized($id));
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * What a flush leaves in the listener when it fails after `onFlush`: `postFlush` never runs.
     */
    private function failedFlushOf(KernelUser $user): void
    {
        (new ReflectionProperty(PasswordEntityListener::class, 'pendingChanges'))->setValue($this->entityListener, [[$user, new DateTimeImmutable(), 0]]);
        (new ReflectionProperty(PasswordEntityListener::class, 'processedPasswords'))->setValue($this->entityListener, [$user->getPassword() => true]);
    }

    /**
     * @return array<mixed>
     */
    private function pendingChanges(): array
    {
        /* @var array<mixed> */
        return (new ReflectionProperty(PasswordEntityListener::class, 'pendingChanges'))->getValue($this->entityListener);
    }

    /**
     * @return array<mixed>
     */
    private function processedPasswords(): array
    {
        /* @var array<mixed> */
        return (new ReflectionProperty(PasswordEntityListener::class, 'processedPasswords'))->getValue($this->entityListener);
    }

    private function runNestedCommand(EventDispatcherInterface $dispatcher): void
    {
        [$command, $input, $output] = $this->command();
        $dispatcher->dispatch(new ConsoleCommandEvent($command, $input, $output), ConsoleEvents::COMMAND);
        $dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, Command::SUCCESS), ConsoleEvents::TERMINATE);
    }

    /**
     * @return array{Command, ArrayInput, NullOutput}
     */
    private function command(): array
    {
        return [new Command('app:test'), new ArrayInput([]), new NullOutput()];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        return $dispatcher;
    }

    private function boundary(): PasswordPolicyWorkerBoundaryListener
    {
        $boundary = $this->container->get(PasswordPolicyWorkerBoundaryListener::class);
        self::assertInstanceOf(PasswordPolicyWorkerBoundaryListener::class, $boundary);

        return $boundary;
    }
}
