<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;

/**
 * Port: where the password history of a subject lives.
 *
 * The default adapter, {@see DoctrinePasswordHistoryRepository}, works on the Doctrine association of a
 * HasPasswordPolicyInterface entity (the same data the onFlush listener writes). Alias this interface to your own
 * adapter to keep the history in your aggregate, a DBAL table, an API…
 */
interface PasswordHistoryRepositoryInterface
{
    /**
     * The newest entries of the subject, newest first, at most $limit.
     *
     * @return list<PasswordHistoryEntry>
     */
    public function findRecent(string $subjectClass, string $subjectId, int $limit): array;

    /**
     * Stores a previous password hash of the subject.
     */
    public function add(string $subjectClass, string $subjectId, string $passwordHash, DateTimeImmutable $createdAt): void;

    /**
     * Removes all but the newest $keep entries of the subject and returns how many were removed.
     */
    public function prune(string $subjectClass, string $subjectId, int $keep): int;
}
