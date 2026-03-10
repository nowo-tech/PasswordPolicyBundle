<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordChangedEventTest extends UnitTestCase
{
    public function testGetUser(): void
    {
        $user      = Mockery::mock(HasPasswordPolicyInterface::class);
        $changedAt = new DateTimeImmutable();
        $event     = new PasswordChangedEvent($user, $changedAt);
        $this->assertSame($user, $event->getUser());
    }

    public function testGetChangedAt(): void
    {
        $user      = Mockery::mock(HasPasswordPolicyInterface::class);
        $changedAt = new DateTimeImmutable('2024-01-15 12:00:00');
        $event     = new PasswordChangedEvent($user, $changedAt);
        $this->assertSame($changedAt, $event->getChangedAt());
    }
}
