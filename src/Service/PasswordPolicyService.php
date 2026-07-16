<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Closure;
use Exception;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function ctype_digit;
use function function_exists;
use function strlen;

/**
 * Service for enforcing password policies.
 *
 * This service checks if a password has been used before by comparing it against
 * the password history of an entity.
 */
class PasswordPolicyService implements PasswordPolicyServiceInterface
{
    /**
     * Single-character extensions checked when extension detection is enabled.
     *
     * @var list<string>
     */
    private const EXTENSION_CHARS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '!', '@', '#', '$', '%'];

    /**
     * Maximum length of a numeric prefix/suffix candidate (matches 0–999 semantics).
     */
    private const MAX_NUMERIC_EXTENSION_LENGTH = 3;

    /**
     * Optional closure to determine if an entity can be cloned. Signature: (object): bool.
     * If null, defaults to method_exists($entity, '__clone').
     * Used for testability of the non-clone fallback path.
     *
     * @param UserPasswordHasherInterface $userPasswordHasher The password hasher service for verifying passwords
     * @param Closure(object): bool|null $isCloneable Optional; when null, cloneability is determined via method_exists
     */
    public function __construct(
        public UserPasswordHasherInterface $userPasswordHasher,
        private readonly ?Closure $isCloneable = null
    ) {
    }

    /**
     * Gets a password history entry that matches the given plain password.
     *
     * @param string $password The plain password to check
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     *
     * @return PasswordHistoryInterface|null The matching password history entry or null if not found
     */
    public function getHistoryByPassword(
        string $password,
        HasPasswordPolicyInterface $hasPasswordPolicy
    ): ?PasswordHistoryInterface {
        $collection = $hasPasswordPolicy->getPasswordHistory();

        foreach ($collection as $passwordHistory) {
            if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $password)) {
                return $passwordHistory;
            }
        }

        return null;
    }

    /**
     * Check if a password matches a hashed password.
     *
     * This method tries multiple approaches to verify the password:
     * 1. First tries password_verify() for any hash format (most reliable)
     * 2. Then tries UserPasswordHasherInterface which handles Symfony's password hashers
     * 3. Never compares hashes directly (security requirement)
     */
    private function isPasswordValid(
        HasPasswordPolicyInterface $hasPasswordPolicy,
        string $hashedPassword,
        string $plainPassword
    ): bool {
        // First, try password_verify() - this is the most reliable method
        // It works with bcrypt, argon2, and any hash generated with password_hash()
        // It's also safe to call on any hash format - it will return false if it doesn't match
        if (function_exists('password_verify')) {
            $result = password_verify($plainPassword, $hashedPassword);
            if ($result) {
                return true;
            }
        }

        // Try UserPasswordHasherInterface if the entity implements UserInterface
        // This handles Symfony's password hashers (NativePasswordHasher, etc.)
        // This is important for Symfony's custom hashers that might not use password_hash()
        if ($hasPasswordPolicy instanceof PasswordAuthenticatedUserInterface) {
            try {
                $canClone = $this->isCloneable instanceof Closure
                    ? ($this->isCloneable)($hasPasswordPolicy)
                    : method_exists($hasPasswordPolicy, '__clone');
                // Try to clone the entity to avoid modifying the original
                // If cloning is not possible, we'll skip this method
                if ($canClone) {
                    $tempUser = clone $hasPasswordPolicy;
                    $valid    = $this->verifyWithClonedUser($tempUser, $hashedPassword, $plainPassword);
                    if ($valid) {
                        return true;
                    }
                } elseif (method_exists($hasPasswordPolicy, 'setPassword')) {
                    // If not cloneable, try to use the entity directly (but restore password after)
                    // This is a fallback for entities that don't support cloning
                    $originalPassword = $hasPasswordPolicy->getPassword();
                    try {
                        $hasPasswordPolicy->setPassword($hashedPassword);
                        $result = $this->userPasswordHasher->isPasswordValid($hasPasswordPolicy, $plainPassword);
                        // Restore original password
                        $hasPasswordPolicy->setPassword($originalPassword);
                        if ($result) {
                            return true;
                        }
                    } catch (Exception) {
                        // If anything fails, restore original password
                        try {
                            $hasPasswordPolicy->setPassword($originalPassword);
                        } catch (Exception) {
                            // Ignore restore errors
                        }
                    }
                }
            } catch (Exception) {
                // If cloning or verification fails, continue to return false
                // This is expected for some hash formats
            }
        }

        // If all methods failed, the password doesn't match
        // We never compare hashes directly for security reasons
        return false;
    }

    /**
     * Verifies plain password against hashed password using a cloned user (has setPassword).
     * Extracted for testability and reliable coverage of the clone path.
     */
    private function verifyWithClonedUser(
        PasswordAuthenticatedUserInterface $tempUser,
        string $hashedPassword,
        string $plainPassword
    ): bool {
        if (!method_exists($tempUser, 'setPassword')) {
            return false;
        }
        $tempUser->setPassword($hashedPassword);

        return $this->userPasswordHasher->isPasswordValid($tempUser, $plainPassword);
    }

    /**
     * Checks if the given password is an extension of any password in history.
     *
     * Attempts to detect if a new password is an extension of an old password by stripping
     * allowed prefix/suffix patterns and verifying the remaining base against history hashes.
     * Candidate bases are deduplicated so each hash is checked at most once per base string.
     *
     * @param string $password The plain password to check
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     * @param int $minLength Minimum length of old password to consider
     *
     * @return PasswordHistoryInterface|null The matching password history entry or null if not found
     */
    public function getHistoryByPasswordExtension(
        string $password,
        HasPasswordPolicyInterface $hasPasswordPolicy,
        int $minLength = 4
    ): ?PasswordHistoryInterface {
        $candidates = $this->collectExtensionBaseCandidates($password, $minLength);
        if ($candidates === []) {
            return null;
        }

        $collection = $hasPasswordPolicy->getPasswordHistory();

        foreach ($candidates as $basePassword) {
            foreach ($collection as $passwordHistory) {
                if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $basePassword)) {
                    return $passwordHistory;
                }
            }
        }

        return null;
    }

    /**
     * Builds deduplicated base-password candidates by stripping allowed extensions.
     *
     * Preserves previous semantics (single special/digit chars and numeric prefixes/suffixes 0–999)
     * without re-verifying the same base password or scanning 0–999 when no digits are present.
     *
     * @return list<string>
     */
    private function collectExtensionBaseCandidates(string $password, int $minLength): array
    {
        /** @var array<string, true> $candidates */
        $candidates = [];
        $length     = strlen($password);

        if ($length <= $minLength) {
            return [];
        }

        foreach (self::EXTENSION_CHARS as $char) {
            if (str_ends_with($password, $char)) {
                $this->addExtensionBaseCandidate($candidates, substr($password, 0, -1), $minLength);
            }
            if (str_starts_with($password, $char)) {
                $this->addExtensionBaseCandidate($candidates, substr($password, 1), $minLength);
            }
        }

        $this->addNumericExtensionCandidates($candidates, $password, $minLength, suffix: true);
        $this->addNumericExtensionCandidates($candidates, $password, $minLength, suffix: false);

        return array_keys($candidates);
    }

    /**
     * @param array<string, true> $candidates
     */
    private function addExtensionBaseCandidate(array &$candidates, string $basePassword, int $minLength): void
    {
        if (strlen($basePassword) >= $minLength) {
            $candidates[$basePassword] = true;
        }
    }

    /**
     * Adds bases obtained by removing numeric prefixes or suffixes up to three digits (0–999).
     *
     * @param array<string, true> $candidates
     */
    private function addNumericExtensionCandidates(
        array &$candidates,
        string $password,
        int $minLength,
        bool $suffix
    ): void {
        $length = strlen($password);

        for ($size = 1; $size <= self::MAX_NUMERIC_EXTENSION_LENGTH && $size < $length; ++$size) {
            $part = $suffix ? substr($password, -$size) : substr($password, 0, $size);
            if (!ctype_digit($part)) {
                continue;
            }

            $basePassword = $suffix ? substr($password, 0, -$size) : substr($password, $size);
            $this->addExtensionBaseCandidate($candidates, $basePassword, $minLength);
        }
    }
}
