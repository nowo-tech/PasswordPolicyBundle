<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Event;

use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordReuseAttemptedEventTest extends UnitTestCase
{
    public function testGetUser(): void
    {
        $user    = Mockery::mock(HasPasswordPolicyInterface::class);
        $history = Mockery::mock(PasswordHistoryInterface::class);
        $event   = new PasswordReuseAttemptedEvent($user, $history);
        $this->assertSame($user, $event->getUser());
    }

    public function testGetPasswordHistory(): void
    {
        $user    = Mockery::mock(HasPasswordPolicyInterface::class);
        $history = Mockery::mock(PasswordHistoryInterface::class);
        $event   = new PasswordReuseAttemptedEvent($user, $history);
        $this->assertSame($history, $event->getPasswordHistory());
    }
}
