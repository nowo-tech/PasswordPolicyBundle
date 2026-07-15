<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service\ExpiryFlash;

/**
 * Persists when the password expiry flash was last shown for a given subject (usually a user).
 */
interface ExpiryFlashThrottleStorageInterface
{
    /**
     * @return int|null Unix timestamp when the flash was last shown, or null if never shown
     */
    public function getLastShownAt(string $subjectKey): ?int;

    public function markShown(string $subjectKey, int $timestamp): void;
}
