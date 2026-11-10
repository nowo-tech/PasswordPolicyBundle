<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\History;

use DateTimeImmutable;
use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRecorder;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryPasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PasswordHistoryRecorderTest extends UnitTestCase
{
    private const SUBJECT_CLASS = 'App\Domain\User';

    public function testRecordsPrunesAndDispatchesScalarEvent(): void
    {
        $repository = new InMemoryPasswordHistoryRepository();
        $config     = new PasswordPolicyConfigurationService();
        $config->setEntityConfiguration(self::SUBJECT_CLASS, ['passwords_to_remember' => 2]);

        $events     = [];
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(static function (object $event) use (&$events): object {
            $events[] = $event;

            return $event;
        });
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('notice')->times(3)->with('Password changed successfully', Mockery::on(
            static fn (array $context): bool => $context['user_id'] === 'u-1' && $context['entity_class'] === self::SUBJECT_CLASS,
        ));

        $recorder = new PasswordHistoryRecorder($repository, $config, $dispatcher, null, $logger, true, 'notice');

        self::assertSame(0, $recorder->record(self::SUBJECT_CLASS, 'u-1', 'hash-1', new DateTimeImmutable('2026-01-01')));
        self::assertSame(0, $recorder->record(self::SUBJECT_CLASS, 'u-1', 'hash-2', new DateTimeImmutable('2026-02-01')));
        self::assertSame(1, $recorder->record(self::SUBJECT_CLASS, 'u-1', 'hash-3', new DateTimeImmutable('2026-03-01')));

        self::assertSame(['hash-3', 'hash-2'], array_map(
            static fn (PasswordHistoryEntry $entry): string => $entry->passwordHash,
            $repository->findRecent(self::SUBJECT_CLASS, 'u-1', 10),
        ));

        self::assertCount(3, $events);
        $last = $events[2];
        self::assertInstanceOf(PasswordChangeRecordedEvent::class, $last);
        self::assertSame(self::SUBJECT_CLASS, $last->getSubjectClass());
        self::assertSame('u-1', $last->getSubjectId());
        self::assertSame('2026-03-01', $last->getChangedAt()->format('Y-m-d'));
        self::assertSame(1, $last->getRemovedEntriesCount());
    }

    public function testEmptyHashRecordsNothing(): void
    {
        $repository = new InMemoryPasswordHistoryRepository();
        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldNotReceive('dispatch');

        $recorder = new PasswordHistoryRecorder($repository, null, $dispatcher);

        self::assertSame(0, $recorder->record(self::SUBJECT_CLASS, 'u-1', ''));
        self::assertSame(0, $recorder->record(self::SUBJECT_CLASS, 'u-1', '0'));
        self::assertSame([], $repository->findRecent(self::SUBJECT_CLASS, 'u-1', 10));
    }

    public function testDefaultsToClockNowAndDefaultLimit(): void
    {
        $repository = new InMemoryPasswordHistoryRepository();
        $clock      = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-04-05 06:07:08'));

        $recorder = new PasswordHistoryRecorder($repository, null, null, $clock);
        foreach (['a', 'b', 'c', 'd'] as $hash) {
            $recorder->record(self::SUBJECT_CLASS, 'u-1', $hash);
        }

        $entries = $repository->findRecent(self::SUBJECT_CLASS, 'u-1', 10);
        self::assertCount(3, $entries);
        self::assertSame('2026-04-05 06:07:08', $entries[0]->createdAt?->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function logLevels(): iterable
    {
        yield 'debug' => ['debug', 'debug'];
        yield 'info' => ['info', 'info'];
        yield 'warning' => ['warning', 'warning'];
        yield 'error' => ['error', 'error'];
        yield 'unknown' => ['bogus', 'info'];
    }

    #[DataProvider('logLevels')]
    public function testLogsAtTheConfiguredLevel(string $level, string $method): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive($method)->once();

        (new PasswordHistoryRecorder(new InMemoryPasswordHistoryRepository(), null, null, null, $logger, true, $level))
            ->record(self::SUBJECT_CLASS, 'u-1', 'hash');
        $this->addToAssertionCount(1);
    }
}
