<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use Carbon\Carbon;
use Doctrine\Common\Collections\ArrayCollection;
use Mockery;
use Mockery\Mock;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordHistoryServiceTest extends UnitTestCase
{
    /**
     * @var PasswordHistoryService|Mock
     */
    private $historyService;

    /**
     * @var HasPasswordPolicyInterface|Mock
     */
    private $entityMock;

    protected function setUp(): void
    {
        $this->entityMock = Mockery::mock(HasPasswordPolicyInterface::class);
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

        $actualTimestamps = array_map(fn (PasswordHistoryInterface $passwordHistory) => $passwordHistory->getCreatedAt()->format('U'), $deletedItems);

        $expectedTimestamps = [];

        for ($i = 6; $i >= 0; --$i) {
            $expectedTimestamps[] = $arrayCollection->offsetGet($i)->getCreatedAt()->format('U');
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

    private function getDummyPasswordHistory(): ArrayCollection
    {
        $arrayCollection = new ArrayCollection();
        $time = Carbon::now()->getTimestamp();

        for ($i = 0; $i < 10; ++$i) {

            $time += $i * 100;

            $arrayCollection->add(Mockery::mock(PasswordHistoryInterface::class)
                                     ->shouldReceive('getCreatedAt')
                                     ->andReturn((Carbon::now())->setTimestamp($time))
                                     ->getMock());
        }

        return $arrayCollection;
    }
}
