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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
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
use Throwable;

use function array_map;
use function gc_collect_cycles;
use function memory_get_usage;
use function str_replace;
use function strtolower;
use function sys_get_temp_dir;

use const PHP_INT_MIN;

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

        $dispatcher->dispatch(new FinishRequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST), KernelEvents::FINISH_REQUEST);
        $dispatcher->dispatch(new TerminateEvent($this->kernel, $request, new Response()), KernelEvents::TERMINATE);
        self::assertSame([], $this->pendingChanges(), 'The request unit ends clean.');

        // Outside any request, the outermost command clears again.
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-after-request'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges());
    }

    /**
     * Audit NM (a): a `console.terminate` listener that throws in a nested command no longer leaves the console
     * bookkeeping unbalanced: the outer command still ends clean, and so does the next one.
     */
    public function testThrowingTerminateListenerOfANestedCommandKeepsTheOuterBoundary(): void
    {
        $dispatcher = $this->dispatcher();
        $throwing   = static function (ConsoleTerminateEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:nested') {
                throw new RuntimeException('terminate listener failed');
            }
        };
        $dispatcher->addListener(ConsoleEvents::TERMINATE, $throwing, 0);

        try {
            $pendingAfterNested = null;
            $this->runApplication(function (Application $application, OutputInterface $output) use (&$pendingAfterNested): void {
                $this->failedFlushOf(new KernelUser('REDACTED-TEST-outer'));
                try {
                    $application->doRun(new ArrayInput(['command' => 'app:nested']), $output);
                } catch (RuntimeException) {
                    // The nested command's terminate listener failed; the outer command goes on.
                }
                $pendingAfterNested = $this->pendingChanges();
            });

            self::assertCount(1, (array) $pendingAfterNested, 'The nested command does not clear the outer unit.');
            self::assertSame([], $this->pendingChanges(), 'The outer command still ends clean.');

            $this->failedFlushOf(new KernelUser('REDACTED-TEST-next'));
            $this->runApplication(static function (): void {
            });
            self::assertSame([], $this->pendingChanges(), 'The next outermost command ends clean too.');
        } finally {
            $dispatcher->removeListener(ConsoleEvents::TERMINATE, $throwing);
        }
    }

    /**
     * Audit NM (c): an unknown nested command (only `console.error`, no command / terminate) never ends the outer unit.
     */
    public function testUnknownNestedCommandKeepsTheOuterUnit(): void
    {
        $pendingAfterNested = null;
        $this->runApplication(function (Application $application, OutputInterface $output) use (&$pendingAfterNested): void {
            $this->failedFlushOf(new KernelUser('REDACTED-TEST-outer'));
            try {
                $application->doRun(new ArrayInput(['command' => 'app:does-not-exist']), $output);
            } catch (Throwable) {
                // Command not found.
            }
            $pendingAfterNested = $this->pendingChanges();
        });

        // The console error listener logged the unknown command: drop the buffered line, not the bundle's state.
        $logger = $this->container->get('logger');
        self::assertInstanceOf(BufferingLogger::class, $logger);
        $logger->cleanLogs();

        self::assertCount(1, (array) $pendingAfterNested, 'An unknown nested command keeps the outer unit.');
        self::assertSame([], $this->pendingChanges(), 'The outer command ends clean.');
    }

    /**
     * Audit NM (d): a `console.command` listener above 4096 that stops propagation in a nested command no longer makes
     * that command's terminate end the outer unit mid-run.
     */
    public function testStoppedNestedCommandEventKeepsTheOuterUnit(): void
    {
        $dispatcher = $this->dispatcher();
        $stopping   = static function (ConsoleCommandEvent $event): void {
            if ($event->getCommand()?->getName() === 'app:nested') {
                $event->stopPropagation();
            }
        };
        $dispatcher->addListener(ConsoleEvents::COMMAND, $stopping, 10000);

        try {
            $pendingAfterNested = null;
            $this->runApplication(function (Application $application, OutputInterface $output) use (&$pendingAfterNested): void {
                $this->failedFlushOf(new KernelUser('REDACTED-TEST-outer'));
                $application->doRun(new ArrayInput(['command' => 'app:nested']), $output);
                $pendingAfterNested = $this->pendingChanges();
            });

            self::assertCount(1, (array) $pendingAfterNested, 'The nested command does not clear the outer unit mid-run.');
            self::assertSame([], $this->pendingChanges(), 'The outer command ends clean.');
        } finally {
            $dispatcher->removeListener(ConsoleEvents::COMMAND, $stopping);
        }
    }

    public function testResetForgetsTheConsoleBookkeeping(): void
    {
        $boundary                   = $this->boundary();
        [$command, $input, $output] = $this->command();

        $boundary->onConsoleCommand(new ConsoleCommandEvent($command, $input, $output));
        $boundary->onKernelRequestStarted(new RequestEvent($this->kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        $boundary->onMessageReceivedStarted();
        $boundary->reset();

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-after-reset'));
        $this->runNestedCommand($this->dispatcher());
        self::assertSame([], $this->pendingChanges(), 'After reset() the next command is the outermost unit again.');
    }

    /**
     * Audit NL2-v: a command that runs itself in-process (same Command object, new input) whose inner run fails and
     * whose error listener throws: the inner terminate never comes; the outer run still ends the unit and the next
     * outermost command clears too.
     */
    public function testSelfRecursiveCommandWithAThrowingErrorListener(): void
    {
        $dispatcher = $this->dispatcher();
        $throwing   = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $depth       = 0;
        $pending     = null;
        $application = $this->application([]);
        $recursive   = (new Command('app:rec'))->setCode(function () use ($application, &$depth, &$pending, $dispatcher, $throwing): int {
            if (++$depth > 1) {
                throw new RuntimeException('inner run failed');
            }
            $this->failedFlushOf(new KernelUser('REDACTED-TEST-outer'));
            $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
            try {
                $application->run(new ArrayInput(['command' => 'app:rec']), new NullOutput());
            } catch (Throwable) {
                // The inner run failed and its error listener threw.
            } finally {
                $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
            }
            $pending = $this->pendingChanges();

            return Command::SUCCESS;
        });
        $application->setCommandLoader(new FactoryCommandLoader(['app:rec' => static fn (): Command => $recursive]));

        self::assertSame(Command::SUCCESS, $application->run(new ArrayInput(['command' => 'app:rec']), new NullOutput()));
        $this->cleanLogs();
        self::assertCount(1, (array) $pending, 'The failed inner run does not clear the outer unit.');
        self::assertSame([], $this->pendingChanges(), 'The outer run ends clean.');

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges(), 'The next outermost command clears.');
    }

    /**
     * Audit NL2-v: an error listener at PHP_INT_MIN throws, so the outermost failing command never terminates; the
     * still-failing frame is dropped at the next command, which is the outermost unit again and clears at its end.
     */
    public function testErrorListenerAtIntMinThrowingNeverKeepsTheProcessInsideACommand(): void
    {
        $dispatcher = $this->dispatcher();
        $throwing   = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $failing = (new Command('app:fail'))->setCode(static function (): never {
            throw new RuntimeException('command failed');
        });
        $dispatcher->addListener(ConsoleEvents::ERROR, $throwing, PHP_INT_MIN);

        try {
            $this->application(['app:fail' => $failing])->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
            self::fail('The error listener throws.');
        } catch (RuntimeException) {
        } finally {
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
        }
        $this->cleanLogs();

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges(), 'The next command is the outermost unit and clears.');
    }

    /**
     * Audit NL2-v: a WorkerMessageReceivedEvent listener throws inside an in-process consume command, so no handled /
     * failed / stopped event follows; the command's frame restores the outer "no message" state when it ends.
     */
    public function testThrowingMessageReceivedListenerInsideACommandDoesNotLeakTheMessageFlag(): void
    {
        $dispatcher = $this->dispatcher();
        $throwing   = static function (): never {
            throw new RuntimeException('received listener failed');
        };
        $consume = (new Command('app:consume'))->setCode(static function () use ($dispatcher, $throwing): int {
            $dispatcher->addListener(WorkerMessageReceivedEvent::class, $throwing);
            try {
                $dispatcher->dispatch(new WorkerMessageReceivedEvent(new Envelope(new stdClass()), 'async'));
            } catch (RuntimeException) {
            } finally {
                $dispatcher->removeListener(WorkerMessageReceivedEvent::class, $throwing);
            }

            return Command::SUCCESS;
        });

        self::assertSame(Command::SUCCESS, $this->application(['app:consume' => $consume])->run(new ArrayInput(['command' => 'app:consume']), new NullOutput()));

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges(), 'No message is in flight after the command: the next command clears.');
    }

    /**
     * Audit NL2-v: a main request handled in-process inside another main request does not end the outer one early:
     * a command run afterwards in the outer request still keeps the request unit.
     */
    public function testNestedMainRequestKeepsTheOuterRequestInFlight(): void
    {
        $dispatcher = $this->dispatcher();
        $boundary   = $this->boundary();
        $outer      = Request::create('/outer');
        $inner      = Request::create('/inner');

        $boundary->onKernelRequestStarted(new RequestEvent($this->kernel, $outer, HttpKernelInterface::MAIN_REQUEST));
        $boundary->onKernelRequestStarted(new RequestEvent($this->kernel, $inner, HttpKernelInterface::MAIN_REQUEST));
        $boundary->onKernelFinishRequest(new FinishRequestEvent($this->kernel, $inner, HttpKernelInterface::MAIN_REQUEST));

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-in-outer-request'));
        $this->runNestedCommand($dispatcher);
        self::assertCount(1, $this->pendingChanges(), 'A command inside the outer request keeps the request unit.');

        $boundary->onKernelFinishRequest(new FinishRequestEvent($this->kernel, $outer, HttpKernelInterface::MAIN_REQUEST));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges(), 'Outside any request the outermost command clears.');
    }

    /**
     * Audit NL2-v2: a message handler runs a command that fails and whose console.error listener throws (no
     * terminate); the message fails and the worker stops: the next main request and the next command still clear.
     */
    public function testFailedCommandInsideAMessageNeverLeavesTheMessageInFlight(): void
    {
        $dispatcher = $this->dispatcher();
        $envelope   = new Envelope(new stdClass());
        $throwing   = static function (): never {
            throw new RuntimeException('error listener failed');
        };
        $failing = (new Command('app:fail'))->setCode(static function (): never {
            throw new RuntimeException('command failed');
        });

        $dispatcher->dispatch(new WorkerMessageReceivedEvent($envelope, 'async'));
        $dispatcher->addListener(ConsoleEvents::ERROR, $throwing);
        try {
            $this->application(['app:fail' => $failing])->run(new ArrayInput(['command' => 'app:fail']), new NullOutput());
            self::fail('The error listener throws.');
        } catch (RuntimeException) {
        } finally {
            $dispatcher->removeListener(ConsoleEvents::ERROR, $throwing);
        }
        $dispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'async', new RuntimeException('handler failed')));
        $dispatcher->dispatch(new WorkerStoppedEvent($this->createStub(Worker::class)));
        $this->cleanLogs();

        $httpKernel = $this->container->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next-request'));
        $request  = Request::create('/missing-after-worker');
        $response = $httpKernel->handle($request);
        if ($httpKernel instanceof TerminableInterface) {
            $httpKernel->terminate($request, $response);
        }
        $this->cleanLogs();
        self::assertSame([], $this->pendingChanges(), 'The next main request clears.');

        $this->failedFlushOf(new KernelUser('REDACTED-TEST-next-command'));
        $this->runNestedCommand($dispatcher);
        self::assertSame([], $this->pendingChanges(), 'No message is stuck in flight: the next command clears.');
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
     * A console Application wired to the kernel's dispatcher with the given commands.
     *
     * @param array<string, Command> $commands
     */
    private function application(array $commands): Application
    {
        $application = new Application('test');
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->setDispatcher($this->dispatcher());
        $application->setCommandLoader(new FactoryCommandLoader(array_map(static fn (Command $command): callable => static fn (): Command => $command, $commands)));

        return $application;
    }

    /**
     * Drops the console error lines the test kernel's buffering logger collected.
     */
    private function cleanLogs(): void
    {
        $logger = $this->container->get('logger');
        self::assertInstanceOf(BufferingLogger::class, $logger);
        $logger->cleanLogs();
    }

    /**
     * Runs `app:outer` (its body is $body) through a real console Application wired to the kernel's dispatcher;
     * `app:nested` does nothing.
     *
     * @param callable(Application, OutputInterface): void $body
     */
    private function runApplication(callable $body): void
    {
        $application = $this->application([]);
        $outer       = (new Command('app:outer'))->setCode(static function (InputInterface $input, OutputInterface $output) use ($application, $body): int {
            $body($application, $output);

            return Command::SUCCESS;
        });
        $inner = (new Command('app:nested'))->setCode(static fn (): int => Command::SUCCESS);
        $application->setCommandLoader(new FactoryCommandLoader([
            'app:outer'  => static fn (): Command => $outer,
            'app:nested' => static fn (): Command => $inner,
        ]));

        $application->run(new ArrayInput(['command' => 'app:outer']), new NullOutput());
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
