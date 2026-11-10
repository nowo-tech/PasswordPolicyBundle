<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;

/**
 * Port: finds the subject of a `#[PasswordPolicy]` placed on an object that is not a HasPasswordPolicyInterface
 * entity (a command, a form DTO…).
 *
 * The default, {@see DefaultPasswordPolicySubjectResolver}, handles objects that are a PasswordPolicySubjectInterface
 * or a PasswordPolicySubjectAwareInterface. Alias this interface to your own resolver to look the subject up by id.
 */
interface PasswordPolicySubjectResolverInterface
{
    /**
     * Returns a HasPasswordPolicyInterface entity (checked like the entity itself) or a read-only subject, or null
     * when $validatedObject is not supported (the validator then throws a ValidationException).
     */
    public function resolve(object $validatedObject): HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null;
}
