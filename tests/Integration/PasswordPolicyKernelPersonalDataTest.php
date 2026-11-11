<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Event\PasswordPolicyPersonalDataErasedEvent;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRecorderInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporterInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\PasswordPolicyTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * DDD host (history adapter without Doctrine): the public GDPR ports resolve from the container and work on the
 * host's adapter.
 */
final class PasswordPolicyKernelPersonalDataTest extends TestCase
{
    private PasswordPolicyTestKernel $kernel;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-password-policy-test-kernel');
        $this->kernel = new PasswordPolicyTestKernel();
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
    }

    protected function tearDown(): void
    {
        $logger = $this->container->get('logger');
        if ($logger instanceof BufferingLogger) {
            $logger->cleanLogs();
        }
        $this->kernel->shutdown();
    }

    public function testExportThenEraseThroughThePublicPorts(): void
    {
        $recorder = $this->container->get(PasswordHistoryRecorderInterface::class);
        self::assertInstanceOf(PasswordHistoryRecorderInterface::class, $recorder);
        $recorder->record(KernelUser::class, '7', 'hash-REDACTED-TEST', new DateTimeImmutable('2026-01-02T03:04:05+00:00'));
        $recorder->record(KernelUser::class, '8', 'hash-REDACTED-TEST', new DateTimeImmutable('2026-01-03T03:04:05+00:00'));

        $exporter = $this->container->get(PasswordPolicyPersonalDataExporterInterface::class);
        $eraser   = $this->container->get(PasswordPolicyPersonalDataEraserInterface::class);
        self::assertInstanceOf(PasswordPolicyPersonalDataExporterInterface::class, $exporter);
        self::assertInstanceOf(PasswordPolicyPersonalDataEraserInterface::class, $eraser);
        $dispatcher = $this->container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $events = 0;
        $dispatcher->addListener(PasswordPolicyPersonalDataErasedEvent::class, static function () use (&$events): void {
            ++$events;
        });
        $subject = PasswordPolicyPersonalDataSubject::fromSubjectId(KernelUser::class, '7');

        self::assertSame(['count' => 1, 'entries' => [['changed_at' => '2026-01-02T03:04:05+00:00']]], $exporter->export($subject));
        self::assertSame(1, $eraser->erase($subject)->passwordHistory);
        self::assertSame(['count' => 0, 'entries' => []], $exporter->export($subject));
        self::assertSame(1, $exporter->export(PasswordPolicyPersonalDataSubject::fromSubjectId(KernelUser::class, '8'))['count']);
        // No Doctrine transaction to commit: the event is not dispatched (never before a commit).
        self::assertSame(0, $events);
    }
}
