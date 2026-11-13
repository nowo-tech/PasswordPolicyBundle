<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by the expiry listener when the current user's password has expired on a locked route (since 1.12.0).
 * Scalar ids only, for entity users (right after {@see PasswordExpiredEvent}) and for read-only subjects alike, so
 * a subscriber never depends on the user entity.
 */
final class PasswordExpiryDetectedEvent extends Event
{
    public function __construct(
        private readonly string $subjectClass,
        private readonly string $subjectId,
        private readonly string $route,
        private readonly bool $willRedirect = false,
    ) {
    }

    /**
     * The configured class of the subject (without Doctrine proxy prefix).
     */
    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    /**
     * The subject identifier as a string ('' when it has no scalar/Stringable id).
     */
    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function willRedirect(): bool
    {
        return $this->willRedirect;
    }
}
