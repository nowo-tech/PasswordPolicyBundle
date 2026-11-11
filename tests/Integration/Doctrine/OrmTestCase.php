<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use DateTime;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PasswordPolicyBundle\History\DoctrinePasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

use function getenv;
use function is_string;

use const PHP_VERSION_ID;

/**
 * Real Doctrine ORM on SQLite (in memory) by default, or on the database of DATABASE_URL (MySQL, PostgreSQL), e.g.
 * `DATABASE_URL=mysql://root@mysql:3306/pp_test` (passwordless CI service).
 */
abstract class OrmTestCase extends UnitTestCase
{
    protected EntityManagerInterface $em;

    protected ManagerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $url    = getenv('DATABASE_URL');
        $params = is_string($url) && $url !== ''
            ? (new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite']))->parse($url)
            : ['driver' => 'pdo_sqlite', 'memory' => true];

        $this->em = new EntityManager(DriverManager::getConnection($params, $config), $config);
        $schema   = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        // Not a Mockery mock: mocks record every call, which would skew the worker-mode memory check.
        $this->registry = new SingleManagerRegistry($this->em);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
        parent::tearDown();
    }

    protected function repository(): DoctrinePasswordHistoryRepository
    {
        return new DoctrinePasswordHistoryRepository($this->registry, new PasswordHistoryService(), [ErasureUser::class => 'passwordHistory']);
    }

    protected function isMySql(): bool
    {
        return $this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform;
    }

    /**
     * Persists a user with one history row per date and clears the unit of work.
     */
    protected function seedUser(string $id, string ...$dates): void
    {
        $user = new ErasureUser($id);
        foreach ($dates as $date) {
            $history = new ErasurePasswordHistory(new DateTime($date));
            $history->setPassword('REDACTED-TEST-' . $id);
            $user->addPasswordHistory($history);
        }
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();
    }

    protected function historyRows(string $userId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pp_gdpr_password_history WHERE user_id = ?', [$userId]);
    }

    protected function totalHistoryRows(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pp_gdpr_password_history');
    }
}
