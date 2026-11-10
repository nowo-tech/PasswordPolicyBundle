<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectAwareInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;

/**
 * Default {@see PasswordPolicySubjectResolverInterface}: the validated object itself when it is a subject, or the
 * subject it exposes through {@see PasswordPolicySubjectAwareInterface}.
 */
final class DefaultPasswordPolicySubjectResolver implements PasswordPolicySubjectResolverInterface
{
    public function resolve(object $validatedObject): HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null
    {
        if ($validatedObject instanceof HasPasswordPolicyInterface || $validatedObject instanceof PasswordPolicySubjectInterface) {
            return $validatedObject;
        }

        if ($validatedObject instanceof PasswordPolicySubjectAwareInterface) {
            return $validatedObject->getPasswordPolicySubject();
        }

        return null;
    }
}
