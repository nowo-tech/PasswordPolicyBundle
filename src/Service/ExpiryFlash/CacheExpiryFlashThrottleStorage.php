<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service\ExpiryFlash;

use Psr\Cache\CacheItemPoolInterface;

use function is_int;

/**
 * Stores expiry flash throttle timestamps in a shared cache pool (Redis, Memcached, etc.).
 *
 * Recommended for FrankenPHP worker mode and Kubernetes multi-pod deployments where session
 * storage is not shared between workers or pods.
 */
final class CacheExpiryFlashThrottleStorage implements ExpiryFlashThrottleStorageInterface
{
    private const CACHE_KEY_PREFIX = 'nowo_password_policy.expiry_flash.';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtlSeconds,
    ) {
    }

    public function getLastShownAt(string $subjectKey): ?int
    {
        $item = $this->cache->getItem($this->buildCacheKey($subjectKey));
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return is_int($value) ? $value : null;
    }

    public function markShown(string $subjectKey, int $timestamp): void
    {
        $item = $this->cache->getItem($this->buildCacheKey($subjectKey));
        $item->set($timestamp);
        $item->expiresAfter($this->cacheTtlSeconds);
        $this->cache->save($item);
    }

    private function buildCacheKey(string $subjectKey): string
    {
        return self::CACHE_KEY_PREFIX . hash('sha256', $subjectKey);
    }
}
