<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a password history entry is created.
 *
 * This event is dispatched by the PasswordEntityListener when a new
 * password history entry is created after a password change.
 */
class PasswordHistoryCreatedEvent extends Event
{
    /**
     * PasswordHistoryCreatedEvent constructor.
     *
     * @param HasPasswordPolicyInterface $user                The user whose password was changed
     * @param PasswordHistoryInterface   $passwordHistory     The created password history entry
     * @param int                        $removedEntriesCount The number of old password history entries that were removed
     */
    public function __construct(
        private readonly HasPasswordPolicyInterface $user,
        private readonly PasswordHistoryInterface $passwordHistory,
        private readonly int $removedEntriesCount = 0
    ) {
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
     * Gets the created password history entry.
     *
     * @return PasswordHistoryInterface The password history entry
     */
    public function getPasswordHistory(): PasswordHistoryInterface
    {
        return $this->passwordHistory;
    }

    /**
     * Gets the number of old password history entries that were removed.
     *
     * @return int The number of removed entries
     */
    public function getRemovedEntriesCount(): int
    {
        return $this->removedEntriesCount;
    }
}
