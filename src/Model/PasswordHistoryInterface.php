<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTime;
use DateTimeInterface;

/**
 * Interface for password history entities.
 *
 * Entities implementing this interface represent a single entry in the password
 * history, storing a previously used password and when it was created.
 */
interface PasswordHistoryInterface
{

  /**
   * Gets the hashed password.
   *
   * @return string The hashed password
   */
  public function getPassword(): string;

  /**
   * Sets the hashed password.
   *
   * @param string $password The hashed password
   * @return self
   */
  public function setPassword(string $password): self;

  /**
   * Gets the creation date and time of this password history entry.
   *
   * @return DateTimeInterface|null The creation date or null if not set
   */
  public function getCreatedAt(): ?DateTimeInterface;

  /**
   * Sets the creation date and time of this password history entry.
   *
   * @param DateTimeInterface $createdAt The creation date
   * @return self
   */
  public function setCreatedAt(DateTimeInterface $createdAt): self;
}
