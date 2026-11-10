<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

/**
 * Port: verifies a plain password against a stored hash of a subject, without touching any entity.
 */
interface PasswordHashVerifierInterface
{
    /**
     * @param string $subjectClass a key of nowo_password_policy.entities (selects the Symfony password hasher)
     */
    public function verify(string $subjectClass, string $passwordHash, string $plainPassword): bool;
}
