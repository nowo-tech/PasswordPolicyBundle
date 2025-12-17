<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Carbon\Carbon;
use DateTime;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Service for handling password expiry checks and route locking.
 *
 * This service checks if passwords have expired based on the last password change date
 * and the configured expiry days. It also manages locked routes that require password
 * changes before access.
 */
class PasswordExpiryService implements PasswordExpiryServiceInterface
{
    /**
     * Array of password expiry configurations indexed by entity class name.
     *
     * @var PasswordExpiryConfiguration[]
     */
    private ?array $entities = null;

    /**
     * Whether caching is enabled for password expiry checks.
     *
     * @var bool
     */
    private bool $cacheEnabled = false;

    /**
     * Cache TTL in seconds.
     *
     * @var int
     */
    private int $cacheTtl = 3600;

    /**
     * PasswordExpiryService constructor.
     *
     * @param TokenStorageInterface       $tokenStorage The token storage service for accessing the current user
     * @param UrlGeneratorInterface       $urlGenerator The URL generator service for generating routes
     * @param CacheItemPoolInterface|null $cache        The cache pool (optional, only used if cache is enabled)
     * @param bool                        $cacheEnabled Whether caching is enabled
     * @param int                         $cacheTtl     Cache time-to-live in seconds
     */
    public function __construct(
        public TokenStorageInterface $tokenStorage,
        public UrlGeneratorInterface $urlGenerator,
        private readonly ?CacheItemPoolInterface $cache = null,
        bool $cacheEnabled = false,
        int $cacheTtl = 3600
    ) {
        $this->cacheEnabled = $cacheEnabled && $cache !== null;
        $this->cacheTtl = $cacheTtl;
    }

    /**
     * Checks if the current user's password has expired.
     *
     * @return bool True if the password has expired, false otherwise
     */
    public function isPasswordExpired(): bool
    {
        if ($this->entities === null) {
            return false;
        }

        /** @var HasPasswordPolicyInterface $user */
        if (($user = $this->getCurrentUser()) instanceof HasPasswordPolicyInterface) {
            // Try to get from cache if enabled
            if ($this->cacheEnabled && $this->cache) {
                $cacheKey = $this->getCacheKey($user);
                $cachedItem = $this->cache->getItem($cacheKey);

                if ($cachedItem->isHit()) {
                    return $cachedItem->get();
                }
            }

            // Calculate expiry status
            $isExpired = false;
            foreach ($this->entities as $class => $config) {
                $passwordLastChange = $user->getPasswordChangedAt();
                if ($passwordLastChange instanceof DateTime && $user instanceof $class) {
                    // Validate that passwordChangedAt is not in the future
                    if ($passwordLastChange > Carbon::now()) {
                        // If date is in the future, treat as not expired (data error)
                        continue;
                    }
                    $expiresAt = (clone $passwordLastChange)->modify('+' . $config->getExpiryDays() . ' days');

                    $isExpired = $expiresAt <= Carbon::now();
                    break; // Found matching entity, no need to continue
                }
            }

            // Store in cache if enabled
            if ($this->cacheEnabled && $this->cache) {
                $cacheKey = $this->getCacheKey($user);
                $cachedItem = $this->cache->getItem($cacheKey);
                $cachedItem->set($isExpired);
                $cachedItem->expiresAfter($this->cacheTtl);
                $this->cache->save($cachedItem);
            }

            return $isExpired;
        }

        return false;
    }

    /**
     * Invalidates the cache for a specific user.
     *
     * This should be called when a password is changed to ensure the cache is updated.
     *
     * @param HasPasswordPolicyInterface $user The user whose cache should be invalidated
     *
     * @return void
     */
    public function invalidateCache(HasPasswordPolicyInterface $user): void
    {
        if ($this->cacheEnabled && $this->cache) {
            $cacheKey = $this->getCacheKey($user);
            $this->cache->deleteItem($cacheKey);
        }
    }

    /**
     * Generates a cache key for a user's password expiry status.
     *
     * @param HasPasswordPolicyInterface $user The user
     *
     * @return string The cache key
     */
    private function getCacheKey(HasPasswordPolicyInterface $user): string
    {
        $userId = method_exists($user, 'getId') ? (string) $user->getId() : 'unknown';
        $userClass = $user::class;
        $passwordChangedAt = $user->getPasswordChangedAt();
        $passwordHash = $passwordChangedAt instanceof DateTime
          ? $passwordChangedAt->getTimestamp()
          : 'no-date';

        return sprintf(
            'password_expiry_%s_%s_%s',
            md5($userClass),
            $userId,
            $passwordHash
        );
    }

    /**
     * Gets the list of locked routes for the specified entity class.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return array Array of route names that are locked
     */
    public function getLockedRoutes(?string $entityClass = null): array
    {
        $entityClass = $this->prepareEntityClass(entityClass: $entityClass);

        return isset($this->entities[$entityClass]) ? $this->entities[$entityClass]->getLockRoutes() : [];
    }

    /**
     * Checks if a given route is locked based on the provided route name and entity class.
     *
     * @param string      $routeName   The name of the route to check
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return bool True if the route is locked, false otherwise
     */
    public function isLockedRoute(string $routeName, ?string $entityClass = null): bool
    {
        $lockedRoutes = $this->getLockedRoutes(entityClass: $entityClass);

        return in_array(needle: $routeName, haystack: $lockedRoutes);
    }

    /**
     * Gets the reset password route name for the specified entity class.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return string The route name for password reset
     */
    public function getResetPasswordRouteName(?string $entityClass = null): string
    {
        $entityClass = $this->prepareEntityClass(entityClass: $entityClass);

        return isset($this->entities[$entityClass]) ? $this->entities[$entityClass]->getResetPasswordRouteName() : '';
    }

    /**
     * Gets the list of excluded routes that should not trigger password expiry checks.
     *
     * @param string|null $entityClass The entity class name. If null, uses the current user's class
     *
     * @return array Array of route names that are excluded from expiry checks
     */
    public function getExcludedRoutes(?string $entityClass = null): array
    {
        $entityClass = $this->prepareEntityClass(entityClass: $entityClass);

        return isset($this->entities[$entityClass]) ? $this->entities[$entityClass]->getExcludedRoutes() : [];
    }

    /**
     * Adds a password expiry configuration for an entity class.
     *
     * @param PasswordExpiryConfiguration $passwordExpiryConfiguration The configuration to add
     *
     * @return void
     */
    public function addEntity(PasswordExpiryConfiguration $passwordExpiryConfiguration): void
    {
        $this->entities[$passwordExpiryConfiguration->getEntityClass()] = $passwordExpiryConfiguration;
    }

    /**
     * Gets the current authenticated user if it implements HasPasswordPolicyInterface.
     *
     * @return HasPasswordPolicyInterface|null The current user or null if not authenticated or not implementing the interface
     */
    private function getCurrentUser(): ?HasPasswordPolicyInterface
    {
        $token = $this->tokenStorage->getToken();
        if ($token && $user = $token->getUser()) {
            if ($user === 'anon.') {
                return null;
            }

            return $user instanceof HasPasswordPolicyInterface ? $user : null;
        }

        return null;
    }

    /**
     * Prepares the entity class name for configuration lookup.
     *
     * If no entity class is provided, it attempts to get it from the current user.
     *
     * @param string|null $entityClass The entity class name
     *
     * @return string|null The prepared entity class name
     */
    private function prepareEntityClass(?string $entityClass): ?string
    {
        if (is_null($entityClass) && $user = $this->getCurrentUser()) {
            return $user::class;
        }

        return $entityClass;
    }
}
