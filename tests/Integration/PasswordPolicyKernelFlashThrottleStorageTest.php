<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration;

use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\CacheExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\PasswordPolicyTestKernel;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Regression: flash_throttle_storage "cache" and flash_throttle_storage_service were validated with has() inside
 * load(), which cannot see cache.app or application services, so a real kernel always failed to boot.
 */
final class PasswordPolicyKernelFlashThrottleStorageTest extends TestCase
{
    private ?PasswordPolicyTestKernel $kernel = null;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-password-policy-test-kernel');
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
    }

    public function testCacheStorageUsesCacheAppPool(): void
    {
        $container = $this->boot(['flash_throttle_storage' => 'cache']);

        $storage = $this->flashThrottleStorage($container);
        self::assertInstanceOf(CacheExpiryFlashThrottleStorage::class, $storage);
        self::assertSame($container->get('cache.app'), (new ReflectionProperty($storage, 'cache'))->getValue($storage));
    }

    public function testCustomStorageServiceIsInjected(): void
    {
        $container = $this->boot(['flash_throttle_storage_service' => 'app.flash_storage']);

        self::assertInstanceOf(InMemoryFlashThrottleStorage::class, $this->flashThrottleStorage($container));
    }

    public function testMissingCustomStorageServiceIsReportedByTheContainer(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('app.missing_storage');

        $this->boot(['flash_throttle_storage_service' => 'app.missing_storage']);
    }

    /**
     * @param array<string, mixed> $expiryListenerConfig
     */
    private function boot(array $expiryListenerConfig): ContainerInterface
    {
        $this->kernel = new PasswordPolicyTestKernel($expiryListenerConfig);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function flashThrottleStorage(ContainerInterface $container): mixed
    {
        $listener = $container->get(PasswordExpiryListener::class);
        self::assertInstanceOf(PasswordExpiryListener::class, $listener);

        return (new ReflectionProperty($listener, 'flashThrottleStorage'))->getValue($listener);
    }
}
