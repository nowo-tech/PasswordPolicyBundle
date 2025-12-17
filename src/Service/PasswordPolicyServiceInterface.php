<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

/**
 * Interface for password policy service.
 *
 * This service enforces password policies by checking if passwords have
 * been used before.
 */
interface PasswordPolicyServiceInterface
{
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
    ): ?PasswordHistoryInterface;

    /**
     * Checks if the given password is an extension of any password in history.
     *
     * An extension is when the new password contains an old password as a prefix or suffix,
     * or when an old password is contained within the new password.
     * For example: "password123" is an extension of "password".
     *
     * @param string                     $password          The plain password to check
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     * @param int                        $minLength         Minimum length of old password to consider for extension detection
     *
     * @return PasswordHistoryInterface|null The matching password history entry or null if not found
     */
    public function getHistoryByPasswordExtension(
        string $password,
        HasPasswordPolicyInterface $hasPasswordPolicy,
        int $minLength = 4
    ): ?PasswordHistoryInterface;
}
