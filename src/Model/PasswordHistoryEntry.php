<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;

/**
 * Immutable password history entry returned by {@see PasswordHistoryRepositoryInterface}.
 */
final readonly class PasswordHistoryEntry
{
    public ?DateTimeImmutable $createdAt;

    public function __construct(
        public string $passwordHash,
        ?DateTimeInterface $createdAt = null,
    ) {
        $this->createdAt = $createdAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($createdAt) : null;
    }
}
