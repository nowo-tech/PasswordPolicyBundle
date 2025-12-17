<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use Carbon\Carbon;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryService;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class PasswordExpiryServiceTest extends UnitTestCase
{
    /**
     * @var HasPasswordPolicyInterface|Mock
     */
    private $userMock;

    /**
     * @var UrlGeneratorInterface|Mock
     */
    private $routerMock;

    /**
     * @var PasswordExpiryServiceInterface|Mock
     */
    private $passwordExpiryServiceMock;

    /**
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage|Mock
     */
    private $tokenStorageMock;

    protected function setUp(): void
    {
        $this->tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $this->routerMock = Mockery::mock(UrlGeneratorInterface::class);
        $this->userMock = Mockery::mock(HasPasswordPolicyInterface::class);
        $this->passwordExpiryServiceMock = Mockery::mock(PasswordExpiryService::class, [
            $this->tokenStorageMock,
            $this->routerMock,
        ])->makePartial();
    }

    /**
     * @throws RuntimeException
     */
    public function testIsPasswordExpired(): void
    {
        $expiredPassword = (Carbon::now())->modify('-100 days');
        $notExpiredPassword = (Carbon::now())->modify('-89 days');
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->twice()
                       ->andReturn($expiredPassword, $notExpiredPassword);

        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset_password')
        );

        $this->assertTrue($this->passwordExpiryServiceMock->isPasswordExpired());
        $this->assertFalse($this->passwordExpiryServiceMock->isPasswordExpired());
    }

    public function testGetLockedRoutes(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock', 'dashboard'], ['logout'], 'reset_password')
        );

        $lockedRoutes = $this->passwordExpiryServiceMock->getLockedRoutes();

        $this->assertEquals(['lock', 'dashboard'], $lockedRoutes);
    }

    public function testIsLockedRoute(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock', 'dashboard'], [], 'reset_password')
        );

        $this->assertTrue($this->passwordExpiryServiceMock->isLockedRoute('lock'));
        $this->assertTrue($this->passwordExpiryServiceMock->isLockedRoute('dashboard'));
        $this->assertFalse($this->passwordExpiryServiceMock->isLockedRoute('home'));
    }

    public function testGetResetPasswordRouteName(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset_password')
        );

        $routeName = $this->passwordExpiryServiceMock->getResetPasswordRouteName();
        $this->assertEquals('reset_password', $routeName);
    }

    public function testGetResetPasswordRouteNameWithEntityClass(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset_password')
        );

        $routeName = $this->passwordExpiryServiceMock->getResetPasswordRouteName($this->userMock::class);
        $this->assertEquals('reset_password', $routeName);
    }

    public function testGetResetPasswordRouteNameReturnsEmptyWhenNoEntity(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        // No entity added
        $routeName = $this->passwordExpiryServiceMock->getResetPasswordRouteName();
        $this->assertEquals('', $routeName);
    }

    public function testIsPasswordExpiredWhenEntitiesIsNull(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        // No entity added - entities is null
        $result = $this->passwordExpiryServiceMock->isPasswordExpired();
        $this->assertFalse($result);
    }

    public function testIsPasswordExpiredWithFutureDate(): void
    {
        $futureDate = (Carbon::now())->modify('+10 days');
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->once()
                       ->andReturn($futureDate);

        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset_password')
        );

        // Should return false (not expired) when date is in the future
        $this->assertFalse($this->passwordExpiryServiceMock->isPasswordExpired());
    }

    public function testIsPasswordExpiredWhenNoUser(): void
    {
        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn(null);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset_password')
        );

        $this->assertFalse($this->passwordExpiryServiceMock->isPasswordExpired());
    }

    public function testGetExcludedRoutes(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], ['logout', 'login'], 'reset_password')
        );

        $excludedRoutes = $this->passwordExpiryServiceMock->getExcludedRoutes();
        $this->assertEquals(['logout', 'login'], $excludedRoutes);
    }

    public function testGetCurrentUserWithAnonUser(): void
    {
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn('anon.');

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCurrentUser method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCurrentUser');

        $result = $method->invoke($service);
        $this->assertNull($result);
    }

    public function testGetCurrentUserWithNonHasPasswordPolicyInterface(): void
    {
        $nonPolicyUser = new \stdClass();

        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($nonPolicyUser);

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCurrentUser method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCurrentUser');

        $result = $method->invoke($service);
        $this->assertNull($result);
    }

    public function testGetCurrentUserWithValidUser(): void
    {
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($this->userMock);

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCurrentUser method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCurrentUser');

        $result = $method->invoke($service);
        $this->assertEquals($this->userMock, $result);
    }

    public function testGetCurrentUserWithNullToken(): void
    {
        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn(null);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCurrentUser method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCurrentUser');

        $result = $method->invoke($service);
        $this->assertNull($result);
    }

    public function testPrepareEntityClassWithNullAndUser(): void
    {
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($this->userMock);

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private prepareEntityClass method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('prepareEntityClass');

        $result = $method->invoke($service, null);
        $this->assertEquals($this->userMock::class, $result);
    }

    public function testPrepareEntityClassWithProvidedClass(): void
    {
        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private prepareEntityClass method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('prepareEntityClass');

        $result = $method->invoke($service, 'CustomEntityClass');
        $this->assertEquals('CustomEntityClass', $result);
    }

    public function testGetCacheKey(): void
    {
        $dateTime = new \DateTime();
        $this->userMock->shouldReceive('getId')
                       ->andReturn(123);
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->andReturn($dateTime);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCacheKey method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCacheKey');

        $result = $method->invoke($service, $this->userMock);

        // Cache key should contain user class hash, user ID, and timestamp
        $this->assertStringStartsWith('password_expiry_', $result);
        $this->assertStringContainsString('123', $result);
        $this->assertStringContainsString((string) $dateTime->getTimestamp(), $result);
    }

    public function testGetCacheKeyWithNoId(): void
    {
        $dateTime = new \DateTime();
        // Test getCacheKey with a user that has getId method but returns null
        // The code uses method_exists first, so if method exists, it calls getId()
        // When getId() returns null, it's cast to string '' (empty string)
        $userWithoutId = Mockery::mock(HasPasswordPolicyInterface::class);
        $userWithoutId->shouldReceive('getPasswordChangedAt')
                      ->andReturn($dateTime);
        $userWithoutId->shouldReceive('getId')
                      ->andReturn(null);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCacheKey method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCacheKey');

        $result = $method->invoke($service, $userWithoutId);

        // Cache key should be generated (getId() returns null which becomes empty string)
        $this->assertStringStartsWith('password_expiry_', $result);
        $this->assertNotEmpty($result);
    }

    public function testGetCacheKeyWithNoPasswordChangedAt(): void
    {
        $this->userMock->shouldReceive('getId')
                       ->andReturn(123);
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->andReturn(null);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock);

        // Use reflection to call private getCacheKey method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getCacheKey');

        $result = $method->invoke($service, $this->userMock);

        // Cache key should contain 'no-date' when passwordChangedAt is null
        $this->assertStringStartsWith('password_expiry_', $result);
        $this->assertStringContainsString('no-date', $result);
    }

    public function testIsPasswordExpiredWithCacheEnabled(): void
    {
        $cacheMock = Mockery::mock(\Psr\Cache\CacheItemPoolInterface::class);
        $cacheItemMock1 = Mockery::mock(\Psr\Cache\CacheItemInterface::class);
        $cacheItemMock2 = Mockery::mock(\Psr\Cache\CacheItemInterface::class);

        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($this->userMock);

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $passwordChangedAt = Carbon::now()->subDays(100);
        // getPasswordChangedAt is called:
        // 1. In getCacheKey() when checking cache (line 82)
        // 2. When calculating expiry (line 93)
        // 3. In getCacheKey() when storing in cache (line 109)
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->times(3)
                       ->andReturn($passwordChangedAt);
        // getId is called:
        // 1. In getCacheKey() when checking cache (line 82)
        // 2. In getCacheKey() when storing in cache (line 109)
        $this->userMock->shouldReceive('getId')
                       ->twice()
                       ->andReturn(123);

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock, $cacheMock, true, 3600);

        $config = new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset');
        $service->addEntity($config);

        // Cache miss - first call to getItem (check cache)
        $cacheItemMock1->shouldReceive('isHit')
                       ->once()
                       ->andReturn(false);

        // Second call to getItem (store in cache)
        $cacheItemMock2->shouldReceive('set')
                      ->once()
                      ->with(true)
                      ->andReturnSelf();
        $cacheItemMock2->shouldReceive('expiresAfter')
                      ->once()
                      ->with(3600)
                      ->andReturnSelf();

        $cacheMock->shouldReceive('getItem')
                  ->twice()
                  ->andReturn($cacheItemMock1, $cacheItemMock2);
        $cacheMock->shouldReceive('save')
                  ->once()
                  ->with($cacheItemMock2);

        $result = $service->isPasswordExpired();
        $this->assertTrue($result);
    }

    public function testIsPasswordExpiredWithCacheHit(): void
    {
        $cacheMock = Mockery::mock(\Psr\Cache\CacheItemPoolInterface::class);
        $cacheItemMock = Mockery::mock(\Psr\Cache\CacheItemInterface::class);

        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($this->userMock);

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($tokenMock);

        $this->userMock->shouldReceive('getId')
                       ->once()
                       ->andReturn(123);
        // getPasswordChangedAt is called in getCacheKey even when cache hits
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->once()
                       ->andReturn(Carbon::now()->subDays(50));

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock, $cacheMock, true, 3600);

        $config = new PasswordExpiryConfiguration($this->userMock::class, 90, ['lock'], [], 'reset');
        $service->addEntity($config);

        // Cache hit - should return cached value
        $cacheItemMock->shouldReceive('isHit')
                      ->once()
                      ->andReturn(true);
        $cacheItemMock->shouldReceive('get')
                      ->once()
                      ->andReturn(false);

        $cacheMock->shouldReceive('getItem')
                  ->once()
                  ->andReturn($cacheItemMock);

        $result = $service->isPasswordExpired();
        $this->assertFalse($result);
    }

    public function testInvalidateCache(): void
    {
        $cacheMock = Mockery::mock(\Psr\Cache\CacheItemPoolInterface::class);

        $this->userMock->shouldReceive('getId')
                       ->andReturn(123);
        $this->userMock->shouldReceive('getPasswordChangedAt')
                       ->andReturn(new \DateTime());

        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock, $cacheMock, true, 3600);

        $cacheMock->shouldReceive('deleteItem')
                  ->once()
                  ->with(Mockery::type('string'));

        $service->invalidateCache($this->userMock);

        $this->assertTrue(true);
    }

    public function testInvalidateCacheWhenCacheDisabled(): void
    {
        $service = new PasswordExpiryService($this->tokenStorageMock, $this->routerMock, null, false, 3600);

        // Should not throw exception when cache is disabled
        $service->invalidateCache($this->userMock);

        $this->assertTrue(true);
    }
}
