<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The PasswordPolicy validator rejected a reused password of a read-only subject (a command/DTO validated through
 * the subject resolver); carries scalar ids only. Entity validations keep dispatching PasswordReuseAttemptedEvent.
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
