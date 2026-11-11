<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Event;

use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * The password policy data of a subject was erased; carries scalar values only.
 *
 * Dispatched by {@see PasswordPolicyPersonalDataEraserInterface::erase()} after the commit of the transaction it
 * opened. Not dispatched when the erasure joined the caller's transaction.
 */
final class PasswordPolicyPersonalDataErasedEvent extends Event
{
    /**
     * @param array<string, int> $counts rows or entries removed per kind (password_history, expiry_flash_throttle)
     */
    public function __construct(
        private readonly string $subjectClass,
        private readonly string $subjectId,
        private readonly array $counts,
    ) {
    }

    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getSubjectId(): string
    {
        return $this->subjectId;
    }

    /**
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        return $this->counts;
    }
}
