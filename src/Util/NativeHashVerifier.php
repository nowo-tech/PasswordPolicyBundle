<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Util;

use SensitiveParameter;

use function base64_encode;
use function hash;
use function password_verify;
use function str_contains;
use function str_starts_with;
use function strlen;

/**
 * password_verify() that also understands Symfony's bcrypt pre-hashing.
 *
 * Symfony's NativePasswordHasher (the auto/bcrypt default) stores a password longer than 72 bytes or containing a
 * NUL byte as bcrypt(base64(sha512(password))), which password_verify(password, hash) never matches. Without the
 * second attempt the reuse checks failed open for such passwords.
 */
final class NativeHashVerifier
{
    private const BCRYPT_MAX_BYTES = 72;

    public static function verify(#[SensitiveParameter] string $plainPassword, string $passwordHash): bool
    {
        if (password_verify($plainPassword, $passwordHash)) {
            return true;
        }

        if (str_starts_with($passwordHash, '$2')
            && (strlen($plainPassword) > self::BCRYPT_MAX_BYTES || str_contains($plainPassword, "\0"))) {
            return password_verify(base64_encode(hash('sha512', $plainPassword, true)), $passwordHash);
        }

        return false;
    }
}
