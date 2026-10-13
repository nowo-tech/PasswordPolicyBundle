<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;

/**
 * Optional capability of a password policy service: tell whether a plain password matches the
 * entity's current (still active) password hash.
 *
 * The current password only enters the history on the next change, so without this check a user
 * could "change" their password to the one they already have. Kept separate from
 * {@see PasswordPolicyServiceInterface} so custom implementations of that interface keep working.
 */
interface CurrentPasswordMatcherInterface
{
    /**
     * Returns true when $password matches the hash returned by $hasPasswordPolicy->getPassword().
     */
    public function matchesCurrentPassword(string $password, HasPasswordPolicyInterface $hasPasswordPolicy): bool;
}
