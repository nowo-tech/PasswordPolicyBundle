<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use DateTime;
use Nowo\PasswordPolicyBundle\Event\PasswordPolicyPersonalDataErasedEvent;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyErasureResult;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraser;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporter;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\CacheExpiryFlashThrottleStorage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function getenv;
use function json_encode;
use function memory_get_usage;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/**
 * GDPR ports on a real database: SQLite always, MySQL / PostgreSQL with DATABASE_URL.
 */
final class PasswordPolicyPersonalDataDoctrineTest extends OrmTestCase
{
    /** @var list<PasswordPolicyPersonalDataErasedEvent> */
    private array $events = [];

    /** @var list<bool> whether a transaction was open when each event was dispatched */
    private array $transactionOpenOnDispatch = [];

    private int $eventCount = 0;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(PasswordPolicyPersonalDataErasedEvent::class, function (PasswordPolicyPersonalDataErasedEvent $event): void {
            ++$this->eventCount;
            if ($this->eventCount <= 10) {
                $this->events[]                    = $event;
                $this->transactionOpenOnDispatch[] = $this->em->getConnection()->isTransactionActive();
            }
        });
    }

    private function eraser(?CacheExpiryFlashThrottleStorage $storage = null): PasswordPolicyPersonalDataEraser
    {
        return new PasswordPolicyPersonalDataEraser($this->repository(), $this->registry, $storage, $this->dispatcher);
    }

    private function exporter(): PasswordPolicyPersonalDataExporter
    {
        return new PasswordPolicyPersonalDataExporter($this->repository());
    }

    private static function subject(string $id): PasswordPolicyPersonalDataSubject
    {
        return PasswordPolicyPersonalDataSubject::fromSubjectId(ErasureUser::class, $id);
    }

    public function testEraseDeletesOnlyTheSubjectsRowsAndIsIdempotent(): void
    {
        $this->seedUser('alice', '2026-01-01 10:00:00', '2026-03-01 10:00:00', '2026-02-01 10:00:00');
        $this->seedUser('bob', '2026-01-15 10:00:00');

        $result = $this->eraser()->erase(self::subject('alice'));

        self::assertSame(['password_history' => 3, 'expiry_flash_throttle' => 0], $result->toArray());
        self::assertSame(1, $this->totalHistoryRows());
        self::assertSame(1, $this->historyRows('bob'));
        self::assertSame(0, $this->eraser()->erase(self::subject('alice'))->total());
        self::assertSame(0, $this->eraser()->erase(self::subject('nobody'))->total());
        self::assertSame(1, $this->totalHistoryRows());
    }

    public function testEventIsDispatchedAfterTheOwnCommit(): void
    {
        $this->seedUser('alice', '2026-01-01 10:00:00');

        $this->eraser()->erase(self::subject('alice'));

        self::assertCount(1, $this->events);
        self::assertSame([false], $this->transactionOpenOnDispatch);
        self::assertSame(ErasureUser::class, $this->events[0]->getSubjectClass());
        self::assertSame('alice', $this->events[0]->getSubjectId());
        self::assertSame(['password_history' => 1, 'expiry_flash_throttle' => 0], $this->events[0]->getCounts());
    }

    public function testInsideTheCallersTransactionItJoinsAndDoesNotDispatch(): void
    {
        $this->seedUser('alice', '2026-01-01 10:00:00', '2026-02-01 10:00:00');
        $connection = $this->em->getConnection();

        $connection->beginTransaction();
        $result = $this->eraser()->erase(self::subject('alice'));
        self::assertTrue($connection->isTransactionActive());
        $connection->rollBack();

        self::assertSame(2, $result->passwordHistory);
        self::assertSame([], $this->events);
        // The caller rolled back: nothing was erased.
        self::assertSame(2, $this->totalHistoryRows());

        $this->em->clear();
        $connection->beginTransaction();
        $this->eraser()->erase(self::subject('alice'));
        $connection->commit();

        self::assertSame([], $this->events);
        self::assertSame(0, $this->totalHistoryRows());
    }

    public function testCaseAndAccentVariantsAreNeitherErasedNorExported(): void
    {
        $this->seedUser('UserA', '2026-01-01 10:00:00');
        $this->seedUser('josé', '2026-01-02 10:00:00');

        if ($this->isMySql()) {
            // The trap this guards against: MySQL's default collation finds the look-alikes.
            self::assertSame('UserA', $this->em->getConnection()->fetchOne('SELECT id FROM pp_gdpr_user WHERE id = ?', ['usera']));
        }

        foreach (['usera', 'USERA', 'jose', 'JOSÉ', 'josé '] as $variant) {
            self::assertSame(0, $this->eraser()->erase(self::subject($variant))->total(), $variant);
            self::assertSame(['count' => 0, 'entries' => []], $this->exporter()->export(self::subject($variant)), $variant);
            $this->em->clear();
        }

        self::assertSame(2, $this->totalHistoryRows());
        self::assertSame(1, $this->exporter()->export(self::subject('UserA'))['count']);
        self::assertSame(1, $this->eraser()->erase(self::subject('josé'))->passwordHistory);
        self::assertSame(1, $this->totalHistoryRows());
    }

    public function testExportHasChangeDatesOnlyNewestFirst(): void
    {
        $this->seedUser('alice', '2026-01-01 10:00:00', '2026-03-01 10:00:00', '2026-02-01 10:00:00');
        $this->seedUser('bob', '2026-01-15 10:00:00');

        $export = $this->exporter()->export(self::subject('alice'));

        self::assertSame(3, $export['count']);
        self::assertSame(
            [(new DateTime('2026-03-01 10:00:00'))->format(DATE_ATOM), (new DateTime('2026-02-01 10:00:00'))->format(DATE_ATOM), (new DateTime('2026-01-01 10:00:00'))->format(DATE_ATOM)],
            array_column($export['entries'], 'changed_at'),
        );
        self::assertSame(['changed_at'], array_keys($export['entries'][0]));
        self::assertFalse(str_contains(json_encode($export, JSON_THROW_ON_ERROR), 'REDACTED-TEST'));
    }

    public function testLoadedCollectionIsSyncedAndTheHostCanFlushAndDeleteTheUser(): void
    {
        $this->seedUser('alice', '2026-01-01 10:00:00', '2026-02-01 10:00:00');
        $user = $this->em->find(ErasureUser::class, 'alice');
        self::assertInstanceOf(ErasureUser::class, $user);
        self::assertCount(2, $user->getPasswordHistory());

        self::assertSame(2, $this->eraser()->erase(self::subject('alice'))->passwordHistory);

        self::assertCount(0, $user->getPasswordHistory());
        $this->em->flush();
        $this->em->remove($user);
        $this->em->flush();
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pp_gdpr_user'));
    }

    public function testCacheThrottleEntryIsForgotten(): void
    {
        $storage = new CacheExpiryFlashThrottleStorage(new ArrayAdapter(), 3600);
        $storage->markShown(ErasureUser::class . ':alice', 1);
        $storage->markShown(ErasureUser::class . ':bob', 1);
        $this->seedUser('alice', '2026-01-01 10:00:00');

        $result = $this->eraser($storage)->erase(self::subject('alice'));

        self::assertEquals(new PasswordPolicyErasureResult(1, 1), $result);
        self::assertNull($storage->getLastShownAt(ErasureUser::class . ':alice'));
        self::assertSame(1, $storage->getLastShownAt(ErasureUser::class . ':bob'));
        self::assertSame(0, $this->eraser($storage)->erase(self::subject('alice'))->total());
    }

    /**
     * FrankenPHP worker mode (kernel kept alive, no reset): the same service instances serve user A then user B many
     * times without leaking state between them, and memory stays flat.
     */
    public function testWorkerModeAlternatingSubjectsDoNotLeakState(): void
    {
        $runs = (string) getenv('DATABASE_URL') === '' ? 1000 : 100;
        $this->seedUser('alice');
        $this->seedUser('bob');
        $eraser     = $this->eraser();
        $exporter   = $this->exporter();
        $connection = $this->em->getConnection();
        $alice      = self::subject('alice');
        $bob        = self::subject('bob');
        $baseline   = 0;

        for ($run = 1; $run <= $runs; ++$run) {
            $connection->insert('pp_gdpr_password_history', ['user_id' => 'alice', 'password' => 'REDACTED-TEST', 'created_at' => '2026-01-01 10:00:00']);
            $connection->insert('pp_gdpr_password_history', ['user_id' => 'bob', 'password' => 'REDACTED-TEST', 'created_at' => '2026-02-02 10:00:00']);
            $connection->insert('pp_gdpr_password_history', ['user_id' => 'bob', 'password' => 'REDACTED-TEST', 'created_at' => '2026-02-03 10:00:00']);

            self::assertSame(1, $exporter->export($alice)['count']);
            self::assertSame(1, $eraser->erase($alice)->passwordHistory);
            $this->em->clear(); // the request boundary of the host
            self::assertSame(2, $exporter->export($bob)['count']);
            self::assertSame(2, $eraser->erase($bob)->passwordHistory);
            self::assertSame(0, $exporter->export($alice)['count']);
            $this->em->clear();

            if ($run === 50) {
                $baseline = memory_get_usage();
            }
        }

        self::assertSame(0, $this->totalHistoryRows());
        self::assertSame(2 * $runs, $this->eventCount);
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $baseline);
    }
}
