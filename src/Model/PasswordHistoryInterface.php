<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTime;
use DateTimeInterface;

interface PasswordHistoryInterface
{

  public function getPassword(): string;

  public function setPassword(string $password): self;

  /**
   * @return DateTime
   */
  public function getCreatedAt(): ?DateTimeInterface;

  /**
   * @param DateTime $dateTime
   * @return DateTime|null
   */
  public function setCreatedAt(DateTimeInterface $createdAt): self;
}
