<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Kernel;

use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\ExpiryFlashThrottleStorageInterface;

/** Application-style custom flash throttle storage. */
final class InMemoryFlashThrottleStorage implements ExpiryFlashThrottleStorageInterface
{
    /** @var array<string, int> */
    private array $shownAt = [];

    public function getLastShownAt(string $subjectKey): ?int
    {
        return $this->shownAt[$subjectKey] ?? null;
    }

    public function markShown(string $subjectKey, int $timestamp): void
    {
        $this->shownAt[$subjectKey] = $timestamp;
    }
}
