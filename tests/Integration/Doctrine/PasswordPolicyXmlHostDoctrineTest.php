<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost\XmlHostPasswordHistory;
use Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine\XmlHost\XmlHostUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function getenv;
use function is_string;

use const PHP_VERSION_ID;

/**
 * REQ-ARCH-001 DDD host case: the host entities are plain PHP mapped with Doctrine XML (no ORM attribute, no bundle
 * base class or trait). The default history listener records the history through that mapping, and every event is
 * dispatched after the commit (rule 7): at dispatch time no transaction is open and the history row is visible on
 * the connection. A flush that fails dispatches nothing.
 */
final class PasswordPolicyXmlHostDoctrineTest extends UnitTestCase
{
    private EntityManagerInterface $em;

    private PasswordEntityListener $listener;

    /** @var list<array{class: string, transactionActive: bool, historyRows: int}> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        parent::setUp();

        $config = ORMSetup::createXMLMetadataConfiguration([__DIR__ . '/XmlHost/mapping'], true);
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

        $dispatcher = new EventDispatcher();
        foreach ([PasswordHistoryCreatedEvent::class, PasswordChangedEvent::class, PasswordChangeRecordedEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, function (object $event): void {
                $connection         = $this->em->getConnection();
                $this->dispatched[] = [
                    'class'             => $event::class,
                    'transactionActive' => $connection->isTransactionActive(),
                    'historyRows'       => (int) $connection->fetchOne('SELECT COUNT(*) FROM pp_xml_password_history'),
                ];
            });
        }

        $this->listener = new PasswordEntityListener(
            new PasswordHistoryService(),
            'password',
            'passwordHistory',
            3,
            XmlHostUser::class,
            null,
            false,
            'info',
            $dispatcher,
        );
        $this->em->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], $this->listener);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
        parent::tearDown();
    }

    public function testXmlMappedHostRecordsHistoryAndDispatchesAfterCommit(): void
    {
        $user = new XmlHostUser('u-1', 'hash-1');
        $this->em->persist($user);
        $this->em->flush();
        self::assertNotNull($user->getPasswordChangedAt(), 'passwordChangedAt is stamped on insert through the XML mapping');

        $user->changePassword('hash-2');
        $this->em->flush();

        $dispatched = $this->dispatched();
        // Exactly these three, all from the password change: the insert flush dispatched nothing.
        self::assertSame(
            [PasswordHistoryCreatedEvent::class, PasswordChangedEvent::class, PasswordChangeRecordedEvent::class],
            array_column($dispatched, 'class'),
        );
        foreach ($dispatched as $dispatch) {
            self::assertFalse($dispatch['transactionActive'], $dispatch['class'] . ' is dispatched after the commit');
            self::assertSame(1, $dispatch['historyRows'], $dispatch['class'] . ' sees the committed history row');
        }

        $this->em->clear();
        $reloaded = $this->em->find(XmlHostUser::class, 'u-1');
        self::assertInstanceOf(XmlHostUser::class, $reloaded);
        self::assertCount(1, $reloaded->getPasswordHistory());
        $entry = $reloaded->getPasswordHistory()->first();
        self::assertInstanceOf(XmlHostPasswordHistory::class, $entry);
        self::assertSame('hash-1', $entry->getPassword());
    }

    public function testFailedFlushDispatchesNothing(): void
    {
        $user = new XmlHostUser('u-1', 'hash-1');
        $this->em->persist($user);
        $this->em->flush();
        // A row the unit of work does not know: inserting the same id again fails inside the commit.
        $this->em->getConnection()->insert('pp_xml_user', ['id' => 'u-2', 'password' => 'x']);

        $user->changePassword('hash-2');
        $this->em->persist(new XmlHostUser('u-2', 'y'));

        try {
            $this->em->flush();
            self::fail('The duplicate id must fail the flush.');
        } catch (UniqueConstraintViolationException) {
        }

        self::assertSame([], $this->dispatched(), 'No event for a flush that rolled back');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pp_xml_password_history'));

        // The worker boundary clears the pending changes: a later postFlush announces nothing from the failed flush.
        $this->listener->reset();
        $this->listener->postFlush();
        self::assertSame([], $this->dispatched());
    }

    /**
     * @return list<array{class: string, transactionActive: bool, historyRows: int}>
     */
    private function dispatched(): array
    {
        return $this->dispatched;
    }
}
