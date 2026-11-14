<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Util\SubjectId;
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
     * @param HasPasswordPolicyInterface $user The user attempting to reuse a password
     * @param PasswordHistoryInterface $passwordHistory The password history entry that matches
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
     *
     * @deprecated since 1.13.0, use {@see getSubjectClass()} / {@see getSubjectId()} or listen to
     *             {@see PasswordReuseDetectedEvent} (scalar ids, dispatched alongside); removed in 2.0 (REQ-ARCH-001 rule 4)
     */
    public function getUser(): HasPasswordPolicyInterface
    {
        return $this->user;
    }

    /**
     * Gets the password history entry that matches the attempted password.
     *
     * @return PasswordHistoryInterface The matching password history entry
     *
     * @deprecated since 1.13.0, use {@see PasswordReuseDetectedEvent::getMatchType()} /
     *             {@see PasswordReuseDetectedEvent::getUsedAt()} (dispatched alongside); removed in 2.0
     *             (REQ-ARCH-001 rule 4)
     */
    public function getPasswordHistory(): PasswordHistoryInterface
    {
        return $this->passwordHistory;
    }

    /**
     * Class of the user (without Doctrine proxy prefix), for subscribers that must not depend on the entity.
     */
    public function getSubjectClass(): string
    {
        return SubjectId::classOf($this->user);
    }

    /**
     * Identifier of the user as a string ('' when it has no scalar/Stringable id).
     */
    public function getSubjectId(): string
    {
        return SubjectId::of($this->user->getId());
    }
}
