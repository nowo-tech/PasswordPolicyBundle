<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;

/**
 * Event dispatched when a password is changed.
 *
 * This event is dispatched by the PasswordEntityListener when a password
 * change is detected and the passwordChangedAt timestamp is updated.
 */
class PasswordChangedEvent extends Event
{
    /**
     * PasswordChangedEvent constructor.
     *
     * @param HasPasswordPolicyInterface $user The user whose password was changed
     * @param \DateTimeInterface $changedAt The timestamp when the password was changed
     */
    public function __construct(
        private readonly HasPasswordPolicyInterface $user,
        private readonly \DateTimeInterface $changedAt
    )
    {
    }

    /**
     * Gets the user whose password was changed.
     *
     * @return HasPasswordPolicyInterface The user entity
     */
    public function getUser(): HasPasswordPolicyInterface
    {
        return $this->user;
    }

    /**
     * Gets the timestamp when the password was changed.
     *
     * @return \DateTimeInterface The change timestamp
     */
    public function getChangedAt(): \DateTimeInterface
    {
        return $this->changedAt;
    }
}

