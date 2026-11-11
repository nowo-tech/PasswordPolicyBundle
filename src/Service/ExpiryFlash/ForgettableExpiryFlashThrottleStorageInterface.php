<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service\ExpiryFlash;

/**
 * A throttle storage that can drop the entry of one subject (personal-data erasure). The cache storage implements it;
 * the session storage does not (its entries live and die with the user's sessions).
 */
interface ForgettableExpiryFlashThrottleStorageInterface extends ExpiryFlashThrottleStorageInterface
{
    /**
     * Removes the entry of $subjectKey and returns whether one existed.
     */
    public function forget(string $subjectKey): bool;
}
