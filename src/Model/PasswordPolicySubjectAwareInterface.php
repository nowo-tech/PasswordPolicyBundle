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
     * The subject: a read-only subject, or a {@see HasPasswordPolicyInterface} entity (checked like the entity itself).
     *
     * Build it from the authenticated user or a server-side lookup, never from request data. Null is an error: the
     * validator fails closed and throws a ValidationException.
     */
    public function getPasswordPolicySubject(): HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null;
}
