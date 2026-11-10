<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;

/**
 * Application service the host calls from its "change password" use case when the Doctrine onFlush listener does not
 * record the history (history_listener: false, or a subject that is not a Doctrine entity).
 */
interface PasswordHistoryRecorderInterface
{
    /**
     * Records $previousHash in the subject's history, keeps the newest passwords_to_remember entries and dispatches
     * {@see PasswordChangeRecordedEvent}.
     *
     * @param string $subjectClass a key of nowo_password_policy.entities
     * @param string $previousHash the hash that was replaced (an empty hash records nothing)
     * @param DateTimeInterface|null $changedAt when the password changed (default: now)
     *
     * @return int the number of history entries removed by the pruning
     */
    public function record(string $subjectClass, string $subjectId, string $previousHash, ?DateTimeInterface $changedAt = null): int;
}
