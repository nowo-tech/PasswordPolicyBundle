<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;

interface PasswordExpiryServiceInterface
{
    public function isPasswordExpired(): bool;

    /**
     * @return null|string
     */
    public function getLockedRoutes(?string $entityClass = null): array;

    /**
     * The `isLockedRoute` function is used to check if a specific route is locked for a given entity
     * class. It takes two parameters:
     *
     * @param string $routeName,   which is the name of the route to check
     * @param string $entityClass, which is an optional parameter specifying the entity class
     **/
    public function isLockedRoute(string $routeName, ?string $entityClass = null): bool;

    public function getResetPasswordRouteName(): string;

    public function getExcludedRoutes(?string $entityClass = null): array;

    public function addEntity(PasswordExpiryConfiguration $passwordExpiryConfiguration): void;
}
