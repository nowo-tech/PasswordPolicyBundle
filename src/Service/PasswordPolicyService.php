<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class PasswordPolicyService implements PasswordPolicyServiceInterface
{
    /**
     * PasswordPolicyEnforcerService constructor.
     *
     * @param UserPasswordHasherInterface $userPasswordHasher
     */
    public function __construct(public UserPasswordHasherInterface $userPasswordHasher)
    {
    }

    /**
     * @param HasPasswordPolicyInterface $entity
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
            // Use UserPasswordHasherInterface to verify the password
            // We need to create a temporary user with the hashed password to verify
            $tempUser = clone $hasPasswordPolicy;
            if (method_exists($tempUser, 'setPassword')) {
                $tempUser->setPassword($hashedPassword);
            }

            // Verify using isPasswordValid method
            return $this->userPasswordHasher->isPasswordValid($tempUser, $plainPassword);
        }

        // Fallback: simple comparison if not a UserInterface
        return $hashedPassword === $plainPassword;
    }
}
