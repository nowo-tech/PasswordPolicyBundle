<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Util;

use Nowo\PasswordPolicyBundle\Util\PasswordExtensionCandidates;
use PHPUnit\Framework\TestCase;

final class PasswordExtensionCandidatesTest extends TestCase
{
    public function testStripsSingleCharactersAndNumericAffixes(): void
    {
        $candidates = PasswordExtensionCandidates::collect('1password123', 4);

        self::assertContains('1password12', $candidates);
        self::assertContains('password123', $candidates);
        self::assertContains('1password', $candidates);
        self::assertSame($candidates, array_values(array_unique($candidates)));
    }

    public function testShortPasswordsHaveNoCandidates(): void
    {
        self::assertSame([], PasswordExtensionCandidates::collect('abcd', 4));
    }

    public function testNumericBasesStayStrings(): void
    {
        // "12345" as an array key becomes an int: a candidate must still be a string (strict_types callers).
        $candidates = PasswordExtensionCandidates::collect('12345!', 4);

        self::assertSame(['2345!', '12345', '345!'], $candidates);
    }
}
