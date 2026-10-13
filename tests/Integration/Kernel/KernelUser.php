<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Kernel;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** In-memory user (no Doctrine mapping needed for the wiring tests). */
final class KernelUser implements HasPasswordPolicyInterface, UserInterface, PasswordAuthenticatedUserInterface
{
    #[PasswordPolicy]
    public string $plainPassword = '';

    public ?DateTime $passwordChangedAt = null;

    /** @var Collection<int, PasswordHistoryInterface> */
    private Collection $passwordHistory;

    public function __construct(private string $password = '')
    {
        $this->passwordHistory = new ArrayCollection();
    }

    public function getId(): int
    {
        return 7;
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

    public function getPasswordHistory(): Collection
    {
        return $this->passwordHistory;
    }

    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        $this->passwordHistory->add($passwordHistory);

        return $this;
    }

    public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        $this->passwordHistory->removeElement($passwordHistory);

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'kernel-user';
    }

    /** Builds a history entry for the given hash, created 30 days ago. */
    public static function historyEntry(string $hash): PasswordHistoryInterface
    {
        return new class($hash) implements PasswordHistoryInterface {
            private DateTimeInterface $createdAt;

            public function __construct(private string $password)
            {
                $this->createdAt = new DateTimeImmutable('-30 days');
            }

            public function getPassword(): string
            {
                return $this->password;
            }

            public function setPassword(string $password): static
            {
                $this->password = $password;

                return $this;
            }

            public function getCreatedAt(): DateTimeInterface
            {
                return $this->createdAt;
            }

            public function setCreatedAt(DateTimeInterface $createdAt): static
            {
                $this->createdAt = $createdAt;

                return $this;
            }
        };
    }
}
