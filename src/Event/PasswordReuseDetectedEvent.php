<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The PasswordPolicy validator rejected a reused password; carries scalar ids only.
 *
 * Dispatched for a read-only subject (a command/DTO validated through the subject resolver) and, since 1.13.0, for an
 * entity too, right after PasswordReuseAttemptedEvent (whose entity accessors are deprecated in its favour).
 */
final class PasswordReuseDetectedEvent extends Event
{
    /**
     * @param PasswordReuseMatch::TYPE_* $matchType
     */
    public function __construct(
        private readonly string $subjectClass,
        private readonly string $subjectId,
        private readonly string $matchType,
        private readonly ?DateTimeImmutable $usedAt = null,
    ) {
    }

    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /**
     * @return PasswordReuseMatch::TYPE_*
     */
    public function getMatchType(): string
    {
        return $this->matchType;
    }

    /**
     * When the matching password was used (history entry date, or passwordChangedAt for the current password).
     */
    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }
}
