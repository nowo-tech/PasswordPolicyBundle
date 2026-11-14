<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Util\SubjectId;
use Symfony\Contracts\EventDispatcher\Event;

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
     * @param DateTimeInterface $changedAt The timestamp when the password was changed
     */
    public function __construct(
        private readonly HasPasswordPolicyInterface $user,
        private readonly DateTimeInterface $changedAt
    ) {
    }

    /**
     * Gets the user whose password was changed.
     *
     * @return HasPasswordPolicyInterface The user entity
     *
     * @deprecated since 1.13.0, use {@see getSubjectClass()} / {@see getSubjectId()} or listen to
     *             {@see PasswordChangeRecordedEvent} (scalar ids, dispatched alongside); removed in 2.0 (REQ-ARCH-001 rule 4)
     */
    public function getUser(): HasPasswordPolicyInterface
    {
        return $this->user;
    }

    /**
     * Gets the timestamp when the password was changed.
     *
     * @return DateTimeInterface The change timestamp
     */
    public function getChangedAt(): DateTimeInterface
    {
        return $this->changedAt;
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
