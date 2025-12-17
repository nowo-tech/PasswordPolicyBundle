<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTime;
use Doctrine\Common\Collections\Collection;

/**
 * Interface for entities that support password policy management.
 *
 * Entities implementing this interface can track password history and
 * enforce password expiry policies.
 */
interface HasPasswordPolicyInterface
{
    /**
     * Gets the entity identifier.
     *
     * @return mixed The entity ID
     */
    public function getId();

    /**
     * Gets the date and time when the password was last changed.
     *
     * @return DateTime|null The password change date or null if never changed
     */
    public function getPasswordChangedAt(): ?DateTime;

    /**
     * Sets the date and time when the password was last changed.
     *
     * @param DateTime $dateTime The password change date
     *
     * @return self
     */
    public function setPasswordChangedAt(DateTime $dateTime): self;

    /**
     * Gets the password history collection.
     *
     * @return Collection The collection of password history entries
     */
    public function getPasswordHistory(): Collection;

    /**
     * Adds a password history entry.
     *
     * @param PasswordHistoryInterface $passwordHistory The password history entry to add
     *
     * @return static
     */
    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static;

    /**
     * Gets the current hashed password.
     *
     * @return string The hashed password
     */
    public function getPassword(): string;
}
