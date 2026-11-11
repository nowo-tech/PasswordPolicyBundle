<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use InvalidArgumentException;

/**
 * What {@see PasswordPolicyPersonalDataEraserInterface::erase()} removed, per kind.
 */
final readonly class PasswordPolicyErasureResult
{
    /**
     * @param int $passwordHistory password history rows deleted
     * @param int $expiryFlashThrottle expiry flash throttle entries removed from a shared (cache) storage
     */
    public function __construct(
        public int $passwordHistory = 0,
        public int $expiryFlashThrottle = 0,
    ) {
        if ($passwordHistory < 0 || $expiryFlashThrottle < 0) {
            throw new InvalidArgumentException('Erasure counts must not be negative.');
        }
    }

    /**
     * Counts keyed by stable snake_case kind.
     *
     * @return array{password_history: int, expiry_flash_throttle: int}
     */
    public function toArray(): array
    {
        return [
            'password_history'      => $this->passwordHistory,
            'expiry_flash_throttle' => $this->expiryFlashThrottle,
        ];
    }

    /**
     * Every row or entry removed.
     */
    public function total(): int
    {
        return $this->passwordHistory + $this->expiryFlashThrottle;
    }
}
