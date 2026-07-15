<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

/**
 * Built-in backends for expiry flash throttle state.
 */
final class ExpiryFlashThrottleStorageType
{
    /**
     * Store throttle timestamps in the HTTP session (single pod or shared session handler).
     */
    public const SESSION = 'session';

    /**
     * Store throttle timestamps in a shared cache pool (Redis, Memcached, etc.).
     */
    public const CACHE = 'cache';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::SESSION,
            self::CACHE,
        ];
    }
}
