<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\Model\CurrentPasswordHistory;
use PHPUnit\Framework\TestCase;

final class CurrentPasswordHistoryTest extends TestCase
{
    public function testAccessors(): void
    {
        $changedAt = new DateTimeImmutable('2026-01-01');
        $history   = new CurrentPasswordHistory('hash', $changedAt);

        $this->assertSame('hash', $history->getPassword());
        $this->assertSame($changedAt, $history->getCreatedAt());

        $other = new DateTimeImmutable('2026-02-01');
        $this->assertSame($history, $history->setPassword('hash2')->setCreatedAt($other));
        $this->assertSame('hash2', $history->getPassword());
        $this->assertSame($other, $history->getCreatedAt());
        $this->assertNull((new CurrentPasswordHistory('h'))->getCreatedAt());
    }
}
