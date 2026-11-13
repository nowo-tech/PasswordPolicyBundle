<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;

use DateTime;
use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordExpiryDetectedEvent;
use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\SessionExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryService;
use Nowo\PasswordPolicyBundle\Subject\CurrentPasswordPolicySubjectProviderInterface;
use Nowo\PasswordPolicyBundle\Subject\TokenPasswordPolicySubjectProvider;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\DomainSecurityUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\EntitySecurityUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\FixedClock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * REQ-ARCH-001 rule 4: the expiry listener announces an expired password with scalar ids, for an entity user and for
 * a non-entity user whose read-only subject comes from the opt-in provider.
 */
final class PasswordExpiryListenerSubjectTest extends UnitTestCase
{
    private const NOW = '2026-11-13 12:00:00';

    public function testNonEntityUserGetsTheScalarEvent(): void
    {
        $user   = new DomainSecurityUser('7', new DateTimeImmutable(self::NOW . ' -120 days'));
        $events = $this->handle($user, DomainSecurityUser::class, true);

        self::assertCount(1, $events);
        self::assertInstanceOf(PasswordExpiryDetectedEvent::class, $events[0]);
        self::assertSame([DomainSecurityUser::class, '7', 'app_home', false], [$events[0]->getSubjectClass(), $events[0]->getSubjectId(), $events[0]->getRoute(), $events[0]->willRedirect()]);
    }

    public function testNonEntityUserIsIgnoredWithoutTheProvider(): void
    {
        $user = new DomainSecurityUser('7', new DateTimeImmutable(self::NOW . ' -120 days'));

        self::assertSame([], $this->handle($user, DomainSecurityUser::class, false));
    }

    public function testEntityUserGetsTheEntityEventThenTheScalarOne(): void
    {
        $user = new EntitySecurityUser('u-1');
        $user->setPasswordChangedAt(new DateTime(self::NOW . ' -120 days'));
        $events = $this->handle($user, EntitySecurityUser::class, true);

        self::assertCount(2, $events);
        self::assertInstanceOf(PasswordExpiredEvent::class, $events[0]);
        self::assertInstanceOf(PasswordExpiryDetectedEvent::class, $events[1]);
        self::assertSame([EntitySecurityUser::class, 'u-1'], [$events[1]->getSubjectClass(), $events[1]->getSubjectId()]);
    }

    /**
     * @param class-string $configuredClass
     *
     * @return list<object>
     */
    private function handle(UserInterface $user, string $configuredClass, bool $withProvider): array
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));
        $provider = $withProvider ? new TokenPasswordPolicySubjectProvider($tokenStorage) : null;

        $urls    = Mockery::mock(UrlGeneratorInterface::class);
        $service = new PasswordExpiryService($tokenStorage, $urls, null, null, false, 3600, new FixedClock(new DateTimeImmutable(self::NOW)), false, $provider);
        $service->addEntity(new PasswordExpiryConfiguration($configuredClass, 90, ['app_*'], [], 'app_reset'));

        $request = new Request();
        $request->attributes->set('_route', 'app_home');
        $request->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $events     = [];
        $dispatcher = new EventDispatcher();
        foreach ([PasswordExpiredEvent::class, PasswordExpiryDetectedEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, static function (object $event) use (&$events): void {
                $events[] = $event;
            });
        }

        $translator = Mockery::mock(TranslatorInterface::class);
        $translator->shouldReceive('trans')->andReturnUsing(static fn (string $id): string => $id);

        $listener = new PasswordExpiryListener(
            $service,
            $tokenStorage,
            $requestStack,
            $urls,
            $translator,
            'error',
            'expired',
            new SessionExpiryFlashThrottleStorage($requestStack),
            eventDispatcher: $dispatcher,
            subjectProvider: $provider ?? Mockery::mock(CurrentPasswordPolicySubjectProviderInterface::class, ['getCurrentSubject' => null]),
        );
        $listener->onKernelRequest(new RequestEvent(Mockery::mock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST));

        return $events;
    }
}
