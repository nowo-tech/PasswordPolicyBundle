<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Util\SubjectId;

use function array_chunk;
use function array_column;
use function array_map;
use function array_slice;
use function class_exists;
use function count;
use function is_object;
use function is_a;
use function max;
use function method_exists;
use function sprintf;
use function ucfirst;
use function usort;

/**
 * Default {@see PasswordHistoryRepositoryInterface} adapter: the history association of a HasPasswordPolicyInterface
 * Doctrine entity (the rows the onFlush listener writes). It persists and removes, it never flushes.
 *
 * The subject is looked up with EntityManager::find() and kept only when its id, as a string, equals $subjectId
 * byte for byte (a case- or accent-insensitive collation can return a look-alike).
 */
final readonly class DoctrinePasswordHistoryRepository implements ErasablePasswordHistoryRepositoryInterface
{
    /** Rows per DELETE statement in {@see self::removeAll()}. */
    private const DELETE_BATCH_SIZE = 500;

    /**
     * @param array<string, string> $historyFields password_history_field per configured class
     */
    public function __construct(
        private ?ManagerRegistry $managerRegistry,
        private PasswordHistoryServiceInterface $passwordHistoryService,
        private array $historyFields = [],
    ) {
    }

    public function findRecent(string $subjectClass, string $subjectId, int $limit): array
    {
        $entity = $this->find($subjectClass, $subjectId);
        if (!$entity instanceof HasPasswordPolicyInterface || $limit <= 0) {
            return [];
        }

        $entries = $entity->getPasswordHistory()->toArray();
        usort($entries, static fn (PasswordHistoryInterface $a, PasswordHistoryInterface $b): int => self::timestamp($b) <=> self::timestamp($a));

        return array_map(
            static fn (PasswordHistoryInterface $history): PasswordHistoryEntry => new PasswordHistoryEntry($history->getPassword(), $history->getCreatedAt()),
            array_slice($entries, 0, max(0, $limit)),
        );
    }

    public function add(string $subjectClass, string $subjectId, string $passwordHash, DateTimeImmutable $createdAt): void
    {
        $entity = $this->find($subjectClass, $subjectId);
        if (!$entity instanceof HasPasswordPolicyInterface) {
            throw new RuntimeException(sprintf('No %s entity "%s" implementing %s was found. Alias %s to your own adapter when the history is not a Doctrine association.', $subjectClass, $subjectId, HasPasswordPolicyInterface::class, PasswordHistoryRepositoryInterface::class));
        }

        $entityManager = $this->entityManager($subjectClass);
        $field         = $this->historyField($subjectClass);
        $metadata      = $entityManager->getClassMetadata($entity::class);
        if (!isset($metadata->associationMappings[$field])) {
            throw new RuntimeException(sprintf('%s has no "%s" association (password_history_field).', $subjectClass, $field));
        }

        $historyClass = $metadata->associationMappings[$field]['targetEntity'];
        $mappedBy     = (string) $metadata->associationMappings[$field]['mappedBy'];

        $history = new $historyClass();
        if (!$history instanceof PasswordHistoryInterface) {
            throw new RuntimeException(sprintf('%s must implement %s', $historyClass, PasswordHistoryInterface::class));
        }

        $userSetter = 'set' . ucfirst($mappedBy);
        if (!method_exists($history, $userSetter)) {
            throw new RuntimeException(sprintf('Cannot set user relation in password history class %s because method %s is missing', $historyClass, $userSetter));
        }

        $history->$userSetter($entity);
        $history->setPassword($passwordHash);
        $history->setCreatedAt(Carbon::instance($createdAt));
        $entity->addPasswordHistory($history);
        // @igor-ignore - Unit-of-work I/O on the host's entity manager (reset by Doctrine), not service state.
        $entityManager->persist($history);
    }

    public function prune(string $subjectClass, string $subjectId, int $keep): int
    {
        $entity = $this->find($subjectClass, $subjectId);
        if (!$entity instanceof HasPasswordPolicyInterface) {
            return 0;
        }

        $stale         = $this->passwordHistoryService->getHistoryItemsForCleanup($entity, $keep);
        $entityManager = $this->entityManager($subjectClass);
        foreach ($stale as $history) {
            // @igor-ignore - Unit-of-work I/O on the host's entity manager (reset by Doctrine), not service state.
            $entityManager->remove($history);
        }

        return count($stale);
    }

    /**
     * Deletes the subject's history rows with DQL (by primary key, in batches, owner re-checked), never flushes, and
     * takes the deleted entries out of the loaded collection and the unit of work so a later flush does not touch them.
     */
    public function removeAll(string $subjectClass, string $subjectId): int
    {
        $entity = $this->find($subjectClass, $subjectId);
        if (!$entity instanceof HasPasswordPolicyInterface) {
            return 0;
        }

        $entityManager = $this->entityManager($subjectClass);
        $field         = $this->historyField($subjectClass);
        $metadata      = $entityManager->getClassMetadata($entity::class);
        if (!isset($metadata->associationMappings[$field])) {
            throw new RuntimeException(sprintf('%s has no "%s" association (password_history_field).', $subjectClass, $field));
        }

        $historyClass = $metadata->associationMappings[$field]['targetEntity'];
        $mappedBy     = (string) $metadata->associationMappings[$field]['mappedBy'];
        if ($mappedBy === '') {
            throw new RuntimeException(sprintf('%s::$%s must be a OneToMany association (mappedBy) to erase its history.', $subjectClass, $field));
        }

        $historyMetadata = $entityManager->getClassMetadata($historyClass);
        $idField         = $historyMetadata->getSingleIdentifierFieldName();
        $collection      = $entity->getPasswordHistory();

        $rows = [];
        foreach ($collection->toArray() as $history) {
            // Exact owner: the entry must point to this very entity (identity map), not to a look-alike id.
            if (!is_object($history) || $historyMetadata->getFieldValue($history, $mappedBy) !== $entity) {
                continue;
            }

            $id = $historyMetadata->getFieldValue($history, $idField);
            if ($id !== null) {
                $rows[] = [$id, $history];
            }
        }

        $deleted = 0;
        $dql     = sprintf('DELETE FROM %s h WHERE h.%s IN (:ids) AND h.%s = :owner', $historyClass, $idField, $mappedBy);
        foreach (array_chunk($rows, self::DELETE_BATCH_SIZE) as $chunk) {
            $result = $entityManager->createQuery($dql)
                ->setParameter('ids', array_column($chunk, 0))
                ->setParameter('owner', $entity)
                ->execute();
            $deleted += (int) $result;
        }

        foreach ($rows as [, $history]) {
            // The rows are gone: drop them from the loaded collection without scheduling an orphan removal.
            if ($collection instanceof PersistentCollection) {
                $collection->unwrap()->removeElement($history);
            } else {
                $collection->removeElement($history);
            }
            // @igor-ignore - Unit-of-work I/O on the host's entity manager (reset by Doctrine), not service state.
            $entityManager->detach($history);
        }

        if ($collection instanceof PersistentCollection) {
            $collection->takeSnapshot();
        }

        return $deleted;
    }

    private function find(string $subjectClass, string $subjectId): ?object
    {
        // A subject without id (e.g. being registered) has no history; '' is not a valid id for every id type.
        if ($subjectId === '') {
            return null;
        }

        $entity = $this->entityManager($subjectClass)->find($this->className($subjectClass), $subjectId);

        // A case- or accent-insensitive collation also returns look-alikes (UserA for usera): keep exact matches only.
        if ($entity instanceof HasPasswordPolicyInterface && SubjectId::of($entity->getId()) !== $subjectId) {
            return null;
        }

        return $entity;
    }

    private function entityManager(string $subjectClass): EntityManagerInterface
    {
        $entityManager = $this->managerRegistry?->getManagerForClass($this->className($subjectClass));
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new RuntimeException(sprintf('No Doctrine ORM entity manager handles %s. Alias %s to your own adapter.', $subjectClass, PasswordHistoryRepositoryInterface::class));
        }

        return $entityManager;
    }

    /**
     * password_history_field of the configured class that applies (exact key, then a parent class / interface).
     */
    private function historyField(string $subjectClass): string
    {
        if (isset($this->historyFields[$subjectClass])) {
            return $this->historyFields[$subjectClass];
        }

        foreach ($this->historyFields as $configuredClass => $field) {
            if (is_a($subjectClass, $configuredClass, true)) {
                return $field;
            }
        }

        return Configuration::DEFAULT_PASSWORD_HISTORY_FIELD;
    }

    /**
     * @return class-string
     */
    private function className(string $subjectClass): string
    {
        if (!class_exists($subjectClass)) {
            throw new RuntimeException(sprintf('Class %s not found.', $subjectClass));
        }

        return $subjectClass;
    }

    private static function timestamp(PasswordHistoryInterface $history): int
    {
        $createdAt = $history->getCreatedAt();

        return $createdAt instanceof DateTimeInterface ? (int) $createdAt->format('U') : 0;
    }
}
