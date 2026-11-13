<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

/**
 * DDD host history entry: plain PHP mapped in XML (no ORM attribute, no PasswordHistoryTrait).
 */
class XmlHostPasswordHistory implements PasswordHistoryInterface
{
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    private XmlHostUser $user;

    private string $password = '';

    private ?DateTimeInterface $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): XmlHostUser
    {
        return $this->user;
    }

    public function setUser(XmlHostUser $user): self
    {
        $this->user = $user;

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
