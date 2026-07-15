<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

/**
 * Controls how often the password expiry flash message is added to the session.
 */
final class ExpiryFlashStrategy
{
    /**
     * Add the flash on every locked-route request (after the previous flash was consumed).
     */
    public const ALWAYS = 'always';

    /**
     * Add the flash at most once per session (until logout or session expiry).
     */
    public const ONCE_PER_SESSION = 'once_per_session';

    /**
     * Add the flash again only after the configured flash_interval_minutes have elapsed.
     */
    public const INTERVAL = 'interval';

    /**
     * Never add a flash (redirect and events still apply when configured).
     */
    public const NEVER = 'never';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ALWAYS,
            self::ONCE_PER_SESSION,
            self::INTERVAL,
            self::NEVER,
        ];
    }
}
