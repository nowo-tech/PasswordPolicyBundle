<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A DDD host's security adapter: a Symfony user that is a read-only password policy subject, not an entity.
 */
class DomainSecurityUser implements UserInterface, PasswordPolicySubjectInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?DateTimeInterface $passwordChangedAt,
    ) {
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
        return 'user-' . $this->id;
    }

    public function getPasswordPolicySubjectClass(): string
    {
        return static::class;
    }

    public function getPasswordPolicySubjectId(): string
    {
        return $this->id;
    }

    public function getPasswordHash(): ?string
    {
        return null;
    }

    public function getPasswordChangedAt(): ?DateTimeInterface
    {
        return $this->passwordChangedAt;
    }
}
