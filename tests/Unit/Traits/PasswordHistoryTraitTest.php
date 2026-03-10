<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Traits;

use Carbon\Carbon;
use DateTime;
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
        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
        $this->assertSame($now->getTimestamp(), $entity->getCreatedAt()->getTimestamp());
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
        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
    }

    public function testUpdatedTimestampsDoesNotOverwriteExistingCreatedAt(): void
    {
        $entity = new PasswordHistoryMock();
        $entity->setPassword('pwd');
        $fixed = Carbon::create(2020, 1, 15, 12, 0, 0);
        $entity->setCreatedAt($fixed);
        $entity->updatedTimestamps();
        $this->assertSame($fixed->getTimestamp(), $entity->getCreatedAt()->getTimestamp());
    }
}
