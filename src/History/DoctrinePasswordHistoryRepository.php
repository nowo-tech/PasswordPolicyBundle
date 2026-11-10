<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryEntry;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;

use function array_map;
use function array_slice;
use function class_exists;
use function count;
use function max;
use function method_exists;
use function sprintf;
use function ucfirst;
use function usort;

/**
 * Default {@see PasswordHistoryRepositoryInterface} adapter: the history association of a HasPasswordPolicyInterface
 * Doctrine entity (the rows the onFlush listener writes). It persists and removes, it never flushes.
 */
final readonly class DoctrinePasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
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
        $field         = $this->historyFields[$subjectClass] ?? Configuration::DEFAULT_PASSWORD_HISTORY_FIELD;
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

    private function find(string $subjectClass, string $subjectId): ?object
    {
        return $this->entityManager($subjectClass)->find($this->className($subjectClass), $subjectId);
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
