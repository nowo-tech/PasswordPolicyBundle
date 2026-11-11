<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\PersonalData;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Mockery;
use Nowo\PasswordPolicyBundle\Event\PasswordPolicyPersonalDataErasedEvent;
use Nowo\PasswordPolicyBundle\History\ErasablePasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraser;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporter;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\CacheExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\SessionExpiryFlashThrottleStorage;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;

final class PasswordPolicyPersonalDataEraserTest extends UnitTestCase
{
    private function subject(string $class = KernelUser::class, string $id = '7'): PasswordPolicyPersonalDataSubject
    {
        return PasswordPolicyPersonalDataSubject::fromSubjectId($class, $id);
    }

    public function testAdapterWithoutErasureIsSkippedWithAWarning(): void
    {
        $logger = new BufferingLogger();
        $eraser = new PasswordPolicyPersonalDataEraser(Mockery::mock(PasswordHistoryRepositoryInterface::class), null, null, null, $logger);

        self::assertSame(0, $eraser->erase($this->subject())->total());
        $logs = $logger->cleanLogs();
        self::assertCount(1, $logs);
        self::assertSame('warning', $logs[0][0]);
        self::assertSame(KernelUser::class, $logs[0][2]['entity_class']);
    }

    public function testWithoutDoctrineNoEventIsDispatched(): void
    {
        $repository = Mockery::mock(ErasablePasswordHistoryRepositoryInterface::class);
        $repository->shouldReceive('removeAll')->with('App\Unknown', 'u-1')->once()->andReturn(2);
        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldNotReceive('getManagerForClass');
        $dispatcher = new EventDispatcher();
        $events     = 0;
        $dispatcher->addListener(PasswordPolicyPersonalDataErasedEvent::class, static function () use (&$events): void {
            ++$events;
        });

        $result = (new PasswordPolicyPersonalDataEraser($repository, $registry, null, $dispatcher))->erase($this->subject('App\Unknown', 'u-1'));

        self::assertSame(2, $result->passwordHistory);
        self::assertSame(0, $events);
    }

    public function testOwnTransactionThenEvent(): void
    {
        $order      = [];
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('isTransactionActive')->andReturn(false);
        $connection->shouldReceive('transactional')->once()->andReturnUsing(static function (callable $work) use (&$order): mixed {
            $order[] = 'begin';
            $result  = $work();
            $order[] = 'commit';

            return $result;
        });
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldReceive('getManagerForClass')->with(KernelUser::class)->andReturn($em);
        $repository = Mockery::mock(ErasablePasswordHistoryRepositoryInterface::class);
        $repository->shouldReceive('removeAll')->andReturnUsing(static function () use (&$order): int {
            $order[] = 'delete';

            return 1;
        });
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PasswordPolicyPersonalDataErasedEvent::class, static function () use (&$order): void {
            $order[] = 'event';
        });
        $storage = new CacheExpiryFlashThrottleStorage(new ArrayAdapter(), 60);
        $storage->markShown('Proxies\__CG__\\' . KernelUser::class . ':7', 1);

        $result = (new PasswordPolicyPersonalDataEraser($repository, $registry, $storage, $dispatcher))->erase($this->subject());

        self::assertSame(['begin', 'delete', 'commit', 'event'], $order);
        self::assertSame(['password_history' => 1, 'expiry_flash_throttle' => 1], $result->toArray());
    }

    public function testSessionThrottleStorageIsNotTouched(): void
    {
        $repository = Mockery::mock(ErasablePasswordHistoryRepositoryInterface::class);
        $repository->shouldReceive('removeAll')->andReturn(0);
        $eraser = new PasswordPolicyPersonalDataEraser($repository, null, new SessionExpiryFlashThrottleStorage(new RequestStack()));

        self::assertSame(0, $eraser->erase($this->subject())->expiryFlashThrottle);
    }

    public function testExporterNeverExportsHashes(): void
    {
        $repository = Mockery::mock(PasswordHistoryRepositoryInterface::class);
        $repository->shouldReceive('findRecent')->with(KernelUser::class, '7', PHP_INT_MAX)->andReturn([
            new PasswordHistoryEntry('hash-REDACTED-TEST', new DateTimeImmutable('2026-01-02T03:04:05+00:00')),
            new PasswordHistoryEntry('hash-REDACTED-TEST'),
        ]);

        $export = (new PasswordPolicyPersonalDataExporter($repository))->export($this->subject());

        self::assertSame(['count' => 2, 'entries' => [['changed_at' => '2026-01-02T03:04:05+00:00'], ['changed_at' => null]]], $export);
        self::assertFalse(str_contains(json_encode($export, JSON_THROW_ON_ERROR), 'REDACTED'));
    }
}
