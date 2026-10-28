<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Util;

use Nowo\PasswordPolicyBundle\Util\LogIdentifier;
use PHPUnit\Framework\TestCase;

final class LogIdentifierTest extends TestCase
{
    public function testRedactsIdentifierWithStableHashPrefix(): void
    {
        $token = LogIdentifier::redact('alice@example.com');

        $this->assertSame('sha256:' . substr(hash('sha256', 'alice@example.com'), 0, 16), $token);
        $this->assertSame($token, LogIdentifier::redact('alice@example.com'));
        $this->assertStringNotContainsString('alice', $token);
    }

    public function testUnknownAndEmptyStayUnknown(): void
    {
        $this->assertSame('unknown', LogIdentifier::redact('unknown'));
        $this->assertSame('unknown', LogIdentifier::redact(''));
    }
}
