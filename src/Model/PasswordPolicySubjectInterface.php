<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;

/**
 * Read-only view of a password policy subject (a user, an account…) for checks that must not depend on the Doctrine
 * model: no Collection, no setters, no history methods.
 *
 * Implement it on a domain object or a DTO, or build a {@see PasswordPolicySubject}. The password history is read
 * through {@see PasswordHistoryRepositoryInterface}. Entities that implement
 * {@see HasPasswordPolicyInterface} (the Doctrine adapter variant) keep working unchanged.
 */
interface PasswordPolicySubjectInterface
{
    /**
     * The class whose settings apply: a key of nowo_password_policy.entities.
     */
    public function getPasswordPolicySubjectClass(): string;

    /**
     * The subject identifier, as a string.
     */
    public function getPasswordPolicySubjectId(): string;

    /**
     * The current password hash, or null when the subject has none (e.g. it is being registered).
     */
    public function getPasswordHash(): ?string;

    /**
     * When the current password was set, or null when unknown.
     */
    public function getPasswordChangedAt(): ?DateTimeInterface;
}
