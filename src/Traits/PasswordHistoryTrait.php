<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Traits;

use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

trait PasswordHistoryTrait
{
    /**
     * @ORM\Column(type="string")
     *
     * @ORM\Id()
     */
    private ?string $password = null;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $salt = null;

    /**
     * @var DateTime|null
     *
     * @ORM\Column(type="datetime")
     */
    private ?DateTimeInterface $createdAt = null;

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @ORM\PrePersist
     */
    public function updatedTimestamps(): void
    {

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(Carbon::now());
        }
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): void
    {
        $this->salt = $salt;
    }
}
