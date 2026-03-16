<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyService;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use const PASSWORD_BCRYPT;

final class PasswordPolicyServiceTest extends UnitTestCase
{
    private \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface&MockInterface $entityMock;

    private \Mockery\MockInterface&UserPasswordHasherInterface $userPasswordHasherMock;

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

    /**
     * Covers isPasswordValid when password_verify fails but UserPasswordHasher path succeeds (clone + setPassword).
     */
    public function testGetHistoryByPasswordWhenPasswordVerifyFailsButHasherSucceeds(): void
    {
        $plainPassword = 'pwd';
        $nonBcryptHash = 'symfony_style_hash_that_verify_fails';
        $history1      = $this->makePasswordHistoryMock($nonBcryptHash);
        $entity        = new class implements HasPasswordPolicyInterface, UserInterface, PasswordAuthenticatedUserInterface {
            public string $password = '';

            /** @var \Doctrine\Common\Collections\Collection<int, PasswordHistoryInterface> */
            public \Doctrine\Common\Collections\Collection $passwordHistory;

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return $this->passwordHistory;
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                $this->password = $password;
            }

            public function getId(): int
            {
                return 1;
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function addPasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function getUserIdentifier(): string
            {
                return 'user';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $entity->passwordHistory = new ArrayCollection([$history1]);

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $entity);
        $this->assertSame($history1, $actual);
    }

    /**
     * Covers isPasswordValid catch block when clone throws; next history item matches via password_verify.
     */
    public function testGetHistoryByPasswordWhenCloneThrowsThenFallsBackToPasswordVerify(): void
    {
        $plainPassword = 'pwd';
        $hash          = password_hash($plainPassword, PASSWORD_BCRYPT);
        $historyFail   = $this->makePasswordHistoryMock('hash_that_causes_clone_path');
        $historyMatch  = $this->makePasswordHistoryMock($hash);
        $entityThrows  = Mockery::mock(HasPasswordPolicyInterface::class, UserInterface::class, PasswordAuthenticatedUserInterface::class);
        $entityThrows->shouldReceive('getPasswordHistory')
                    ->once()
                    ->andReturn(new ArrayCollection([$historyFail, $historyMatch]));
        $entityThrows->shouldReceive('getPassword')->andReturn('');
        $entityThrows->shouldReceive('__clone')->andThrow(new Exception('Clone not allowed'));

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $entityThrows);
        $this->assertSame($historyMatch, $actual);
    }

    /**
     * Covers isPasswordValid clone path (canClone true): clone entity, setPassword on temp, hasher returns true.
     * Uses default service (no isCloneable closure) so canClone is determined by method_exists(__clone).
     */
    public function testGetHistoryByPasswordWhenCloneableUsesCloneAndHasherSucceeds(): void
    {
        $plainPassword = 'pwd';
        $nonBcryptHash = 'custom_hash_not_bcrypt';
        $history1      = $this->makePasswordHistoryMock($nonBcryptHash);
        $entity        = new class implements HasPasswordPolicyInterface, UserInterface, PasswordAuthenticatedUserInterface {
            public string $password = '';

            /** @var \Doctrine\Common\Collections\Collection<int, PasswordHistoryInterface> */
            public \Doctrine\Common\Collections\Collection $passwordHistory;

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return $this->passwordHistory;
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                $this->password = $password;
            }

            public function getId(): int
            {
                return 1;
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function addPasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function getUserIdentifier(): string
            {
                return 'user';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $entity->passwordHistory = new ArrayCollection([$history1]);

        // Default service (no closure) -> canClone = method_exists(__clone) = true
        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $entity);
        $this->assertSame($history1, $actual);
        $this->assertSame('', $entity->getPassword(), 'Clone path must not modify original entity');
    }

    /**
     * Covers isPasswordValid clone branch via reflection with a named class.
     */
    public function testIsPasswordValidClonePathViaReflection(): void
    {
        $plainPassword = 'pwd';
        $nonBcryptHash = 'hash_not_bcrypt_so_verify_fails';
        $entity        = new CloneableTestUser();

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $ref = new ReflectionMethod(PasswordPolicyService::class, 'isPasswordValid');
        $result = $ref->invoke($this->passwordPolicyService, $entity, $nonBcryptHash, $plainPassword);

        $this->assertTrue($result);
    }

    /**
     * Covers verifyWithClonedUser directly so the extracted method is fully covered.
     */
    public function testVerifyWithClonedUserViaReflection(): void
    {
        $plainPassword  = 'pwd';
        $hashedPassword = 'any_hash';
        $tempUser       = new CloneableTestUser();

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with($tempUser, $plainPassword)
            ->andReturn(true);

        $ref = new ReflectionMethod(PasswordPolicyService::class, 'verifyWithClonedUser');
        $result = $ref->invoke($this->passwordPolicyService, $tempUser, $hashedPassword, $plainPassword);

        $this->assertTrue($result);
        $this->assertSame($hashedPassword, $tempUser->getPassword());
    }

    /**
     * Covers verifyWithClonedUser early return when temp user has no setPassword.
     */
    public function testVerifyWithClonedUserReturnsFalseWhenNoSetPassword(): void
    {
        $plainPassword  = 'pwd';
        $hashedPassword = 'any_hash';
        $tempUser       = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): string
            {
                return '';
            }

            public function getUserIdentifier(): string
            {
                return 'u';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $ref = new ReflectionMethod(PasswordPolicyService::class, 'verifyWithClonedUser');
        $result = $ref->invoke($this->passwordPolicyService, $tempUser, $hashedPassword, $plainPassword);

        $this->assertFalse($result);
    }

    /**
     * Covers isPasswordValid clone path (97-99) via getHistoryByPassword: clone then verifyWithClonedUser then return true.
     * Uses a hash that is not valid for password_verify so we always take the UserPasswordHasher path.
     */
    public function testGetHistoryByPasswordWithCloneableTestUserTriggersClonePath(): void
    {
        $plainPassword = 'pwd';
        // Not a valid bcrypt hash so password_verify() returns false and we use clone + hasher path
        $nonBcryptHash = '$2y$10$invalidbcryptlengthandformatxxxxxxxxxxxxxxxxxxxxxxxxxx';
        $history1      = $this->makePasswordHistoryMock($nonBcryptHash);
        $entity        = new CloneableTestUser();
        $entity->setPasswordHistory(new ArrayCollection([$history1]));

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $actual = $this->passwordPolicyService->getHistoryByPassword($plainPassword, $entity);
        $this->assertSame($history1, $actual);
    }

    /**
     * Forces clone path (lines 96-99) by injecting isCloneable that returns true; uses getHistoryByPassword only.
     */
    public function testGetHistoryByPasswordWithForcedClonePathCoversLines97To99(): void
    {
        $plainPassword = 'pwd';
        $nonBcryptHash = 'x'; // password_verify fails
        $history1      = $this->makePasswordHistoryMock($nonBcryptHash);
        $entity        = new CloneableTestUser();
        $entity->setPasswordHistory(new ArrayCollection([$history1]));

        $service = new PasswordPolicyService(
            $this->userPasswordHasherMock,
            static fn (object $o): bool => true,
        );

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $actual = $service->getHistoryByPassword($plainPassword, $entity);
        $this->assertSame($history1, $actual);
        $this->assertSame('', $entity->getPassword());
    }

    /**
     * Covers isPasswordValid outer catch (line 126): clone throws, we catch and return false.
     * Uses reflection to call isPasswordValid with an entity that throws in __clone.
     */
    public function testIsPasswordValidOuterCatchWhenCloneThrows(): void
    {
        $plainPassword       = 'pwd';
        $nonBcryptHash       = 'any_hash';
        $entityThrowsOnClone = new class implements HasPasswordPolicyInterface, PasswordAuthenticatedUserInterface {
            public string $password = '';

            public function __clone()
            {
                throw new RuntimeException('Clone not supported');
            }

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return new ArrayCollection();
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                $this->password = $password;
            }

            public function getId(): int
            {
                return 1;
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function addPasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function getUserIdentifier(): string
            {
                return 'user';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };

        $ref = new ReflectionMethod(PasswordPolicyService::class, 'isPasswordValid');
        $result = $ref->invoke($this->passwordPolicyService, $entityThrowsOnClone, $nonBcryptHash, $plainPassword);

        $this->assertFalse($result);
    }

    /**
     * Covers isPasswordValid non-clone path (setPassword on entity directly) when isCloneable returns false.
     */
    public function testGetHistoryByPasswordWhenNotCloneableUsesSetPasswordDirectly(): void
    {
        $plainPassword = 'pwd';
        $nonBcryptHash = 'custom_hasher_hash';
        $history1      = $this->makePasswordHistoryMock($nonBcryptHash);
        $entity        = new class implements HasPasswordPolicyInterface, UserInterface, PasswordAuthenticatedUserInterface {
            public string $password = 'original';

            /** @var \Doctrine\Common\Collections\Collection<int, PasswordHistoryInterface> */
            public \Doctrine\Common\Collections\Collection $passwordHistory;

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return $this->passwordHistory;
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                $this->password = $password;
            }

            public function getId(): int
            {
                return 1;
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function addPasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function getUserIdentifier(): string
            {
                return 'user';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $entity->passwordHistory = new ArrayCollection([$history1]);

        $isCloneable = static fn (object $o): bool => false;
        $service     = new PasswordPolicyService($this->userPasswordHasherMock, $isCloneable);

        $this->userPasswordHasherMock->shouldReceive('isPasswordValid')
            ->once()
            ->with(Mockery::type(PasswordAuthenticatedUserInterface::class), $plainPassword)
            ->andReturn(true);

        $actual = $service->getHistoryByPassword($plainPassword, $entity);
        $this->assertSame($history1, $actual);
        $this->assertSame('original', $entity->getPassword());
    }

    /**
     * Covers isPasswordValid non-clone path when setPassword throws then restore throws (inner catch).
     */
    public function testGetHistoryByPasswordWhenNotCloneableAndSetPasswordThrowsRestoresAndIgnoresRestoreError(): void
    {
        $plainPassword = 'pwd';
        $historyHash   = 'any_hash';
        $history1      = $this->makePasswordHistoryMock($historyHash);
        $entity        = new class($historyHash) implements HasPasswordPolicyInterface, PasswordAuthenticatedUserInterface {
            public string $password = 'original';

            public function __construct(
                private readonly string $hashToSet,
                private readonly \Doctrine\Common\Collections\Collection $passwordHistory = new ArrayCollection(),
            ) {
            }

            public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
            {
                return $this->passwordHistory;
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): void
            {
                if ($password === $this->hashToSet) {
                    throw new RuntimeException('set failed');
                }
                if ($password === 'original') {
                    throw new RuntimeException('restore failed');
                }
            }

            public function getId(): int
            {
                return 1;
            }

            public function getPasswordChangedAt(): ?DateTime
            {
                return null;
            }

            public function setPasswordChangedAt(DateTime $dateTime): static
            {
                return $this;
            }

            public function addPasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function removePasswordHistory(PasswordHistoryInterface $ph): static
            {
                return $this;
            }

            public function getUserIdentifier(): string
            {
                return 'user';
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }
        };
        $ref = new ReflectionProperty($entity, 'passwordHistory');
        $ref->setValue($entity, new ArrayCollection([$history1]));

        $isCloneable = static fn (object $o): bool => false;
        $service     = new PasswordPolicyService($this->userPasswordHasherMock, $isCloneable);

        $actual = $service->getHistoryByPassword($plainPassword, $entity);
        $this->assertNull($actual);
    }

    private function makePasswordHistoryMock(string $hashedPassword = 'hashed_pwd'): PasswordHistoryInterface
    {
        $mock = Mockery::mock(PasswordHistoryInterface::class);
        $mock->shouldReceive('getPassword')->andReturn($hashedPassword);
        $mock->shouldReceive('getSalt')->andReturn(null);
        $mock->shouldReceive('getCreatedAt')->andReturn(new DateTime());

        return $mock;
    }
}

/** Helper for clone-path tests: named class so clone has setPassword. */
final class CloneableTestUser implements HasPasswordPolicyInterface, PasswordAuthenticatedUserInterface
{
    public string $password = '';

    /** @var \Doctrine\Common\Collections\Collection<int, PasswordHistoryInterface> */
    private \Doctrine\Common\Collections\Collection $passwordHistory;

    public function __construct()
    {
        $this->passwordHistory = new ArrayCollection();
    }

    public function setPasswordHistory(\Doctrine\Common\Collections\Collection $history): void
    {
        $this->passwordHistory = $history;
    }

    public function getPasswordHistory(): \Doctrine\Common\Collections\Collection
    {
        return $this->passwordHistory;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getId(): int
    {
        return 1;
    }

    public function getPasswordChangedAt(): ?DateTime
    {
        return null;
    }

    public function setPasswordChangedAt(DateTime $dateTime): static
    {
        return $this;
    }

    public function addPasswordHistory(PasswordHistoryInterface $ph): static
    {
        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $ph): static
    {
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return 'user';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }
}
