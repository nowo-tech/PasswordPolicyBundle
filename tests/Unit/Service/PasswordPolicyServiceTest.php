<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyService;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class PasswordPolicyServiceTest extends UnitTestCase
{
    /**
     * @var HasPasswordPolicyInterface|Mock
     */
    private $entityMock;

    /**
     * @var UserPasswordHasherInterface|Mock
     */
    private $userPasswordHasherMock;

    /**
     * @var PasswordPolicyService
     */
    private $passwordPolicyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userPasswordHasherMock = Mockery::mock(UserPasswordHasherInterface::class);
        $this->passwordPolicyService = new PasswordPolicyService($this->userPasswordHasherMock);
        $this->entityMock = Mockery::mock(HasPasswordPolicyInterface::class);
    }

    public function testGetHistoryByPasswordMatch(): void
    {
        $history1 = $this->makePasswordHistoryMock('hash1');
        $history2 = $this->makePasswordHistoryMock('hash2');

        $history = [$history1, $history2];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        // Mock UserInterface for password verification
        $tempUser = Mockery::mock(UserInterface::class);
        $this->entityMock->shouldReceive('__clone')
                         ->andReturn($tempUser);

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
                                      ->with($tempUser, 'pwd')
                                      ->twice()
                                      ->andReturn(false, true);

        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        $this->assertEquals($history2, $actual);
    }

    public function testGetHistoryByPasswordNoMatch(): void
    {
        $history1 = $this->makePasswordHistoryMock('hash1');
        $history2 = $this->makePasswordHistoryMock('hash2');

        $history = [$history1, $history2];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        $tempUser = Mockery::mock(UserInterface::class);
        $this->entityMock->shouldReceive('__clone')
                         ->andReturn($tempUser);

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
                                      ->with($tempUser, 'pwd')
                                      ->twice()
                                      ->andReturn(false, false);

        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        $this->assertNull($actual);
    }

    public function testGetHistoryByPasswordEmptyHistory(): void
    {
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection());

        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        $this->assertNull($actual);
    }

    /**
     * @return Mock|PasswordHistoryMock
     */
    private function makePasswordHistoryMock(string $hashedPassword = 'hashed_pwd'): Mock
    {
        return Mockery::mock(PasswordHistoryMock::class)
                       ->shouldReceive('getPassword')
                       ->andReturn($hashedPassword)
                       ->shouldReceive('getSalt')
                       ->andReturn(null)
                       ->getMock();
    }
}
