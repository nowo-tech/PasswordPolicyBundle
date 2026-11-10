<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;

/**
 * Checks a new plain password of a read-only subject against its history (through the history port), its current
 * hash and, optionally, extensions of old passwords. Used by the validator for subjects that are not
 * HasPasswordPolicyInterface entities; call it from your own use case too.
 */
interface PasswordReuseCheckerInterface
{
    /**
     * @param bool|null $detectExtensions null uses the class's detect_password_extensions setting
     * @param int|null $extensionMinLength null uses the class's extension_min_length setting
     */
    public function findReuse(
        string $plainPassword,
        PasswordPolicySubjectInterface $subject,
        bool $checkCurrentPassword = true,
        ?bool $detectExtensions = null,
        ?int $extensionMinLength = null,
    ): ?PasswordReuseMatch;
}
