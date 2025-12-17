<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;


use Mockery\Mock;
use Mockery;
use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PasswordExpiryListenerTest extends UnitTestCase
{
    /**
     * @var RequestStack|Mock
     */
    private $requestStackMock;

    /**
     * @var Session|Mock
     */
    private $sessionMock;

    /**
     * @var PasswordExpiryListener|Mock
     */
    private $passwordExpiryListenerMock;

    /**
     * @var PasswordExpiryServiceInterface|Mock
     */
    private $passwordExpiryServiceMock;

    /**
     * @var UrlGeneratorInterface|Mock
     */
    private $urlGeneratorMock;

    /**
     * @var TranslatorInterface|Mock
     */
    private $translatorMock;

    /**
     * Setup..
     */
    protected function setUp(): void
    {
        $this->passwordExpiryServiceMock = Mockery::mock(PasswordExpiryServiceInterface::class);
        $this->requestStackMock = Mockery::mock(RequestStack::class);
        $this->urlGeneratorMock = Mockery::mock(UrlGeneratorInterface::class);
        $this->translatorMock = Mockery::mock(TranslatorInterface::class);
        $this->sessionMock = Mockery::mock(Session::class);

        $this->requestStackMock->shouldReceive('getSession')
                               ->andReturn($this->sessionMock);

        $this->translatorMock->shouldReceive('trans')
                             ->andReturnUsing(function ($id, $parameters = [], $domain = null) {
                                 return $id;
                             });

        $this->passwordExpiryListenerMock = Mockery::mock(PasswordExpiryListener::class, [
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Your password expired. You need to change it'
        ])->makePartial();
    }

    public function testOnKernelRequest(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn(null);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn(['/excluded-1', '/excluded-2']);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once()
                     ->withArgs(['error', 'Your password expired. You need to change it']);
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);
        
        // Verify that flash message was added
        $this->assertTrue(true);
    }

    public function testOnKernelRequestAsLockedRoute(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(false);

        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestExcludedRoute(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('excluded-2');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('excluded-2')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn(['excluded-1', 'excluded-2']);

        // The code checks isPasswordExpired before checking if route is excluded
        // So we need to allow it to be called, but since the route is excluded,
        // the condition `!in_array($route, $excludeRoutes) && $isPasswordExpired` will be false
        // So isPasswordExpired can be called but the result doesn't matter
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturn(false);

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestPasswordNotExpired(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn(['excluded-1', 'excluded-2']);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnFalse();

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestAsSubRequest(): void
    {
        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(false);

        $this->passwordExpiryServiceMock->shouldNotReceive('isLockedRoute');
        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithNullRoute(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn(null);

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        // Should return early without checking anything
        $this->passwordExpiryServiceMock->shouldNotReceive('isLockedRoute');
        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithRedirectOnExpiry(): void
    {
        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn(null);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Your password expired',
            true // redirect_on_expiry
        );

        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();
        $this->passwordExpiryServiceMock->shouldReceive('getResetPasswordRouteName')
                                        ->once()
                                        ->andReturn('reset_password');

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once();
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $this->urlGeneratorMock->shouldReceive('generate')
                               ->once()
                               ->with('reset_password')
                               ->andReturn('/reset-password');

        $responseEventMock->shouldReceive('setResponse')
                          ->once()
                          ->with(Mockery::type(RedirectResponse::class));

        $listener->onKernelRequest($responseEventMock);
        
        // Verify that redirect was set
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithInvalidRoute(): void
    {
        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn(null);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Your password expired',
            true // redirect_on_expiry
        );

        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();
        $this->passwordExpiryServiceMock->shouldReceive('getResetPasswordRouteName')
                                        ->once()
                                        ->andReturn('invalid_route');

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once();
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        // URL generator throws exception for invalid route
        $this->urlGeneratorMock->shouldReceive('generate')
                               ->once()
                               ->with('invalid_route')
                               ->andThrow(new \Symfony\Component\Routing\Exception\RouteNotFoundException('Route not found'));

        // Should not set response (no redirect), but flash message should be shown
        $responseEventMock->shouldNotReceive('setResponse');

        $listener->onKernelRequest($responseEventMock);
        
        // Verify that flash message was shown even though redirect failed
        $this->assertTrue(true);
    }

    public function testLoggingWithDifferentLevels(): void
    {
        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        
        // Test debug level
        $loggerMock->shouldReceive('debug')
                   ->once()
                   ->with('Test debug message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Error message',
            false,
            $loggerMock,
            true,
            'debug'
        );
        
        // Use reflection to call private log method
        $reflection = new \ReflectionClass($listener);
        $logMethod = $reflection->getMethod('log');
        $logMethod->invoke($listener, 'debug', 'Test debug message');
        
        // Test info level
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test info message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'info', 'Test info message');
        
        // Test notice level
        $loggerMock->shouldReceive('notice')
                   ->once()
                   ->with('Test notice message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'notice', 'Test notice message');
        
        // Test warning level
        $loggerMock->shouldReceive('warning')
                   ->once()
                   ->with('Test warning message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'warning', 'Test warning message');
        
        // Test error level
        $loggerMock->shouldReceive('error')
                   ->once()
                   ->with('Test error message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'error', 'Test error message');
        
        // Test default level (unknown level should default to info)
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Test unknown level message', Mockery::on(function ($context) {
                       return isset($context['bundle']) && $context['bundle'] === 'PasswordPolicyBundle';
                   }));
        
        $logMethod->invoke($listener, 'unknown', 'Test unknown level message');
        
        $this->assertTrue(true);
    }

    public function testLoggingWithNullLogger(): void
    {
        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Error message',
            false,
            null,
            true,
            'info'
        );
        
        // Use reflection to call private log method with null logger
        $reflection = new \ReflectionClass($listener);
        $logMethod = $reflection->getMethod('log');
        
        // Should not throw exception when logger is null
        $logMethod->invoke($listener, 'info', 'Test message');
        
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithArrayErrorMessage(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $userMock = Mockery::mock(\Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface::class);
        $userMock->shouldReceive('getId')
                 ->andReturn(123);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($userMock);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();

        // Create listener with array error message
        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            ['title' => 'Expired', 'message' => 'Change password']
        );

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once()
                     ->with('error', ['title' => 'Expired', 'message' => 'Change password']);
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $listener->onKernelRequest($responseEventMock);
        
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithEventDispatcher(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $userMock = Mockery::mock(\Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface::class);
        $userMock->shouldReceive('getId')
                 ->andReturn(123);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($userMock);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();

        $eventDispatcherMock = Mockery::mock(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
        $eventDispatcherMock->shouldReceive('dispatch')
                            ->once()
                            ->with(Mockery::type(\Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent::class));

        // Create listener with event dispatcher
        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Error message',
            false,
            null,
            true,
            'info',
            $eventDispatcherMock
        );

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once();
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $listener->onKernelRequest($responseEventMock);
        
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithGetUserIdentifier(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        // Create a concrete class that implements HasPasswordPolicyInterface and has getUserIdentifier method
        // This allows method_exists() to work correctly in the listener
        $userMock = new class implements \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface {
            public function getUserIdentifier(): string { return 'test@example.com'; }
            public function getId(): ?int { return 123; }
            public function getPassword(): string { return ''; }
            public function getPasswordChangedAt(): ?\DateTime { return null; }
            public function setPasswordChangedAt(\DateTime $dateTime): self { return $this; }
            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection { return new \Doctrine\Common\Collections\ArrayCollection(); }
            public function addPasswordHistory(\Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface $passwordHistory): static { return $this; }
        };

        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($userMock);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();

        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Password expired detected', Mockery::on(function ($context) {
                       return isset($context['user_identifier']) && 
                              $context['user_identifier'] === 'test@example.com' &&
                              isset($context['user_id']) &&
                              isset($context['route']) &&
                              isset($context['redirect_on_expiry']) &&
                              isset($context['bundle']);
                   }));

        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Error message',
            false,
            $loggerMock,
            true,
            'info'
        );

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once();
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $listener->onKernelRequest($responseEventMock);
        
        $this->assertTrue(true);
    }

    public function testOnKernelRequestWithGetEmail(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(RequestEvent::class);
        $responseEventMock->shouldReceive('isMainRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        // Create a concrete class that has getEmail but not getUserIdentifier
        $userMock = new class implements \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface {
            public function getEmail(): string { return 'test@example.com'; }
            public function getId(): ?int { return 123; }
            public function getPassword(): string { return ''; }
            public function getPasswordChangedAt(): ?\DateTime { return null; }
            public function setPasswordChangedAt(\DateTime $dateTime): self { return $this; }
            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection { return new \Doctrine\Common\Collections\ArrayCollection(); }
            public function addPasswordHistory(\Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface $passwordHistory): static { return $this; }
        };

        $tokenStorageMock = Mockery::mock(TokenStorageInterface::class);
        $tokenMock = Mockery::mock(TokenInterface::class);
        $tokenMock->shouldReceive('getUser')
                  ->andReturn($userMock);
        $tokenStorageMock->shouldReceive('getToken')
                         ->andReturn($tokenMock);
        $this->passwordExpiryServiceMock->tokenStorage = $tokenStorageMock;

        $this->passwordExpiryServiceMock->shouldReceive('isLockedRoute')
                                        ->once()
                                        ->with('route')
                                        ->andReturn(true);
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->once()
                                        ->andReturn([]);
        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnTrue();

        $loggerMock = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $loggerMock->shouldReceive('info')
                   ->once()
                   ->with('Password expired detected', Mockery::on(function ($context) {
                       return isset($context['user_identifier']) && 
                              $context['user_identifier'] === 'test@example.com' &&
                              isset($context['user_id']) &&
                              isset($context['route']) &&
                              isset($context['redirect_on_expiry']) &&
                              isset($context['bundle']);
                   }));

        $listener = new PasswordExpiryListener(
            $this->passwordExpiryServiceMock,
            $this->requestStackMock,
            $this->urlGeneratorMock,
            $this->translatorMock,
            'error',
            'Error message',
            false,
            $loggerMock,
            true,
            'info'
        );

        $flashBagMock = Mockery::mock(FlashBagInterface::class);
        $flashBagMock->shouldReceive('add')
                     ->once();
        $this->sessionMock->shouldReceive('getFlashBag')
                          ->once()
                          ->andReturn($flashBagMock);

        $listener->onKernelRequest($responseEventMock);
        
        $this->assertTrue(true);
    }
}
