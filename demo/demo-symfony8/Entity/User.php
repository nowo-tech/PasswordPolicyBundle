<?php

declare(strict_types=1);

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements HasPasswordPolicyInterface, UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $passwordChangedAt = null;

    #[ORM\OneToMany(targetEntity: PasswordHistory::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $passwordHistory;

    public function __construct()
    {
        $this->passwordHistory = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
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

    public function getPasswordChangedAt(): ?DateTime
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(DateTime $dateTime): self
    {
        $this->passwordChangedAt = $dateTime;
        return $this;
    }

    public function getPasswordHistory(): Collection
    {
        return $this->passwordHistory;
    }

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if (!$this->passwordHistory->contains($passwordHistory)) {
            $this->passwordHistory->add($passwordHistory);
        }
        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if ($this->passwordHistory->contains($passwordHistory)) {
            $this->passwordHistory->removeElement($passwordHistory);
        }
        return $this;
    }

    // UserInterface methods
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }
}

