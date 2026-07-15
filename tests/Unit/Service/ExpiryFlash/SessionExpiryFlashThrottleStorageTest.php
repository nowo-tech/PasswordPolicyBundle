<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service\ExpiryFlash;

use Mockery;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\SessionExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

final class SessionExpiryFlashThrottleStorageTest extends UnitTestCase
{
    public function testMarkShownAndGetLastShownAt(): void
    {
        $sessionMock = Mockery::mock(Session::class);
        $sessionMock->shouldReceive('has')
                    ->once()
                    ->with('_nowo_password_policy.expiry_flash_last_shown_at.user:7')
                    ->andReturnFalse();
        $sessionMock->shouldReceive('set')
                    ->once()
                    ->with('_nowo_password_policy.expiry_flash_last_shown_at.user:7', 1_700_000_000);
        $sessionMock->shouldReceive('has')
                    ->once()
                    ->with('_nowo_password_policy.expiry_flash_last_shown_at.user:7')
                    ->andReturnTrue();
        $sessionMock->shouldReceive('get')
                    ->once()
                    ->with('_nowo_password_policy.expiry_flash_last_shown_at.user:7')
                    ->andReturn(1_700_000_000);

        $requestStackMock = Mockery::mock(RequestStack::class);
        $requestStackMock->shouldReceive('getSession')
                         ->times(3)
                         ->andReturn($sessionMock);

        $storage = new SessionExpiryFlashThrottleStorage($requestStackMock);

        $this->assertNull($storage->getLastShownAt('user:7'));
        $storage->markShown('user:7', 1_700_000_000);
        $this->assertSame(1_700_000_000, $storage->getLastShownAt('user:7'));
    }
}
