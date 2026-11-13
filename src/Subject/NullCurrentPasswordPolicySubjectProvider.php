<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;

/**
 * Default {@see CurrentPasswordPolicySubjectProviderInterface}: no subject, so only `HasPasswordPolicyInterface`
 * security users are checked for expiry (the behaviour before 1.12.0).
 */
final class NullCurrentPasswordPolicySubjectProvider implements CurrentPasswordPolicySubjectProviderInterface
{
    public function getCurrentSubject(): ?PasswordPolicySubjectInterface
    {
        return null;
    }
}
