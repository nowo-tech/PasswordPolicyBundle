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
        if ($hasPasswordPolicy instanceof UserInterface) {
            try {
                // Use UserPasswordHasherInterface to verify the password
                // We need to create a temporary user with the hashed password to verify
                // Check if object is clonable by checking if __clone method exists or if it's not final
                if (!method_exists($hasPasswordPolicy, '__clone')) {
                    // If object is not clonable, try using password_verify as fallback
                    if (function_exists('password_verify')) {
                        return password_verify($plainPassword, $hashedPassword);
                    }

                    return false;
                }

                $tempUser = clone $hasPasswordPolicy;
                if (method_exists($tempUser, 'setPassword')) {
                    $tempUser->setPassword($hashedPassword);

                    return $this->userPasswordHasher->isPasswordValid($tempUser, $plainPassword);
                }
            } catch (\Exception $e) {
                // If cloning fails, try password_verify as fallback
                if (function_exists('password_verify')) {
                    return password_verify($plainPassword, $hashedPassword);
                }

                return false;
            }
        }

        // Fallback: use password_verify if available (never compare directly)
        if (function_exists('password_verify')) {
            return password_verify($plainPassword, $hashedPassword);
        }

        // Last resort: return false (never compare hashes directly)
        return false;
    }
}
