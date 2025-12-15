<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;


use Carbon\Carbon;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Mockery;
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
            new PasswordExpiryConfiguration($this->userMock::class, 90, 'lock')
        );

        $this->assertTrue($this->passwordExpiryServiceMock->isPasswordExpired());
        $this->assertFalse($this->passwordExpiryServiceMock->isPasswordExpired());
    }

    public function testGenerateLockedRoute(): void
    {
        $legacyMock = Mockery::mock(TokenInterface::class)
                             ->shouldReceive('getUser')
                             ->andReturn($this->userMock)
                             ->getMock();

        $this->tokenStorageMock->shouldReceive('getToken')
                               ->andReturn($legacyMock);

        $this->passwordExpiryServiceMock->addEntity(
            new PasswordExpiryConfiguration($this->userMock::class, 90, 'lock', ['id' => 1])
        );

        $this->routerMock->shouldReceive('generate')
                         ->withArgs(['lock', ['id' => 1, 'foo' => 'bar']])
                         ->andReturn('lock/1');

        $route = $this->passwordExpiryServiceMock->generateLockedRoute(null, ['foo' => 'bar']);

        $this->assertEquals('lock/1', $route);
    }

}
