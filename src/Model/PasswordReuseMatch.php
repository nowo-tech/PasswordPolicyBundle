<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTimeImmutable;

/**
 * Result of a reuse check on a {@see PasswordPolicySubjectInterface}: how the new password matched and when the
 * matching password was used.
 */
final readonly class PasswordReuseMatch
{
    /** The password is in the history. */
    public const TYPE_EXACT = 'exact';

    /** The password is the current one. */
    public const TYPE_CURRENT = 'current';

    /** The password extends one of the history (e.g. "password1" after "password"). */
    public const TYPE_EXTENSION = 'extension';

    /**
     * @param self::TYPE_* $type
     */
    public function __construct(
        public string $type,
        public ?DateTimeImmutable $usedAt = null,
    ) {
    }
}
