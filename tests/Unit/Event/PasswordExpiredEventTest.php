<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Event;

use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordExpiredEventTest extends UnitTestCase
{
    public function testGetUser(): void
    {
        $user  = Mockery::mock(HasPasswordPolicyInterface::class);
        $event = new PasswordExpiredEvent($user, 'dashboard', false);
        $this->assertSame($user, $event->getUser());
    }

    public function testGetRoute(): void
    {
        $user  = Mockery::mock(HasPasswordPolicyInterface::class);
        $event = new PasswordExpiredEvent($user, 'profile_edit', true);
        $this->assertSame('profile_edit', $event->getRoute());
    }

    public function testWillRedirect(): void
    {
        $user          = Mockery::mock(HasPasswordPolicyInterface::class);
        $eventRedirect = new PasswordExpiredEvent($user, 'home', true);
        $this->assertTrue($eventRedirect->willRedirect());

        $eventNoRedirect = new PasswordExpiredEvent($user, 'home', false);
        $this->assertFalse($eventNoRedirect->willRedirect());
    }
}
