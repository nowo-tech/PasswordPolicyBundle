<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Util;

use function strlen;

/**
 * Matches Symfony route names against configuration entries that may be literals,
 * globs (fnmatch), or PCRE patterns (same delimiter at start and end).
 */
final class RouteNameMatcher
{
    /**
     * @param string $pattern Entry from notified_routes, excluded_notified_routes, or reset_password_route_pattern
     * @param string $routeName Current request route name (_route)
     */
    public static function matches(string $pattern, string $routeName): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return false;
        }

        $len   = strlen($pattern);
        $first = $pattern[0];
        if ($len >= 3 && ($first === '~' || $first === '#' || $first === '/') && $pattern[$len - 1] === $first) {
            $matched = @preg_match($pattern, $routeName);

            return $matched === 1;
        }

        if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
            return fnmatch($pattern, $routeName);
        }

        return $pattern === $routeName;
    }
}
