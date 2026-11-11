<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;

/**
 * A host history adapter that cannot erase (no ErasablePasswordHistoryRepositoryInterface).
 */
final class NonErasablePasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
    public function findRecent(string $subjectClass, string $subjectId, int $limit): array
    {
        return [];
    }

    public function add(string $subjectClass, string $subjectId, string $passwordHash, DateTimeImmutable $createdAt): void
    {
    }

    public function prune(string $subjectClass, string $subjectId, int $keep): int
    {
        return 0;
    }
}
