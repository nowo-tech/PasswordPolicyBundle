<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle;

use Nowo\PasswordPolicyBundle\DependencyInjection\PasswordPolicyExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for password policy management.
 *
 * This bundle provides password history tracking, password expiry enforcement,
 * and configurable password policies for Symfony applications.
 */
class PasswordPolicyBundle extends Bundle
{
    /**
     * Returns the container extension instance.
     *
     * Overridden to allow for the custom extension alias.
     *
     * @return ExtensionInterface|null The container extension
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PasswordPolicyExtension();
        }

        return $this->extension;
    }
}
