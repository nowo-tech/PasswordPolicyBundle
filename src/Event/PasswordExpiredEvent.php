<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a password expiry is detected.
 *
 * This event is dispatched by the PasswordExpiryListener when it detects
 * that a user's password has expired while accessing a locked route.
 */
class PasswordExpiredEvent extends Event
{
    /**
     * PasswordExpiredEvent constructor.
     *
     * @param HasPasswordPolicyInterface $user         The user whose password has expired
     * @param string                     $route        The route that triggered the expiry check
     * @param bool                       $willRedirect Whether the user will be redirected to reset password route
     */
    public function __construct(
        private readonly HasPasswordPolicyInterface $user,
        private readonly string $route,
        private readonly bool $willRedirect = false
    ) {
    }

    /**
     * Gets the user whose password has expired.
     *
     * @return HasPasswordPolicyInterface The user entity
     */
    public function getUser(): HasPasswordPolicyInterface
    {
        return $this->user;
    }

    /**
     * Gets the route that triggered the expiry check.
     *
     * @return string The route name
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * Checks if the user will be redirected to the reset password route.
     *
     * @return bool True if redirect is enabled, false otherwise
     */
    public function willRedirect(): bool
    {
        return $this->willRedirect;
    }
}
