<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Util;

use function hash;
use function substr;

/**
 * Pseudonymises user identifiers (usually e-mail addresses) before they reach the logs: the same identifier always
 * gives the same token, so entries stay correlatable without storing personal data.
 */
final class LogIdentifier
{
    public static function redact(string $identifier): string
    {
        if ($identifier === '' || $identifier === 'unknown') {
            return 'unknown';
        }

        return 'sha256:' . substr(hash('sha256', $identifier), 0, 16);
    }
}
