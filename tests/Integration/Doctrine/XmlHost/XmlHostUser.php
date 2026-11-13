<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

/**
 * DDD host user: plain PHP, no ORM attribute, no bundle base class or trait. Mapped in
 * mapping/Nowo.PasswordPolicyBundle.Tests.Integration.Doctrine.XmlHost.XmlHostUser.dcm.xml (REQ-ARCH-001 rule 2).
 */
class XmlHostUser implements HasPasswordPolicyInterface
{
    private ?DateTime $passwordChangedAt = null;

    /**
     * Not readonly: Doctrine replaces the collection with its own on persist and load.
     *
     * @var Collection<int, PasswordHistoryInterface>|null
     */
    private ?Collection $passwordHistory = null;

    public function __construct(
        private readonly string $id,
        private string $password,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function changePassword(string $hash): void
    {
        $this->password = $hash;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPasswordChangedAt(): ?DateTime
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(DateTime $dateTime): static
    {
        $this->passwordChangedAt = $dateTime;

        return $this;
    }

    /**
     * @return Collection<int, PasswordHistoryInterface>
     */
    public function getPasswordHistory(): Collection
    {
        return $this->passwordHistory ??= new ArrayCollection();
    }

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if (!$passwordHistory instanceof XmlHostPasswordHistory) {
            throw new InvalidArgumentException('Unexpected history class.');
        }
        $passwordHistory->setUser($this);
        $this->getPasswordHistory()->add($passwordHistory);

        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        $this->getPasswordHistory()->removeElement($passwordHistory);

        return $this;
    }
}
