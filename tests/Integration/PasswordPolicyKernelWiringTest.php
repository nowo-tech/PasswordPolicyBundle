<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration;

use DateTime;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\PasswordPolicyTestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function sprintf;

use const PASSWORD_BCRYPT;

/**
 * Regression: optional services (logger, event_dispatcher, cache.app, router) were decided with has() inside
 * load(), which is always false in a real kernel, so events, logging and enable_cache were silently disabled.
 */
final class PasswordPolicyKernelWiringTest extends TestCase
{
    private PasswordPolicyTestKernel $kernel;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-password-policy-test-kernel');

        $this->kernel = new PasswordPolicyTestKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $this->container = $container;
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testPasswordReuseDispatchesEventAndLogs(): void
    {
        $dispatcher = $this->container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $events = [];
        $dispatcher->addListener(PasswordReuseAttemptedEvent::class, static function (PasswordReuseAttemptedEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $user = new KernelUser();
        $user->addPasswordHistory(KernelUser::historyEntry(password_hash('Old-password1', PASSWORD_BCRYPT, ['cost' => 4])));
        $user->plainPassword = 'Old-password1';

        $validator = $this->container->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $violations = $validator->validate($user);

        self::assertCount(1, $violations);
        self::assertCount(1, $events);

        $logger = $this->container->get('logger');
        self::assertInstanceOf(BufferingLogger::class, $logger);
        $messages = array_column($logger->cleanLogs(), 1);
        self::assertContains('Password reuse attempt detected', $messages);
    }

    public function testEnableCacheCachesExpiryStatus(): void
    {
        $user                    = new KernelUser();
        $user->passwordChangedAt = new DateTime('-200 days');
        $tokenStorage            = $this->container->get('security.token_storage');
        self::assertInstanceOf(TokenStorage::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $service = $this->container->get(PasswordExpiryService::class);
        self::assertInstanceOf(PasswordExpiryService::class, $service);
        self::assertTrue($service->isPasswordExpired());

        // The expiry status was stored in cache.app (key format of PasswordExpiryService::getCacheKey()).
        $cache = $this->container->get('cache.app');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $key = sprintf('password_expiry_%s_%s_%s', md5(KernelUser::class), $user->getId(), $user->passwordChangedAt->getTimestamp());
        self::assertTrue($cache->hasItem($key));
        self::assertTrue($cache->getItem($key)->get());
    }

    public function testListenersReceiveLoggerAndEventDispatcher(): void
    {
        $expiryListener = $this->container->get(PasswordExpiryListener::class);
        self::assertInstanceOf(PasswordExpiryListener::class, $expiryListener);
        self::assertNotNull($this->readProperty($expiryListener, 'logger'));
        self::assertNotNull($this->readProperty($expiryListener, 'eventDispatcher'));

        $entityListener = $this->container->get('nowo_password_policy.entity_listener.' . strtolower(str_replace('\\', '_', KernelUser::class)));
        self::assertInstanceOf(PasswordEntityListener::class, $entityListener);
        self::assertNotNull($this->readProperty($entityListener, 'logger'));
        self::assertNotNull($this->readProperty($entityListener, 'eventDispatcher'));

        $service = $this->container->get(PasswordExpiryService::class);
        self::assertInstanceOf(PasswordExpiryService::class, $service);
        self::assertNotNull($this->readProperty($service, 'router'));
    }

    private function readProperty(object $object, string $property): mixed
    {
        return (new ReflectionProperty($object, $property))->getValue($object);
    }
}
