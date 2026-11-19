<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PasswordPolicyBundle\Concurrency\StoredPasswordLock;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\Exceptions\PasswordChangeConflictException;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost\XmlHostUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\TransientFailureKind;

use function array_map;
use function getenv;
use function is_string;
use function sort;
use function str_starts_with;
use function strval;

use const PHP_VERSION_ID;

/**
 * REQ-RUNTIME-003: two password changes of one user on two real connections (two entity managers, two sessions), on
 * the database of DATABASE_URL (MySQL 8.4, MariaDB 11.4, PostgreSQL 16 in CI; SQLite in memory runs the
 * single-connection cases only). Every session sets a 1 s lock timeout, so a hang fails fast.
 *
 * Both requests load the user before either commits, so both validated the new password against the same history.
 * Without the lock the later commit overwrites the first one and its password never reaches the history (the reuse
 * rule can then be bypassed); with it the later flush fails with a lock conflict and writes nothing.
 */
final class PasswordChangeRaceDoctrineTest extends UnitTestCase
{
    /** @var list<EntityManagerInterface> */
    private array $managers = [];

    private string $url = '';

    protected function setUp(): void
    {
        parent::setUp();

        $url       = getenv('DATABASE_URL');
        $this->url = is_string($url) ? $url : '';

        $setup    = $this->manager(false);
        $schema   = new SchemaTool($setup);
        $metadata = $setup->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        // The setup manager stays open: it is the observer (and, on SQLite, the shared in-memory database).
        $setup->persist(new XmlHostUser('u-1', 'hash-0'));
        $setup->flush();
        $setup->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->managers as $manager) {
            $manager->getConnection()->close();
        }
        $this->managers = [];
        parent::tearDown();
    }

    public function testLaterConcurrentChangeFailsWithAConflictAndWritesNothing(): void
    {
        $this->requiresTwoConnections();
        [$first, $second] = [$this->manager(true), $this->manager(true)];
        $userA            = $this->load($first);
        $userB            = $this->load($second);

        $userA->changePassword('hash-1');
        $first->flush();

        $userB->changePassword('hash-2');
        try {
            $second->flush();
            self::fail('The later change must meet the first one\'s committed password.');
        } catch (PasswordChangeConflictException $passwordChangeConflictException) {
            self::assertSame(XmlHostUser::class, $passwordChangeConflictException->subjectClass);
            self::assertSame(PasswordChangeConflictException::MESSAGE, $passwordChangeConflictException->getMessage());
            // Nothing committed: the client may simply retry (rule 6).
            self::assertSame(ConflictCode::RetrySafe, ConflictCode::forFailure($passwordChangeConflictException));
        }

        self::assertSame('hash-1', $this->storedPassword());
        self::assertSame(['hash-0'], $this->historyHashes(), 'The failed flush wrote no history row');
        $this->assertConnectionUsable($second);

        // The retry loads the current state: its change is validated against, and recorded after, hash-1.
        $retry = $this->manager(true);
        $user  = $this->load($retry);
        $user->changePassword('hash-2');
        $retry->flush();

        self::assertSame('hash-2', $this->storedPassword());
        self::assertSame(['hash-0', 'hash-1'], $this->historyHashes());
    }

    public function testWithoutTheLockTheEarlierPasswordNeverReachesTheHistory(): void
    {
        $this->requiresTwoConnections();
        [$first, $second] = [$this->manager(false), $this->manager(false)];
        $userA            = $this->load($first);
        $userB            = $this->load($second);

        $userA->changePassword('hash-1');
        $first->flush();
        $userB->changePassword('hash-2');
        $second->flush();

        // The behaviour password_change_lock: false keeps: hash-1 was the password, yet it is not remembered.
        self::assertSame('hash-2', $this->storedPassword());
        self::assertSame(['hash-0', 'hash-0'], $this->historyHashes());
    }

    public function testLockSerialisesOverlappingFlushes(): void
    {
        $this->requiresTwoConnections();
        [$first, $second] = [$this->manager(true), $this->manager(true)];
        $userA            = $this->load($first);
        $userB            = $this->load($second);

        // A's flush runs inside an open transaction: its row lock is held until A commits.
        $first->getConnection()->beginTransaction();
        $userA->changePassword('hash-1');
        $first->flush();

        $userB->changePassword('hash-2');
        try {
            $second->flush();
            self::fail('B must wait for A\'s row lock and time out.');
        } catch (DriverException $driverException) {
            // MySQL / MariaDB 1205 (LockWaitTimeoutException), PostgreSQL 55P03 (a plain DriverException in DBAL).
            self::assertSame(TransientFailureKind::LockWaitTimeout, (new TransientFailureClassifier())->classify($driverException));
        }

        $first->getConnection()->commit();
        $this->assertConnectionUsable($second);

        self::assertSame('hash-1', $this->storedPassword());
        self::assertSame(['hash-0'], $this->historyHashes(), 'B wrote nothing');
    }

    public function testSequentialChangesInOneManagerAreNotConflicts(): void
    {
        $manager = $this->manager(true);
        $user    = $this->load($manager);

        $user->changePassword('hash-1');
        $manager->flush();
        $user->changePassword('hash-2');
        $manager->flush();

        $manager->persist(new XmlHostUser('u-2', 'other-0'));
        $manager->flush();

        self::assertSame('hash-2', $this->storedPassword());
        self::assertSame(['hash-0', 'hash-1'], $this->historyHashes());
    }

    public function testPasswordChangedBehindTheManagerIsAConflict(): void
    {
        // One session: another writer's committed change is simulated with a direct UPDATE (every platform).
        $manager = $this->manager(true);
        $user    = $this->load($manager);
        $manager->getConnection()->update('pp_xml_user', ['password' => 'hash-other'], ['id' => 'u-1']);

        $user->changePassword('hash-1');
        try {
            $manager->flush();
            self::fail('The stored password no longer matches the loaded one.');
        } catch (PasswordChangeConflictException) {
        }

        self::assertSame('hash-other', $this->storedPassword());
        self::assertSame([], $this->historyHashes());
    }

    public function testDeletedRowIsAConflict(): void
    {
        if (!$this->onSqlite()) {
            // Real databases enforce the history row's foreign key first (a plain error, not a conflict).
            self::markTestSkipped('The row-gone branch is reachable only without foreign key enforcement (SQLite).');
        }

        $manager = $this->manager(true);
        $user    = $this->load($manager);
        $manager->getConnection()->delete('pp_xml_user', ['id' => 'u-1']);

        $user->changePassword('hash-1');
        $this->expectException(PasswordChangeConflictException::class);
        $manager->flush();
    }

    private function manager(bool $lock): EntityManagerInterface
    {
        $config = ORMSetup::createXMLMetadataConfiguration([__DIR__ . '/XmlHost/mapping'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $params = $this->url !== ''
            ? (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite']))->parse($this->url)
            : ['driver' => 'pdo_sqlite', 'memory' => true];

        // One in-memory SQLite database is shared through one connection; real databases get one session per manager.
        $connection = $this->url === '' && $this->managers !== []
            ? $this->managers[0]->getConnection()
            : DriverManager::getConnection($params, $config);
        $this->setLockTimeout($connection);

        // Own event manager: on SQLite the managers share one connection (and DBAL 3 its event manager).
        $manager  = new EntityManager($connection, $config, new EventManager());
        $listener = new PasswordEntityListener(
            new PasswordHistoryService(),
            'password',
            'passwordHistory',
            5,
            XmlHostUser::class,
            null,
            false,
            storedPasswordLock: $lock ? new StoredPasswordLock() : null,
        );
        $manager->getEventManager()->addEventListener([Events::onFlush, Events::postFlush, Events::preUpdate], $listener);

        return $this->managers[] = $manager;
    }

    private function setLockTimeout(Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $connection->executeStatement("SET lock_timeout = '1s'");
        }
    }

    private function requiresTwoConnections(): void
    {
        if ($this->onSqlite()) {
            self::markTestSkipped('Two sessions need a real database (DATABASE_URL: MySQL, MariaDB, PostgreSQL).');
        }
    }

    /**
     * The failed flush rolled its transaction back: the session is usable at once (the ORM closed that manager, as
     * after any failed flush; the host replaces it, never this bundle).
     */
    private function assertConnectionUsable(EntityManagerInterface $manager): void
    {
        self::assertFalse($manager->isOpen());
        self::assertFalse($manager->getConnection()->isTransactionActive());
        self::assertSame(1, (int) $manager->getConnection()->fetchOne('SELECT 1'));
    }

    private function onSqlite(): bool
    {
        return $this->url === '' || str_starts_with($this->url, 'sqlite');
    }

    private function load(EntityManagerInterface $manager): XmlHostUser
    {
        $user = $manager->find(XmlHostUser::class, 'u-1');
        self::assertInstanceOf(XmlHostUser::class, $user);
        $user->getPasswordHistory()->count();

        return $user;
    }

    private function storedPassword(): string
    {
        return (string) $this->observer()->fetchOne('SELECT password FROM pp_xml_user WHERE id = ?', ['u-1']);
    }

    /**
     * @return list<string>
     */
    private function historyHashes(): array
    {
        $hashes = array_map(strval(...), $this->observer()->fetchFirstColumn('SELECT password FROM pp_xml_password_history'));
        sort($hashes);

        return $hashes;
    }

    private function observer(): Connection
    {
        return $this->managers[0]->getConnection();
    }
}
