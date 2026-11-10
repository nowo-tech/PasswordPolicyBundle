<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRecorderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A password change was recorded in the history; carries scalar ids only (no entity).
 *
 * Dispatched by the Doctrine listener on postFlush (after the flush transaction committed) and by
 * {@see PasswordHistoryRecorderInterface::record()} when it returns.
 */
final class PasswordChangeRecordedEvent extends Event
{
    public function __construct(
        private readonly string $subjectClass,
        private readonly string $subjectId,
        private readonly DateTimeImmutable $changedAt,
        private readonly int $removedEntriesCount = 0,
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

    public function getChangedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getRemovedEntriesCount(): int
    {
        return $this->removedEntriesCount;
    }
}
