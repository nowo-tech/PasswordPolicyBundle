<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTime;
use Doctrine\Common\Collections\Collection;

/**
 * Interface HasPasswordPolicyInterface
 */
interface HasPasswordPolicyInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @return DateTime
     */
    public function getPasswordChangedAt(): ?DateTime;

    public function setPasswordChangedAt(DateTime $dateTime): self;

    public function getPasswordHistory(): Collection;

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static;

    public function getPassword(): string;
}
