<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Concurrency;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Mockery;
use Nowo\PasswordPolicyBundle\Concurrency\StoredPasswordLock;
use Nowo\PasswordPolicyBundle\Exceptions\PasswordChangeConflictException;
use Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost\XmlHostUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

use function dirname;

use const PHP_VERSION_ID;

/**
 * The row-lock clause per platform. The real-database behaviour is in PasswordChangeRaceDoctrineTest.
 */
final class StoredPasswordLockTest extends UnitTestCase
{
    private EntityManagerInterface $real;

    private XmlHostUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $config = ORMSetup::createXMLMetadataConfiguration([dirname(__DIR__, 2) . '/Integration/Doctrine/XmlHost/mapping'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $this->real = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->real))->createSchema($this->real->getMetadataFactory()->getAllMetadata());
        $this->user = new XmlHostUser('u-1', 'hash-0');
        $this->real->persist($this->user);
        $this->real->flush();
    }

    protected function tearDown(): void
    {
        $this->real->getConnection()->close();
        parent::tearDown();
    }

    public function testPostgreSqlTakesANoKeyUpdateLock(): void
    {
        $sql = $this->lockedSql(new PostgreSQLPlatform(), ['hash-0']);

        self::assertStringEndsWith(' FOR NO KEY UPDATE', $sql);
    }

    public function testMySqlTakesAForUpdateLock(): void
    {
        $sql = $this->lockedSql(new MySQL80Platform(), ['hash-0']);

        self::assertStringEndsWith(' FOR UPDATE', $sql);
        self::assertStringNotContainsString('NO KEY', $sql);
    }

    public function testAnotherStoredValueIsAConflict(): void
    {
        $this->expectException(PasswordChangeConflictException::class);
        $this->lockedSql(new PostgreSQLPlatform(), ['hash-other']);
    }

    /**
     * @param list<string> $rows
     */
    private function lockedSql(PostgreSQLPlatform|MySQL80Platform $platform, array $rows): string
    {
        $executed   = '';
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);
        $connection->shouldReceive('createQueryBuilder')->andReturnUsing(static fn (): QueryBuilder => new QueryBuilder($connection));
        $connection->shouldReceive('fetchFirstColumn')->once()->andReturnUsing(static function (string $sql) use (&$executed, $rows): array {
            $executed = $sql;

            return $rows;
        });

        $manager = Mockery::mock(EntityManagerInterface::class);
        $manager->shouldReceive('getConnection')->andReturn($connection);
        $manager->shouldReceive('getClassMetadata')->andReturnUsing(fn (string $class): ClassMetadata => $this->real->getClassMetadata($class));
        $manager->shouldReceive('getConfiguration')->andReturn($this->real->getConfiguration());
        $manager->shouldReceive('getUnitOfWork')->andReturn($this->real->getUnitOfWork());

        (new StoredPasswordLock())->assertUnchanged($manager, $this->user, 'password', 'hash-0');

        return $executed;
    }
}
