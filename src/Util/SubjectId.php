<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Util;

use Stringable;

use function is_scalar;
use function strlen;
use function strrpos;
use function substr;

/**
 * Scalar subject identity (class + id) of a password policy subject, for events and ports that must not carry the
 * host entity.
 */
final class SubjectId
{
    private const PROXY_MARKER = '\__CG__\\';

    /**
     * The identifier as a string: scalars and Stringable values (e.g. Uuid) are cast, anything else gives ''.
     */
    public static function of(mixed $id): string
    {
        if (is_scalar($id) || $id instanceof Stringable) {
            return (string) $id;
        }

        return '';
    }

    /**
     * The class of the object without a Doctrine proxy prefix (Proxies\__CG__\App\Entity\User → App\Entity\User).
     */
    public static function classOf(object $subject): string
    {
        return self::withoutProxyPrefix($subject::class);
    }

    /**
     * The class name without a Doctrine proxy prefix.
     */
    public static function withoutProxyPrefix(string $class): string
    {
        $position = strrpos($class, self::PROXY_MARKER);

        return $position === false ? $class : substr($class, $position + strlen(self::PROXY_MARKER));
    }
}
