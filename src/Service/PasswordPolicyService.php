<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service for enforcing password policies.
 *
 * This service checks if a password has been used before by comparing it against
 * the password history of an entity.
 */
class PasswordPolicyService implements PasswordPolicyServiceInterface
{
    /**
     * PasswordPolicyService constructor.
     *
     * @param UserPasswordHasherInterface $userPasswordHasher The password hasher service for verifying passwords
     */
    public function __construct(public UserPasswordHasherInterface $userPasswordHasher)
    {
    }

    /**
     * Gets a password history entry that matches the given plain password.
     *
     * @param string                     $password          The plain password to check
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
            if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $password, $passwordHistory->getSalt())) {
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
     *
     * @param HasPasswordPolicyInterface $hasPasswordPolicy
     * @param string                     $hashedPassword
     * @param string                     $plainPassword
     * @param string|null                $salt
     *
     * @return bool
     */
    private function isPasswordValid(
        HasPasswordPolicyInterface $hasPasswordPolicy,
        string $hashedPassword,
        string $plainPassword,
        ?string $salt
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
        if ($hasPasswordPolicy instanceof UserInterface) {
            try {
                // Try to clone the entity to avoid modifying the original
                // If cloning is not possible, we'll skip this method
                if (method_exists($hasPasswordPolicy, '__clone')) {
                    $tempUser = clone $hasPasswordPolicy;
                    if (method_exists($tempUser, 'setPassword')) {
                        // Set the hashed password from history on the temp user
                        $tempUser->setPassword($hashedPassword);
                        // Verify if the plain password matches the hashed password
                        if ($this->userPasswordHasher->isPasswordValid($tempUser, $plainPassword)) {
                            return true;
                        }
                    }
                } else {
                    // If not cloneable, try to use the entity directly (but restore password after)
                    // This is a fallback for entities that don't support cloning
                    if (method_exists($hasPasswordPolicy, 'getPassword') && method_exists($hasPasswordPolicy, 'setPassword')) {
                        $originalPassword = $hasPasswordPolicy->getPassword();

                        try {
                            $hasPasswordPolicy->setPassword($hashedPassword);
                            $result = $this->userPasswordHasher->isPasswordValid($hasPasswordPolicy, $plainPassword);
                            // Restore original password
                            $hasPasswordPolicy->setPassword($originalPassword);
                            if ($result) {
                                return true;
                            }
                        } catch (\Exception $e) {
                            // If anything fails, restore original password
                            try {
                                $hasPasswordPolicy->setPassword($originalPassword);
                            } catch (\Exception $restoreException) {
                                // Ignore restore errors
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // If cloning or verification fails, continue to return false
                // This is expected for some hash formats
            }
        }

        // If all methods failed, the password doesn't match
        // We never compare hashes directly for security reasons
        return false;
    }

    /**
     * Checks if the given password is an extension of any password in history.
     *
     * This method attempts to detect if a new password is an extension of an old password
     * by trying common extension patterns (adding numbers, characters, etc. to the end or beginning).
     * Since passwords in history are hashed, we use a heuristic approach:
     * - Try common suffixes (numbers 0-999, common characters)
     * - Try common prefixes
     * - Check if removing common patterns from the new password matches an old password
     *
     * @param string                     $password          The plain password to check
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     * @param int                        $minLength         Minimum length of old password to consider
     *
     * @return PasswordHistoryInterface|null The matching password history entry or null if not found
     */
    public function getHistoryByPasswordExtension(
        string $password,
        HasPasswordPolicyInterface $hasPasswordPolicy,
        int $minLength = 4
    ): ?PasswordHistoryInterface {
        $collection = $hasPasswordPolicy->getPasswordHistory();

        // Common extension patterns to try
        $commonSuffixes = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '!', '@', '#', '$', '%'];
        $commonPrefixes = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '!', '@', '#', '$', '%'];

        // Try removing common suffixes from the new password and check if it matches an old password
        foreach ($commonSuffixes as $suffix) {
            if (str_ends_with($password, $suffix)) {
                $basePassword = substr($password, 0, -strlen($suffix));
                if (strlen($basePassword) >= $minLength) {
                    foreach ($collection as $passwordHistory) {
                        if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $basePassword, $passwordHistory->getSalt())) {
                            return $passwordHistory;
                        }
                    }
                }
            }
        }

        // Try removing common prefixes from the new password and check if it matches an old password
        foreach ($commonPrefixes as $prefix) {
            if (str_starts_with($password, $prefix)) {
                $basePassword = substr($password, strlen($prefix));
                if (strlen($basePassword) >= $minLength) {
                    foreach ($collection as $passwordHistory) {
                        if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $basePassword, $passwordHistory->getSalt())) {
                            return $passwordHistory;
                        }
                    }
                }
            }
        }

        // Try removing numeric suffixes (0-999)
        for ($i = 0; $i <= 999; ++$i) {
            $suffix = (string) $i;
            if (str_ends_with($password, $suffix)) {
                $basePassword = substr($password, 0, -strlen($suffix));
                if (strlen($basePassword) >= $minLength) {
                    foreach ($collection as $passwordHistory) {
                        if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $basePassword, $passwordHistory->getSalt())) {
                            return $passwordHistory;
                        }
                    }
                }
            }
        }

        // Try removing numeric prefixes (0-999)
        for ($i = 0; $i <= 999; ++$i) {
            $prefix = (string) $i;
            if (str_starts_with($password, $prefix)) {
                $basePassword = substr($password, strlen($prefix));
                if (strlen($basePassword) >= $minLength) {
                    foreach ($collection as $passwordHistory) {
                        if ($this->isPasswordValid($hasPasswordPolicy, $passwordHistory->getPassword(), $basePassword, $passwordHistory->getSalt())) {
                            return $passwordHistory;
                        }
                    }
                }
            }
        }

        return null;
    }
}
