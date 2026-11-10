<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration;

use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseDetectedEvent;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRecorderInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\ChangePasswordCommand;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\PasswordPolicyTestKernel;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use const PASSWORD_BCRYPT;

/**
 * A host without a Doctrine user: the use case records the history through the recorder and validates a command.
 */
final class PasswordPolicyKernelDddPortsTest extends TestCase
{
    private PasswordPolicyTestKernel $kernel;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-password-policy-test-kernel');

        $this->kernel = new PasswordPolicyTestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $this->container = $container;
    }

    protected function tearDown(): void
    {
        $logger = $this->container->get('logger');
        if ($logger instanceof BufferingLogger) {
            $logger->cleanLogs();
        }
        $this->kernel->shutdown();
    }

    public function testRecordedPasswordIsRejectedOnACommand(): void
    {
        $dispatcher = $this->container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $events  = [];
        $collect = static function (object $event) use (&$events): void {
            $events[] = $event;
        };
        $dispatcher->addListener(PasswordChangeRecordedEvent::class, $collect);
        $dispatcher->addListener(PasswordReuseDetectedEvent::class, $collect);

        $recorder = $this->container->get(PasswordHistoryRecorderInterface::class);
        self::assertInstanceOf(PasswordHistoryRecorderInterface::class, $recorder);
        $recorder->record(KernelUser::class, 'u-9', password_hash('Old-password1', PASSWORD_BCRYPT, ['cost' => 4]));

        $validator = $this->container->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $subject = new PasswordPolicySubject(KernelUser::class, 'u-9', password_hash('Current-1', PASSWORD_BCRYPT, ['cost' => 4]));

        $violations = $validator->validate(new ChangePasswordCommand('Old-password1', $subject));
        self::assertCount(1, $violations);
        self::assertSame(PasswordPolicy::PASSWORD_IN_HISTORY, $violations[0]?->getCode());

        self::assertCount(1, $validator->validate(new ChangePasswordCommand('Current-1', $subject)));
        self::assertCount(0, $validator->validate(new ChangePasswordCommand('Brand-new-1', $subject)));

        self::assertCount(3, $events);
        self::assertInstanceOf(PasswordChangeRecordedEvent::class, $events[0]);
        self::assertSame('u-9', $events[0]->getSubjectId());
        self::assertInstanceOf(PasswordReuseDetectedEvent::class, $events[1]);
        self::assertSame('exact', $events[1]->getMatchType());
    }
}
