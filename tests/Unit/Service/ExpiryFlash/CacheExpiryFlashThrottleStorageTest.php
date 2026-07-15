<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service\ExpiryFlash;

use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\CacheExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheExpiryFlashThrottleStorageTest extends UnitTestCase
{
    public function testMarkShownAndGetLastShownAt(): void
    {
        $storage = new CacheExpiryFlashThrottleStorage(new ArrayAdapter(), 3600);

        $this->assertNull($storage->getLastShownAt('user:42'));

        $storage->markShown('user:42', 1_700_000_000);

        $this->assertSame(1_700_000_000, $storage->getLastShownAt('user:42'));
    }

    public function testDifferentSubjectKeysAreIsolated(): void
    {
        $storage = new CacheExpiryFlashThrottleStorage(new ArrayAdapter(), 3600);

        $storage->markShown('user:1', 100);
        $storage->markShown('user:2', 200);

        $this->assertSame(100, $storage->getLastShownAt('user:1'));
        $this->assertSame(200, $storage->getLastShownAt('user:2'));
    }
}
