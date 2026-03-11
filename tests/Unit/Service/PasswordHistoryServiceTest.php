<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use Carbon\Carbon;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordHistoryServiceTest extends UnitTestCase
{
    private \Mockery\MockInterface|PasswordHistoryService $historyService;

    private \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface|MockInterface $entityMock;

    protected function setUp(): void
    {
        $this->entityMock     = Mockery::mock(HasPasswordPolicyInterface::class);
        $this->historyService = Mockery::mock(PasswordHistoryService::class)->makePartial();
    }

    public function testCleanupHistory(): void
    {
        $arrayCollection = $this->getDummyPasswordHistory();
        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->andReturn($arrayCollection);

        // Mock removePasswordHistory to be called for each item to be removed
        $this->entityMock->shouldReceive('removePasswordHistory')
                         ->times(7)
                         ->andReturnSelf();

        $deletedItems = $this->historyService->getHistoryItemsForCleanup($this->entityMock, 3);

        $this->assertCount(7, $deletedItems);

        $actualTimestamps = array_map(static function (PasswordHistoryInterface $passwordHistory): string {
            $createdAt = $passwordHistory->getCreatedAt();

            return $createdAt instanceof DateTimeInterface ? $createdAt->format('U') : '0';
        }, $deletedItems);

        $expectedTimestamps = [];

        for ($i = 6; $i >= 0; --$i) {
            $item = $arrayCollection->offsetGet($i);
            if ($item === null) {
                continue;
            }
            $createdAt            = $item->getCreatedAt();
            $expectedTimestamps[] = $createdAt !== null ? $createdAt->format('U') : '0';
        }

        $this->assertEquals($expectedTimestamps, $actualTimestamps);
    }

    public function testCleanupHistoryNoNeed(): void
    {
        $arrayCollection = $this->getDummyPasswordHistory();

        $this->entityMock->shouldReceive('getPasswordHistory')
                         ->andReturn($arrayCollection);

        $deletedItems = $this->historyService->getHistoryItemsForCleanup($this->entityMock, 20);

        $this->assertEmpty($deletedItems);
    }

    /**
     * @return ArrayCollection<int, PasswordHistoryInterface>
     */
    private function getDummyPasswordHistory(): ArrayCollection
    {
        $arrayCollection = new ArrayCollection();
        $time            = Carbon::now()->getTimestamp();

        for ($i = 0; $i < 10; ++$i) {

            $time += $i * 100;

            $arrayCollection->add(Mockery::mock(PasswordHistoryInterface::class)
                                     ->shouldReceive('getCreatedAt')
                                     ->andReturn(Carbon::now()->setTimestamp($time))
                                     ->getMock());
        }

        return $arrayCollection;
    }
}
