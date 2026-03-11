<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Traits;

use Carbon\Carbon;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordHistoryTraitTest extends UnitTestCase
{
    public function testGetSetPassword(): void
    {
        $entity = new PasswordHistoryMock();
        $entity->setPassword('hashed');
        $this->assertSame('hashed', $entity->getPassword());
    }

    public function testGetSetCreatedAt(): void
    {
        $entity = new PasswordHistoryMock();
        $now    = Carbon::now();
        $entity->setCreatedAt($now);
        $createdAt = $entity->getCreatedAt();
        $this->assertInstanceOf(DateTimeInterface::class, $createdAt);
        $this->assertSame($now->getTimestamp(), $createdAt->getTimestamp());
    }

    public function testGetSetSalt(): void
    {
        $entity = new PasswordHistoryMock();
        $this->assertNull($entity->getSalt());
        $entity->setSalt('sodium');
        $this->assertSame('sodium', $entity->getSalt());
        $entity->setSalt(null);
        $this->assertNull($entity->getSalt());
    }

    public function testUpdatedTimestampsSetsCreatedAtWhenNull(): void
    {
        $entity = new PasswordHistoryMock();
        $entity->setPassword('pwd');
        $entity->updatedTimestamps();
        $this->assertInstanceOf(DateTimeInterface::class, $entity->getCreatedAt());
    }

    public function testUpdatedTimestampsDoesNotOverwriteExistingCreatedAt(): void
    {
        $entity = new PasswordHistoryMock();
        $entity->setPassword('pwd');
        $fixed = Carbon::create(2020, 1, 15, 12, 0, 0);
        $this->assertInstanceOf(DateTimeInterface::class, $fixed);
        $entity->setCreatedAt($fixed);
        $entity->updatedTimestamps();
        $createdAt = $entity->getCreatedAt();
        $this->assertInstanceOf(DateTimeInterface::class, $createdAt);
        $this->assertSame($fixed->getTimestamp(), $createdAt->getTimestamp());
    }
}
