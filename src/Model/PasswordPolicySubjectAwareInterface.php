<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

/**
 * Implement it on a command/DTO that carries `#[PasswordPolicy]`: the default subject resolver asks it for the
 * subject whose history and current password the new password is checked against.
 */
interface PasswordPolicySubjectAwareInterface
{
    /**
     * The subject, a {@see HasPasswordPolicyInterface} entity (Doctrine path), or null when unknown.
     */
    public function getPasswordPolicySubject(): HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null;
}
