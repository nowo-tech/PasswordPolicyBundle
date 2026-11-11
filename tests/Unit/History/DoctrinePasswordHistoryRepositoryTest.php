<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\History;

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\History\DoctrinePasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\PasswordHistoryMock;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use ReflectionProperty;
use stdClass;

final class DoctrinePasswordHistoryRepositoryTest extends UnitTestCase
{
    private EntityManagerInterface&MockInterface $em;

    private KernelUser $user;

    protected function setUp(): void
    {
        $this->user = new KernelUser('current');
        $this->em   = Mockery::mock(EntityManagerInterface::class);
        $this->em->shouldReceive('find')->with(KernelUser::class, '7')->andReturn($this->user)->byDefault();
    }

    private function repository(?ManagerRegistry $registry = null): DoctrinePasswordHistoryRepository
    {
        if (!$registry instanceof ManagerRegistry) {
            $registry = Mockery::mock(ManagerRegistry::class);
            $registry->shouldReceive('getManagerForClass')->with(KernelUser::class)->andReturn($this->em);
        }

        return new DoctrinePasswordHistoryRepository($registry, new PasswordHistoryService(), [KernelUser::class => 'passwordHistory']);
    }

    private function history(string $hash, string $date): PasswordHistoryMock
    {
        $history = new PasswordHistoryMock();
        $history->setPassword($hash);
        $history->setCreatedAt(new DateTime($date));

        return $history;
    }

    /**
     * @param class-string $target
     */
    private function mapHistory(string $target, string $mappedBy = 'user'): void
    {
        $metadata = new ClassMetadata(KernelUser::class);
        $property = new ReflectionProperty($metadata, 'associationMappings');
        $property->setValue($metadata, ['passwordHistory' => ['targetEntity' => $target, 'mappedBy' => $mappedBy]]);
        $this->em->shouldReceive('getClassMetadata')->with(KernelUser::class)->andReturn($metadata);
    }

    public function testFindRecentReturnsNewestEntriesFirst(): void
    {
        $this->user->addPasswordHistory($this->history('h-old', '2025-01-01'));
        $this->user->addPasswordHistory($this->history('h-new', '2026-01-01'));
        $this->user->addPasswordHistory($this->history('h-mid', '2025-06-01'));

        $entries = $this->repository()->findRecent(KernelUser::class, '7', 2);

        self::assertSame(['h-new', 'h-mid'], array_map(static fn (PasswordHistoryEntry $entry): string => $entry->passwordHash, $entries));
        self::assertSame([], $this->repository()->findRecent(KernelUser::class, '7', 0));
    }

    public function testUnknownSubjectHasNoHistory(): void
    {
        $this->em->shouldReceive('find')->with(KernelUser::class, '8')->andReturn(null);

        self::assertSame([], $this->repository()->findRecent(KernelUser::class, '8', 3));
        self::assertSame(0, $this->repository()->prune(KernelUser::class, '8', 3));

        $this->expectException(RuntimeException::class);
        $this->repository()->add(KernelUser::class, '8', 'hash', new DateTimeImmutable());
    }

    public function testAddPersistsAHistoryEntryOfTheAssociation(): void
    {
        $this->mapHistory(PasswordHistoryMock::class);
        $this->em->shouldReceive('persist')->once()->with(Mockery::type(PasswordHistoryMock::class));

        $this->repository()->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable('2026-01-02'));

        $history = $this->user->getPasswordHistory()->first();
        self::assertInstanceOf(PasswordHistoryMock::class, $history);
        self::assertSame('old-hash', $history->getPassword());
        self::assertSame($this->user, $history->getUser());
        self::assertSame('2026-01-02', $history->getCreatedAt()?->format('Y-m-d'));
    }

    public function testAddRejectsInvalidMappings(): void
    {
        $this->mapHistory(stdClass::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement ' . PasswordHistoryInterface::class);
        $this->repository()->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable());
    }

    public function testAddRequiresTheInverseSetter(): void
    {
        $this->mapHistory(PasswordHistoryMock::class, 'owner');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('setOwner is missing');
        $this->repository()->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable());
    }

    public function testAddRequiresTheHistoryAssociation(): void
    {
        $metadata = new ClassMetadata(KernelUser::class);
        $this->em->shouldReceive('getClassMetadata')->andReturn($metadata);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('password_history_field');
        $this->repository()->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable());
    }

    public function testPruneRemovesTheOldestEntries(): void
    {
        $this->user->addPasswordHistory($old = $this->history('h-old', '2025-01-01'));
        $this->user->addPasswordHistory($this->history('h-new', '2026-01-01'));
        $this->em->shouldReceive('remove')->once()->with($old);

        self::assertSame(1, $this->repository()->prune(KernelUser::class, '7', 1));
        self::assertCount(1, $this->user->getPasswordHistory());
    }

    public function testWithoutEntityManagerAliasTheAdapter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No Doctrine ORM entity manager');
        (new DoctrinePasswordHistoryRepository(null, new PasswordHistoryService()))->findRecent(KernelUser::class, '7', 3);
    }

    public function testUnknownClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Class App\Missing not found.');
        $this->repository()->findRecent('App\Missing', '7', 3);
    }

    public function testCollectionIsUnchangedByFindRecent(): void
    {
        $this->user->addPasswordHistory($this->history('h-1', '2025-01-01'));
        $this->repository()->findRecent(KernelUser::class, '7', 1);

        self::assertInstanceOf(ArrayCollection::class, $this->user->getPasswordHistory());
        self::assertCount(1, $this->user->getPasswordHistory());
    }

    public function testSubjectWithoutIdHasNoHistory(): void
    {
        $this->em->shouldNotReceive('find');

        self::assertSame([], $this->repository()->findRecent(KernelUser::class, '', 3));
        self::assertSame(0, $this->repository()->prune(KernelUser::class, '', 3));
    }

    public function testHistoryFieldOfAConfiguredParent(): void
    {
        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldReceive('getManagerForClass')->andReturn($this->em);
        $repository = new DoctrinePasswordHistoryRepository($registry, new PasswordHistoryService(), [HasPasswordPolicyInterface::class => 'oldPasswords']);
        $this->em->shouldReceive('getClassMetadata')->andReturn(new ClassMetadata(KernelUser::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no "oldPasswords" association');
        $repository->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable());
    }

    public function testUnconfiguredClassUsesTheDefaultHistoryField(): void
    {
        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldReceive('getManagerForClass')->andReturn($this->em);
        $this->em->shouldReceive('getClassMetadata')->andReturn(new ClassMetadata(KernelUser::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no "passwordHistory" association');
        (new DoctrinePasswordHistoryRepository($registry, new PasswordHistoryService()))->add(KernelUser::class, '7', 'old-hash', new DateTimeImmutable());
    }

    public function testLookAlikeIdIsNotTheSubject(): void
    {
        // A case- or accent-insensitive collation (or a numeric cast) returns the entity for a different id string.
        $this->em->shouldReceive('find')->with(KernelUser::class, '07')->andReturn($this->user);
        $this->user->addPasswordHistory($this->history('h-1', '2026-01-01'));

        self::assertSame([], $this->repository()->findRecent(KernelUser::class, '07', 3));
        self::assertSame(0, $this->repository()->prune(KernelUser::class, '07', 0));
        self::assertSame(0, $this->repository()->removeAll(KernelUser::class, '07'));
        self::assertCount(1, $this->repository()->findRecent(KernelUser::class, '7', 3));
    }

    public function testRemoveAllOfAnUnknownSubject(): void
    {
        $this->em->shouldReceive('find')->with(KernelUser::class, '8')->andReturn(null);

        self::assertSame(0, $this->repository()->removeAll(KernelUser::class, '8'));
        self::assertSame(0, $this->repository()->removeAll(KernelUser::class, ''));
    }

    public function testRemoveAllRequiresTheHistoryAssociation(): void
    {
        $this->em->shouldReceive('getClassMetadata')->andReturn(new ClassMetadata(KernelUser::class));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('password_history_field');
        $this->repository()->removeAll(KernelUser::class, '7');
    }

    public function testRemoveAllRequiresAOneToManyAssociation(): void
    {
        $this->mapHistory(PasswordHistoryMock::class, '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be a OneToMany association');
        $this->repository()->removeAll(KernelUser::class, '7');
    }
}
