<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Kernel;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;

use function array_slice;
use function count;
use function max;
use function usort;

/**
 * Host adapter without Doctrine: the history lives in memory, keyed by subject class and id.
 */
final class InMemoryPasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
    /** @var array<string, list<PasswordHistoryEntry>> */
    private array $entries = [];

    public function findRecent(string $subjectClass, string $subjectId, int $limit): array
    {
        return array_slice($this->sorted($subjectClass, $subjectId), 0, max(0, $limit));
    }

    public function add(string $subjectClass, string $subjectId, string $passwordHash, DateTimeImmutable $createdAt): void
    {
        $this->entries[$subjectClass . '#' . $subjectId][] = new PasswordHistoryEntry($passwordHash, $createdAt);
    }

    public function prune(string $subjectClass, string $subjectId, int $keep): int
    {
        $sorted                                          = $this->sorted($subjectClass, $subjectId);
        $this->entries[$subjectClass . '#' . $subjectId] = array_slice($sorted, 0, max(0, $keep));

        return count($sorted) - count($this->entries[$subjectClass . '#' . $subjectId]);
    }

    /**
     * @return list<PasswordHistoryEntry>
     */
    private function sorted(string $subjectClass, string $subjectId): array
    {
        $entries = $this->entries[$subjectClass . '#' . $subjectId] ?? [];
        usort($entries, static fn (PasswordHistoryEntry $a, PasswordHistoryEntry $b): int => $b->createdAt <=> $a->createdAt);

        return $entries;
    }
}
