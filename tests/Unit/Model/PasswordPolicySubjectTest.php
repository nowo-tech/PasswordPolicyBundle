<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Model;

use DateTime;
use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use TypeError;

final class PasswordPolicySubjectTest extends UnitTestCase
{
    public function testHoldsImmutableValues(): void
    {
        $changedAt = new DateTime('2026-01-02 03:04:05');
        $subject   = new PasswordPolicySubject('App\Domain\User', '42', 'hash', $changedAt);
        $changedAt->modify('+1 day');

        self::assertSame('App\Domain\User', $subject->getPasswordPolicySubjectClass());
        self::assertSame('42', $subject->getPasswordPolicySubjectId());
        self::assertSame('hash', $subject->getPasswordHash());
        self::assertInstanceOf(DateTimeImmutable::class, $subject->getPasswordChangedAt());
        self::assertSame('2026-01-02 03:04:05', $subject->getPasswordChangedAt()->format('Y-m-d H:i:s'));
        self::assertNull((new PasswordPolicySubject('C', '1'))->getPasswordChangedAt());
    }

    public function testFromEntity(): void
    {
        $user                    = new KernelUser('current-hash');
        $user->passwordChangedAt = new DateTime('2026-03-04');

        $subject = PasswordPolicySubject::fromEntity($user);

        self::assertSame(KernelUser::class, $subject->getPasswordPolicySubjectClass());
        self::assertSame('7', $subject->getPasswordPolicySubjectId());
        self::assertSame('current-hash', $subject->getPasswordHash());
        self::assertSame('2026-03-04', $subject->getPasswordChangedAt()?->format('Y-m-d'));
    }

    public function testFromEntityWithoutInitialisedPassword(): void
    {
        $entity = Mockery::mock(HasPasswordPolicyInterface::class);
        $entity->shouldReceive('getPassword')->andThrow(new TypeError('not initialised'));
        $entity->shouldReceive('getId')->andReturn(null);
        $entity->shouldReceive('getPasswordChangedAt')->andReturn(null);

        $subject = PasswordPolicySubject::fromEntity($entity);

        self::assertNull($subject->getPasswordHash());
        self::assertSame('', $subject->getPasswordPolicySubjectId());
    }

    public function testHistoryEntryAndReuseMatch(): void
    {
        $entry = new PasswordHistoryEntry('h', new DateTime('2026-05-06'));
        self::assertSame('h', $entry->passwordHash);
        self::assertInstanceOf(DateTimeImmutable::class, $entry->createdAt);
        self::assertNull((new PasswordHistoryEntry('h'))->createdAt);

        $match = new PasswordReuseMatch(PasswordReuseMatch::TYPE_CURRENT);
        self::assertSame('current', $match->type);
        self::assertNull($match->usedAt);
    }
}
