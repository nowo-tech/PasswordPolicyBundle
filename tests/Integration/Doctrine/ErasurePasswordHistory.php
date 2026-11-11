<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

#[ORM\Entity]
#[ORM\Table(name: 'pp_gdpr_password_history')]
class ErasurePasswordHistory implements PasswordHistoryInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ErasureUser::class, inversedBy: 'passwordHistory')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ErasureUser $user;

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $createdAt;

    public function __construct(DateTimeInterface $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ErasureUser
    {
        return $this->user;
    }

    public function setUser(ErasureUser $user): self
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
