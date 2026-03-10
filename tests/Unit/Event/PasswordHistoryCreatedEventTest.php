<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Event;

use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordHistoryCreatedEventTest extends UnitTestCase
{
    public function testGetters(): void
    {
        $user    = Mockery::mock(HasPasswordPolicyInterface::class);
        $history = Mockery::mock(PasswordHistoryInterface::class);
        $event   = new PasswordHistoryCreatedEvent($user, $history, 2);

        $this->assertSame($user, $event->getUser());
        $this->assertSame($history, $event->getPasswordHistory());
        $this->assertSame(2, $event->getRemovedEntriesCount());
    }

    public function testDefaultRemovedEntriesCount(): void
    {
        $user    = Mockery::mock(HasPasswordPolicyInterface::class);
        $history = Mockery::mock(PasswordHistoryInterface::class);
        $event   = new PasswordHistoryCreatedEvent($user, $history);
        $this->assertSame(0, $event->getRemovedEntriesCount());
    }
}
