<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

/**
 * Standard host entity: a natural string id (so a case- or accent-insensitive collation can return look-alikes).
 */
#[ORM\Entity]
#[ORM\Table(name: 'pp_gdpr_user')]
class ErasureUser implements HasPasswordPolicyInterface
{
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $passwordChangedAt = null;

    /** @var Collection<int, PasswordHistoryInterface> */
    #[ORM\OneToMany(targetEntity: ErasurePasswordHistory::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $passwordHistory;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $id,
        #[ORM\Column(length: 255)]
        private string $password = 'REDACTED-TEST',
    ) {
        $this->passwordHistory = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
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
        return $this->passwordHistory;
    }

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if (!$passwordHistory instanceof ErasurePasswordHistory) {
            throw new InvalidArgumentException('Unexpected history class.');
        }
        $passwordHistory->setUser($this);
        $this->passwordHistory->add($passwordHistory);

        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if ($passwordHistory instanceof ErasurePasswordHistory) {
            $this->passwordHistory->removeElement($passwordHistory);
        }

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
