<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseDetectedEvent;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use PHPUnit\Framework\TestCase;

final class SubjectScalarAccessorsTest extends TestCase
{
    public function testEntityEventsExposeScalarSubject(): void
    {
        $user    = new KernelUser();
        $history = new PasswordHistoryMock();
        $events  = [
            new PasswordChangedEvent($user, new DateTimeImmutable()),
            new PasswordExpiredEvent($user, 'home'),
            new PasswordHistoryCreatedEvent($user, $history),
            new PasswordReuseAttemptedEvent($user, $history),
        ];

        foreach ($events as $event) {
            self::assertSame(KernelUser::class, $event->getSubjectClass());
            self::assertSame('7', $event->getSubjectId());
        }
    }

    public function testScalarEvents(): void
    {
        $at       = new DateTimeImmutable('2026-01-01');
        $recorded = new PasswordChangeRecordedEvent('App\User', 'u-1', $at, 2);
        self::assertSame('App\User', $recorded->getSubjectClass());
        self::assertSame('u-1', $recorded->getSubjectId());
        self::assertSame($at, $recorded->getChangedAt());
        self::assertSame(2, $recorded->getRemovedEntriesCount());

        $reuse = new PasswordReuseDetectedEvent('App\User', 'u-1', PasswordReuseMatch::TYPE_EXTENSION, $at);
        self::assertSame('App\User', $reuse->getSubjectClass());
        self::assertSame('u-1', $reuse->getSubjectId());
        self::assertSame('extension', $reuse->getMatchType());
        self::assertSame($at, $reuse->getUsedAt());
    }
}
