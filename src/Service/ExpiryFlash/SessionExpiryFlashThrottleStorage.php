<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service\ExpiryFlash;

use Symfony\Component\HttpFoundation\RequestStack;

use function is_int;

/**
 * Stores expiry flash throttle timestamps in the HTTP session.
 *
 * Suitable for single-node deployments or when sessions are already backed by a shared store
 * (for example Redis session handler). Not reliable across FrankenPHP workers or Kubernetes
 * pods when sessions are file-based or sticky sessions are disabled.
 */
final class SessionExpiryFlashThrottleStorage implements ExpiryFlashThrottleStorageInterface
{
    private const SESSION_KEY_PREFIX = '_nowo_password_policy.expiry_flash_last_shown_at.';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getLastShownAt(string $subjectKey): ?int
    {
        $session = $this->requestStack->getSession();
        $key     = self::SESSION_KEY_PREFIX . $subjectKey;

        if (!$session->has($key)) {
            return null;
        }

        $value = $session->get($key);

        return is_int($value) ? $value : null;
    }

    public function markShown(string $subjectKey, int $timestamp): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY_PREFIX . $subjectKey, $timestamp);
    }
}
