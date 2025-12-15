<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;

use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

final class PasswordExpiryListenerTest extends UnitTestCase
{
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
     * Setup..
     */
    protected function setUp(): void
    {
        $this->passwordExpiryServiceMock = Mockery::mock(PasswordExpiryServiceInterface::class);

        $this->passwordExpiryServiceMock->shouldReceive('getLockedRoute')
                                        ->withNoArgs()
                                        ->andReturn('locked');
        $this->passwordExpiryServiceMock->shouldReceive('getExcludedRoutes')
                                        ->withNoArgs()
                                        ->andReturn(['/excluded-1', '/excluded-2']);
        $this->passwordExpiryServiceMock->shouldReceive('generateLockedRoute')
                                        ->andReturn('/locked');

        $this->sessionMock = Mockery::mock(Session::class);

        $this->passwordExpiryListenerMock = Mockery::mock(PasswordExpiryListener::class, [
            $this->passwordExpiryServiceMock,
            $this->sessionMock,
            'error',
            'Your password expired. You need to change it',
        ])->makePartial();
    }

    public function testOnKernelRequest(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('getPathInfo')
                    ->once()
                    ->andReturn('/route');
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('route');

        $responseEventMock = Mockery::mock(GetResponseEvent::class);
        $responseEventMock->shouldReceive('isMasterRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $responseEventMock->shouldReceive('setResponse')
                          ->once()
                          ->andReturnUsing(function (RedirectResponse $redirectResponse): void {
                              $this->assertSame('/locked', $redirectResponse->getTargetUrl());
                          });

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
    }

    public function testOnKernelRequestAsLockedRoute(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('getPathInfo')
                    ->once()
                    ->andReturn('/locked');
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('/route');

        $responseEventMock = Mockery::mock(GetResponseEvent::class);
        $responseEventMock->shouldReceive('isMasterRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestExcludedRoute(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('getPathInfo')
                    ->once()
                    ->andReturn('/route');
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('/excluded-2');

        $responseEventMock = Mockery::mock(GetResponseEvent::class);
        $responseEventMock->shouldReceive('isMasterRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestPasswordNotExpired(): void
    {
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('getPathInfo')
                    ->once()
                    ->andReturn('/route');
        $requestMock->shouldReceive('get')
                    ->with('_route')
                    ->once()
                    ->andReturn('/route');

        $responseEventMock = Mockery::mock(GetResponseEvent::class);
        $responseEventMock->shouldReceive('isMasterRequest')
                          ->andReturn(true);
        $responseEventMock->shouldReceive('getRequest')
                          ->andReturn($requestMock);

        $this->passwordExpiryServiceMock->shouldReceive('isPasswordExpired')
                                        ->once()
                                        ->andReturnFalse();

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestAsSubRequest(): void
    {
        $responseEventMock = Mockery::mock(GetResponseEvent::class);
        $responseEventMock->shouldReceive('isMasterRequest')
                          ->andReturn(false);

        $this->passwordExpiryServiceMock->shouldNotReceive('isPasswordExpired');

        $this->passwordExpiryListenerMock->onKernelRequest($responseEventMock);

        $this->assertTrue(true);
    }
}
