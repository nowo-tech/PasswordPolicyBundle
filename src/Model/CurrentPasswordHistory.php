<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;

/**
 * Transient (never persisted) history entry that represents the entity's current password.
 *
 * Used by the PasswordPolicy validator when the new password equals the current one, so the
 * violation, logging and {@see PasswordReuseAttemptedEvent}
 * work the same way as for a real history match. createdAt is the entity's passwordChangedAt.
 */
final class CurrentPasswordHistory implements PasswordHistoryInterface
{
    public function __construct(
        private string $password,
        private ?DateTimeInterface $createdAt = null,
    ) {
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
