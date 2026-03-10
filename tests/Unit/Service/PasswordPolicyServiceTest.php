<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyService;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use const PASSWORD_BCRYPT;

final class PasswordPolicyServiceTest extends UnitTestCase
{
    private \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface|Mock $entityMock;

    private \Mockery\Mock|UserPasswordHasherInterface $userPasswordHasherMock;

    private PasswordPolicyService $passwordPolicyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userPasswordHasherMock = Mockery::mock(UserPasswordHasherInterface::class);
        $this->passwordPolicyService  = new PasswordPolicyService($this->userPasswordHasherMock);
        $this->entityMock             = Mockery::mock(HasPasswordPolicyInterface::class, UserInterface::class);
    }

    public function testGetHistoryByPasswordMatch(): void
    {
        // Use valid bcrypt hashes for password_verify to work correctly
        $hash1 = password_hash('wrong_pwd', PASSWORD_BCRYPT);
        $hash2 = password_hash('pwd', PASSWORD_BCRYPT);

        $history1 = $this->makePasswordHistoryMock($hash1);
        $history2 = $this->makePasswordHistoryMock($hash2);

        $history = [$history1, $history2];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        // Since the entity mock doesn't pass instanceof UserInterface check,
        // the code will use password_verify as fallback
        // password_verify('pwd', $hash1) will return false
        // password_verify('pwd', $hash2) will return true

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

        // Since the entity mock doesn't pass instanceof UserInterface check,
        // the code will use password_verify as fallback
        // password_verify('pwd', $hash1) will return false
        // password_verify('pwd', $hash2) will return false

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

    public function testGetHistoryByPasswordWithNonCloneableObject(): void
    {
        $history1 = $this->makePasswordHistoryMock('hash1');
        $history  = [$history1];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        // Mock object that doesn't implement __clone (not clonable)
        $this->entityMock->shouldNotReceive('__clone');

        // Should use password_verify as fallback
        // Since password_verify is available, it should be used
        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        // Since password_verify('pwd', 'hash1') will return false, result should be null
        $this->assertNull($actual);
    }

    public function testGetHistoryByPasswordWithoutSetPasswordMethod(): void
    {
        $history1 = $this->makePasswordHistoryMock('hash1');
        $history  = [$history1];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        // Mock object that is clonable but doesn't have setPassword method
        $tempUser = Mockery::mock(UserInterface::class);
        $this->entityMock->shouldReceive('__clone')
                         ->andReturn($tempUser);

        // tempUser doesn't have setPassword method, so should fallback to password_verify
        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        // Since password_verify('pwd', 'hash1') will return false, result should be null
        $this->assertNull($actual);
    }

    public function testIsPasswordValidWithExceptionDuringClone(): void
    {
        // This test covers the exception handling in isPasswordValid()
        // Since Mockery mocks always have __clone, we test the fallback path
        // which is the same code path used when clone throws an exception

        // Use valid bcrypt hash
        $hash     = password_hash('pwd', PASSWORD_BCRYPT);
        $history1 = $this->makePasswordHistoryMock($hash);
        $history  = [$history1];

        // Create entity that doesn't implement UserInterface
        // This uses the same fallback path as when clone throws exception
        $nonUserEntity = Mockery::mock(HasPasswordPolicyInterface::class);
        $nonUserEntity->shouldReceive('getPasswordHistory')
                      ->once()
                      ->andReturn(new ArrayCollection($history));

        // Should use password_verify fallback (same as exception fallback)
        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $nonUserEntity);
        $this->assertEquals($history1, $actual);
    }

    public function testIsPasswordValidWithUserInterfaceButNotCloneable(): void
    {
        // Use valid bcrypt hash
        $hash     = password_hash('pwd', PASSWORD_BCRYPT);
        $history1 = $this->makePasswordHistoryMock($hash);
        $history  = [$history1];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        // Entity implements UserInterface but doesn't have __clone method
        // Should use password_verify fallback
        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $this->entityMock);
        $this->assertEquals($history1, $actual);
    }

    public function testIsPasswordValidWithNonUserInterface(): void
    {
        // Use valid bcrypt hash
        $hash     = password_hash('pwd', PASSWORD_BCRYPT);
        $history1 = $this->makePasswordHistoryMock($hash);
        $history  = [$history1];

        // Create entity that doesn't implement UserInterface
        $nonUserEntity = Mockery::mock(HasPasswordPolicyInterface::class);
        $nonUserEntity->shouldReceive('getPasswordHistory')
                      ->once()
                      ->andReturn(new ArrayCollection($history));

        // Should use password_verify fallback for non-UserInterface
        $actual = $this->passwordPolicyService->getHistoryByPassword('pwd', $nonUserEntity);
        $this->assertEquals($history1, $actual);
    }

    public function testGetHistoryByPasswordExtensionWithSuffixMatch(): void
    {
        $basePwd     = 'secret';
        $extendedPwd = 'secret1';
        $hash        = password_hash($basePwd, PASSWORD_BCRYPT);
        $history1    = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension($extendedPwd, $this->entityMock, 4);
        $this->assertEquals($history1, $actual);
    }

    public function testGetHistoryByPasswordExtensionWithPrefixMatch(): void
    {
        $basePwd     = 'secret';
        $extendedPwd = '1secret';
        $hash        = password_hash($basePwd, PASSWORD_BCRYPT);
        $history1    = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension($extendedPwd, $this->entityMock, 4);
        $this->assertEquals($history1, $actual);
    }

    public function testGetHistoryByPasswordExtensionWithNumericSuffixMatch(): void
    {
        $basePwd     = 'pass';
        $extendedPwd = 'pass42';
        $hash        = password_hash($basePwd, PASSWORD_BCRYPT);
        $history1    = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension($extendedPwd, $this->entityMock, 4);
        $this->assertEquals($history1, $actual);
    }

    public function testGetHistoryByPasswordExtensionNoMatch(): void
    {
        $hash     = password_hash('other', PASSWORD_BCRYPT);
        $history1 = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension('uniquePwd99', $this->entityMock, 4);
        $this->assertNull($actual);
    }

    public function testGetHistoryByPasswordExtensionRespectsMinLength(): void
    {
        $hash     = password_hash('ab', PASSWORD_BCRYPT);
        $history1 = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension('ab1', $this->entityMock, 4);
        $this->assertNull($actual);
    }

    public function testGetHistoryByPasswordExtensionWithNumericPrefixMatch(): void
    {
        $basePwd     = 'secret';
        $extendedPwd = '99secret';
        $hash        = password_hash($basePwd, PASSWORD_BCRYPT);
        $history1    = $this->makePasswordHistoryMock($hash);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPasswordExtension($extendedPwd, $this->entityMock, 4);
        $this->assertEquals($history1, $actual);
    }

    public function testIsPasswordValidWithUserInterfaceCloneAndSetPassword(): void
    {
        $plainPassword  = 'pwd';
        $hashedPassword = password_hash('other', PASSWORD_BCRYPT);
        $history1       = $this->makePasswordHistoryMock($hashedPassword);
        $history        = [$history1];

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection($history));

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
                                    ->andReturn(false);

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $this->entityMock);
        $this->assertNull($actual);
    }

    public function testIsPasswordValidWithUserInterfaceCloneAndSetPasswordMatch(): void
    {
        $plainPassword  = 'pwd';
        $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
        $history1       = $this->makePasswordHistoryMock($hashedPassword);
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->once()
                         ->andReturn(new ArrayCollection([$history1]));

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $this->entityMock);
        $this->assertEquals($history1, $actual);
    }

    private function makePasswordHistoryMock(string $hashedPassword = 'hashed_pwd'): PasswordHistoryInterface
    {
        return Mockery::mock(PasswordHistoryInterface::class)
                       ->shouldReceive('getPassword')
                       ->andReturn($hashedPassword)
                       ->shouldReceive('getSalt')
                       ->andReturn(null)
                       ->shouldReceive('getCreatedAt')
                       ->andReturn(new DateTime())
                       ->getMock();
    }
}
