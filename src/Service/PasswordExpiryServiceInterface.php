<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;

/**
 * Interface for password expiry service.
 *
 * This service handles password expiry checks and route locking based on
 * password expiry configuration.
 */
interface PasswordExpiryServiceInterface
{
    /**
     * Checks if the current user's password has expired.
     *
     * @return bool True if the password has expired, false otherwise
     */
    public function isPasswordExpired(): bool;

    /**
     * Gets the list of locked routes for the specified entity class.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return array Array of route names that are locked
     */
    public function getLockedRoutes(?string $entityClass = null): array;

    /**
     * Checks if a given route is locked for the specified entity class.
     *
     * @param string      $routeName   The name of the route to check
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return bool True if the route is locked, false otherwise
     */
    public function isLockedRoute(string $routeName, ?string $entityClass = null): bool;

    /**
     * Gets the reset password route name for the specified entity class.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return string The route name for password reset
     */
    public function getResetPasswordRouteName(?string $entityClass = null): string;

    /**
     * Gets the list of excluded routes that should not trigger password expiry checks.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return array Array of route names that are excluded from expiry checks
     */
    public function getExcludedRoutes(?string $entityClass = null): array;

    /**
     * Adds a password expiry configuration for an entity class.
     *
     * @param PasswordExpiryConfiguration $passwordExpiryConfiguration The configuration to add
     *
     * @return void
     */
    public function addEntity(PasswordExpiryConfiguration $passwordExpiryConfiguration): void;

    /**
     * Invalidates the cache for a specific user's password expiry status.
     *
     * This should be called when a password is changed to ensure the cache is updated.
     *
     * @param HasPasswordPolicyInterface $user The user whose cache should be invalidated
     *
     * @return void
     */
    public function invalidateCache(HasPasswordPolicyInterface $user): void;
}
