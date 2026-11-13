<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Subject\CurrentPasswordPolicySubjectProviderInterface;

/**
 * Password expiry for a read-only subject (since 1.12.0, REQ-ARCH-001 rules 1 and 4): no entity, no security token. Call
 * it from your use case, or let the expiry listener use it for the subject your
 * {@see CurrentPasswordPolicySubjectProviderInterface} returns.
 *
 * The default, {@see PasswordExpiryService}, applies the same rules as the entity check: the settings of the configured
 * class the subject class is (or extends / implements), `expiry_days`, `expire_when_never_changed`, a change date in
 * the future never expires, and the optional cache.
 */
interface PasswordExpiryCheckerInterface
{
    /**
     * Whether the subject's password has expired. False when no configured class applies to the subject.
     */
    public function isSubjectPasswordExpired(PasswordPolicySubjectInterface $subject): bool;

    /**
     * Drops the cached expiry status of the subject (call it after you changed its password yourself).
     */
    public function invalidateSubjectCache(PasswordPolicySubjectInterface $subject): void;
}
