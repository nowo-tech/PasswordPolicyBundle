<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Exceptions\ConfigurationException;

/**
 * Application service the host calls from its "change password" use case when the Doctrine onFlush listener does not
 * record the history (history_listener: false, or a subject that is not a Doctrine entity).
 *
 * It only records the history: it does not stamp passwordChangedAt (your aggregate owns it) and does not flush.
 * Without a record() call per change, the reuse check only sees the current password.
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
     * @throws ConfigurationException when no nowo_password_policy.entities key
     *                                applies to $subjectClass
     *
     * @return int the number of history entries removed by the pruning
     */
    public function record(string $subjectClass, string $subjectId, string $previousHash, ?DateTimeInterface $changedAt = null): int;
}
