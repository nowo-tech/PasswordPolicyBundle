<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\ErasureUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A standard host's user: the HasPasswordPolicyInterface entity is the security user.
 */
final class EntitySecurityUser extends ErasureUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'entity-' . $this->getId();
    }
}
