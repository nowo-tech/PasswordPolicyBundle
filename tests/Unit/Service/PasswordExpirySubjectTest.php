<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use DateTime;
use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubject;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryCheckerInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryService;
use Nowo\PasswordPolicyBundle\Subject\CurrentPasswordPolicySubjectProviderInterface;
use Nowo\PasswordPolicyBundle\Subject\NullCurrentPasswordPolicySubjectProvider;
use Nowo\PasswordPolicyBundle\Subject\TokenPasswordPolicySubjectProvider;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\AdminDomainSecurityUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\DomainSecurityUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\EntitySecurityUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\FixedClock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * REQ-ARCH-001 rules 1 and 4: password expiry of a read-only subject (no entity), equivalent to the entity check.
 */
final class PasswordExpirySubjectTest extends UnitTestCase
{
    private const NOW = '2026-11-13 12:00:00';

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function changeDates(): iterable
    {
        foreach ([false, true] as $expireWhenNeverChanged) {
            $suffix = $expireWhenNeverChanged ? ' (expire_when_never_changed)' : '';
            yield 'expired' . $suffix => ['-100 days', $expireWhenNeverChanged];
            yield 'exactly at expiry' . $suffix => ['-90 days', $expireWhenNeverChanged];
            yield 'not expired' . $suffix => ['-89 days', $expireWhenNeverChanged];
            yield 'in the future' . $suffix => ['+3 days', $expireWhenNeverChanged];
            yield 'never changed' . $suffix => [null, $expireWhenNeverChanged];
        }
    }

    #[DataProvider('changeDates')]
    public function testSubjectCheckMatchesTheEntityCheck(?string $offset, bool $expireWhenNeverChanged): void
    {
        $entity = new EntitySecurityUser('u-1');
        if ($offset !== null) {
            $entity->setPasswordChangedAt(new DateTime(self::NOW . ' ' . $offset));
        }

        $service = $this->service(EntitySecurityUser::class, $this->tokenOf($entity), null, $expireWhenNeverChanged);

        self::assertSame(
            $service->isPasswordExpired(),
            $service->isSubjectPasswordExpired(PasswordPolicySubject::fromEntity($entity)),
        );
    }

    public function testCurrentNonEntityUserIsCheckedThroughTheProvider(): void
    {
        $tokenStorage = $this->tokenOf(new DomainSecurityUser('7', new DateTimeImmutable(self::NOW . ' -120 days')));
        $service      = $this->service(DomainSecurityUser::class, $tokenStorage, new TokenPasswordPolicySubjectProvider($tokenStorage));

        self::assertTrue($service->isPasswordExpired());
        self::assertSame(['app_*'], $service->getLockedRoutes());
        self::assertSame('app_reset', $service->getResetPasswordRouteName());
    }

    public function testSubclassOfAConfiguredSubjectClassUsesItsSettings(): void
    {
        $tokenStorage = $this->tokenOf(new AdminDomainSecurityUser('8', new DateTimeImmutable(self::NOW . ' -120 days')));
        $service      = $this->service(DomainSecurityUser::class, $tokenStorage, new TokenPasswordPolicySubjectProvider($tokenStorage));

        self::assertTrue($service->isPasswordExpired());
        self::assertSame(['app_*'], $service->getLockedRoutes());
    }

    public function testDefaultProviderLeavesNonEntityUsersUnchecked(): void
    {
        $tokenStorage = $this->tokenOf(new DomainSecurityUser('7', new DateTimeImmutable(self::NOW . ' -120 days')));
        $service      = $this->service(DomainSecurityUser::class, $tokenStorage, new NullCurrentPasswordPolicySubjectProvider());

        self::assertFalse($service->isPasswordExpired(), 'Unchanged default: only entity users are checked');
        self::assertSame([], $service->getLockedRoutes());
    }

    public function testUnconfiguredSubjectClassIsNotExpired(): void
    {
        $service = $this->service(DomainSecurityUser::class, new TokenStorage());

        self::assertFalse($service->isSubjectPasswordExpired(new PasswordPolicySubject('App\\Unknown', '1', null, new DateTimeImmutable(self::NOW . ' -400 days'))));
    }

    public function testCacheAndInvalidation(): void
    {
        $cache   = new ArrayAdapter();
        $service = new PasswordExpiryService(
            new TokenStorage(),
            Mockery::mock(UrlGeneratorInterface::class),
            null,
            $cache,
            true,
            3600,
            new FixedClock(new DateTimeImmutable(self::NOW)),
        );
        $service->addEntity(new PasswordExpiryConfiguration(DomainSecurityUser::class, 90, ['app_*'], [], 'app_reset'));

        $subject = new PasswordPolicySubject(DomainSecurityUser::class, '9', null, new DateTimeImmutable(self::NOW . ' -120 days'));
        self::assertTrue($service->isSubjectPasswordExpired($subject));
        self::assertNotSame([], $cache->getValues(), 'The status is cached');

        $service->invalidateSubjectCache($subject);
        self::assertSame([], $cache->getValues());
    }

    public function testTokenProviderIgnoresEntitiesAndForeignUsers(): void
    {
        self::assertNull((new TokenPasswordPolicySubjectProvider())->getCurrentSubject());
        self::assertNull((new TokenPasswordPolicySubjectProvider(new TokenStorage()))->getCurrentSubject());
        self::assertNull((new TokenPasswordPolicySubjectProvider($this->tokenOf(new EntitySecurityUser('u-1'))))->getCurrentSubject());
        self::assertNull((new TokenPasswordPolicySubjectProvider($this->tokenOf(new InMemoryUser('x', null))))->getCurrentSubject());

        $user = new DomainSecurityUser('7', null);
        self::assertSame($user, (new TokenPasswordPolicySubjectProvider($this->tokenOf($user)))->getCurrentSubject());
    }

    private function service(
        string $class,
        TokenStorage $tokenStorage,
        ?CurrentPasswordPolicySubjectProviderInterface $provider = null,
        bool $expireWhenNeverChanged = false,
    ): PasswordExpiryService&PasswordExpiryCheckerInterface {
        $service = new PasswordExpiryService(
            $tokenStorage,
            Mockery::mock(UrlGeneratorInterface::class),
            null,
            null,
            false,
            3600,
            new FixedClock(new DateTimeImmutable(self::NOW)),
            $expireWhenNeverChanged,
            $provider,
        );
        $service->addEntity(new PasswordExpiryConfiguration($class, 90, ['app_*'], [], 'app_reset'));

        return $service;
    }

    private function tokenOf(UserInterface $user): TokenStorage
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        return $tokenStorage;
    }
}
