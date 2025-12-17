<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a user attempts to reuse an old password.
 *
 * This event is dispatched by the PasswordPolicyValidator when validation
 * detects that the provided password matches one in the password history.
 */
class PasswordReuseAttemptedEvent extends Event
{
    /**
     * PasswordReuseAttemptedEvent constructor.
     *
     * @param HasPasswordPolicyInterface $user            The user attempting to reuse a password
     * @param PasswordHistoryInterface   $passwordHistory The password history entry that matches
     */
    public function __construct(
        private readonly HasPasswordPolicyInterface $user,
        private readonly PasswordHistoryInterface $passwordHistory
    ) {
    }

    /**
     * Gets the user attempting to reuse a password.
     *
     * @return HasPasswordPolicyInterface The user entity
     */
    public function getUser(): HasPasswordPolicyInterface
    {
        return $this->user;
    }

    /**
     * Gets the password history entry that matches the attempted password.
     *
     * @return PasswordHistoryInterface The matching password history entry
     */
    public function getPasswordHistory(): PasswordHistoryInterface
    {
        return $this->passwordHistory;
    }
}
