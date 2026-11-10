<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Util;

use function array_keys;
use function array_map;
use function ctype_digit;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Base-password candidates for extension detection ("password123" → "password"): single special/digit characters and
 * numeric prefixes/suffixes up to three digits (0–999) are stripped, deduplicated, and only bases of at least
 * $minLength characters are kept.
 */
final class PasswordExtensionCandidates
{
    /**
     * Single-character extensions checked when extension detection is enabled.
     *
     * @var list<string>
     */
    private const EXTENSION_CHARS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '!', '@', '#', '$', '%'];

    /**
     * Maximum length of a numeric prefix/suffix candidate (matches 0–999 semantics).
     */
    private const MAX_NUMERIC_EXTENSION_LENGTH = 3;

    /**
     * @return list<string>
     */
    public static function collect(string $password, int $minLength): array
    {
        /** @var array<string, true> $candidates */
        $candidates = [];
        $length     = strlen($password);

        if ($length <= $minLength) {
            return [];
        }

        foreach (self::EXTENSION_CHARS as $char) {
            if (str_ends_with($password, $char)) {
                self::add($candidates, substr($password, 0, -1), $minLength);
            }
            if (str_starts_with($password, $char)) {
                self::add($candidates, substr($password, 1), $minLength);
            }
        }

        self::addNumeric($candidates, $password, $minLength, suffix: true);
        self::addNumeric($candidates, $password, $minLength, suffix: false);

        // Numeric-string keys ("12345") become integers in PHP arrays: cast them back.
        return array_map(static fn (int|string $candidate): string => (string) $candidate, array_keys($candidates));
    }

    /**
     * @param array<string, true> $candidates
     */
    private static function add(array &$candidates, string $basePassword, int $minLength): void
    {
        if (strlen($basePassword) >= $minLength) {
            $candidates[$basePassword] = true;
        }
    }

    /**
     * Adds bases obtained by removing numeric prefixes or suffixes up to three digits (0–999).
     *
     * @param array<string, true> $candidates
     */
    private static function addNumeric(array &$candidates, string $password, int $minLength, bool $suffix): void
    {
        $length = strlen($password);

        for ($size = 1; $size <= self::MAX_NUMERIC_EXTENSION_LENGTH && $size < $length; ++$size) {
            $part = $suffix ? substr($password, -$size) : substr($password, 0, $size);
            if (!ctype_digit($part)) {
                continue;
            }

            self::add($candidates, $suffix ? substr($password, 0, -$size) : substr($password, $size), $minLength);
        }
    }
}
