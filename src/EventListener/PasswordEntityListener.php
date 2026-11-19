<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Nowo\PasswordPolicyBundle\Concurrency\StoredPasswordLock;
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Nowo\PasswordPolicyBundle\Exceptions\PasswordChangeConflictException;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Util\SubjectId;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function array_values;
use function count;
use function is_array;
use function spl_object_id;
use function sprintf;

/**
 * Doctrine event listener for managing password history and password change timestamps.
 *
 * This listener listens to Doctrine's onFlush event and automatically creates password
 * history entries when a password is changed, and updates the passwordChangedAt timestamp.
 * It dispatches nothing inside the flush (REQ-ARCH-001 rule 7): on postFlush, after the commit, it dispatches for each
 * change PasswordHistoryCreatedEvent, PasswordChangedEvent (since 1.12.0; before, inside onFlush) and
 * PasswordChangeRecordedEvent (scalar ids). A failed flush dispatches none of them. Do not flush from a postFlush
 * listener that runs before this one: the nested flush starts a new change set and the outer flush's events are not
 * dispatched.
 *
 * It is the default history adapter; set history_listener: false on an entity to record the history yourself
 * through PasswordHistoryRecorderInterface.
 *
 * Concurrency (REQ-RUNTIME-003): the history rows join the host's own flush, so they commit or roll back with the
 * password change. With a {@see StoredPasswordLock} (`password_change_lock`, on by default), preUpdate locks the
 * subject's row inside that flush and fails it with {@see PasswordChangeConflictException} when another request
 * changed the stored password after this one loaded it (the reuse check ran against a stale history).
 */
class PasswordEntityListener implements ResetInterface
{
    /**
     * Entity/old-password pairs already handled in the current flush, to avoid duplicate history entries.
     * Cleared at the start of every flush and at every request / message / console boundary
     * ({@see PasswordPolicyWorkerBoundaryListener}), so nothing survives into the next unit of a long-running worker.
     *
     * @var array<string, true>
     */
    private array $processedPasswords = [];

    /**
     * Changes recorded in the current flush, dispatched on postFlush (after commit): PasswordHistoryCreatedEvent,
     * PasswordChangedEvent and PasswordChangeRecordedEvent.
     * Cleared at the start of every flush, so the changes of a failed flush are never announced, and at every boundary
     * ({@see PasswordPolicyWorkerBoundaryListener}), so a failed flush's users are not kept until the next flush.
     *
     * @var list<array{HasPasswordPolicyInterface, DateTimeInterface, int, PasswordHistoryInterface}>
     */
    private array $pendingChanges = [];

    /**
     * Password value each changed subject had when it was loaded, checked against the stored row in preUpdate.
     * Keyed by object id, cleared like {@see self::$pendingChanges}.
     *
     * @var array<int, array{HasPasswordPolicyInterface, mixed}>
     */
    private array $expectedPasswords = [];

    /**
     * Entity managers whose flush changed a password and never reached postFlush (it failed: a conflict, a deadlock,
     * a lock-wait timeout). A failed flush closes the manager; {@see PasswordPolicyWorkerBoundaryListener} replaces
     * the closed ones at the next boundary, so one failed password change cannot break a worker that runs without
     * kernel reset. Kept across flushes (only a successful postFlush or {@see self::takeFailedFlushManagers()} drops
     * an entry); cleared by {@see self::reset()}.
     *
     * @var array<int, EntityManagerInterface>
     */
    private array $failedFlushManagers = [];

    /** Manager of the flush in progress when it changed a password (moved out on postFlush). */
    private ?EntityManagerInterface $flushingManager = null;

    /**
     * PasswordEntityListener constructor.
     *
     * @param PasswordHistoryServiceInterface $passwordHistoryService The service for managing password history cleanup
     * @param string $passwordField The name of the password field in the entity
     * @param string $passwordHistoryField The name of the password history collection field in the entity
     * @param int $historyLimit The maximum number of password history entries to keep
     * @param string $entityClass The fully qualified class name of the entity this listener handles
     * @param LoggerInterface|null $logger The logger service (optional, uses NullLogger if not provided)
     * @param bool $enableLogging Whether logging is enabled
     * @param string $logLevel The logging level to use
     * @param EventDispatcherInterface|null $eventDispatcher The event dispatcher (optional)
     * @param PasswordExpiryServiceInterface|null $passwordExpiryService The password expiry service for cache invalidation (optional)
     * @param StoredPasswordLock|null $storedPasswordLock Compare-and-set of the password change in preUpdate (null = off)
     */
    public function __construct(
        public PasswordHistoryServiceInterface $passwordHistoryService,
        private readonly string $passwordField,
        private readonly string $passwordHistoryField,
        private readonly int $historyLimit,
        private readonly string $entityClass,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $enableLogging = true,
        private readonly string $logLevel = 'info',
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?PasswordExpiryServiceInterface $passwordExpiryService = null,
        private readonly ?ClockInterface $clock = null,
        private readonly ?StoredPasswordLock $storedPasswordLock = null,
    ) {
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }

    /**
     * Handles the Doctrine onFlush event to detect password changes.
     *
     * @param OnFlushEventArgs $onFlushEventArgs The event arguments containing entity manager and unit of work
     */
    public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
    {
        $this->resetFlushState();

        $em         = $onFlushEventArgs->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        // New entities are not in the identity map yet (IDENTITY ids): stamp passwordChangedAt at creation, otherwise
        // their password would never expire until the first change.
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (is_a($entity, $this->entityClass, true) && $entity instanceof HasPasswordPolicyInterface
                && !$entity->getPasswordChangedAt() instanceof DateTime) {
                $entity->setPasswordChangedAt(Carbon::instance($this->now()));
                $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
            }
        }

        foreach ($unitOfWork->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if (is_a($entity, $this->entityClass, true) && $entity instanceof HasPasswordPolicyInterface) {
                    $changeSet      = $unitOfWork->getEntityChangeSet($entity);
                    $passwordChange = $changeSet[$this->passwordField] ?? null;
                    if (is_array($passwordChange) && isset($passwordChange[0])) {
                        $this->flushingManager                         = $em;
                        $this->failedFlushManagers[spl_object_id($em)] = $em;
                        if ($this->storedPasswordLock instanceof StoredPasswordLock) {
                            $this->expectedPasswords[spl_object_id($entity)] = [$entity, $passwordChange[0]];
                        }
                        $this->createPasswordHistory($em, $entity, $passwordChange[0]);
                    }
                }
            }
        }
    }

    /**
     * Creates a new password history entry for the given entity.
     *
     * This method stores the old password in the password history and associates it
     * with the entity. It also updates the passwordChangedAt timestamp and manages
     * the history limit by removing old entries.
     *
     * @param EntityManagerInterface $entityManager The entity manager for persisting the history entry
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity for which to create password history
     * @param string|null $oldPassword The old password to store. If null or empty, uses the current password
     *
     * @return PasswordHistoryInterface|null The created password history entry or null if creation failed
     */
    public function createPasswordHistory(
        EntityManagerInterface $entityManager,
        HasPasswordPolicyInterface $hasPasswordPolicy,
        ?string $oldPassword
    ): ?PasswordHistoryInterface {
        if ($oldPassword === null || $oldPassword === '') {
            $oldPassword = $hasPasswordPolicy->getPassword();
        }

        if ($oldPassword === '' || $oldPassword === '0') {
            return null;
        }

        $processedKey = spl_object_id($hasPasswordPolicy) . "\0" . $oldPassword;
        if (array_key_exists($processedKey, $this->processedPasswords)) {
            return null;
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        $entityMeta = $entityManager->getClassMetadata($hasPasswordPolicy::class);

        $historyClass = $entityMeta->associationMappings[$this->passwordHistoryField]['targetEntity'];
        $mappedField  = $entityMeta->associationMappings[$this->passwordHistoryField]['mappedBy'];

        $history = new $historyClass();
        // Check if the history class implements the PasswordHistoryInterface interface.
        if (!$history instanceof PasswordHistoryInterface) {
            throw new RuntimeException(sprintf('%s must implement %s', $historyClass, PasswordHistoryInterface::class));
        }

        $userSetter = 'set' . ucfirst((string) $mappedField);
        // Check if the history class has a setter method for the user relation.
        if (!method_exists($history, $userSetter)) {
            throw new RuntimeException(sprintf('Cannot set user relation in password history class %s because method %s is missing', $historyClass, $userSetter));
        }

        $history->$userSetter($hasPasswordPolicy);
        $history->setPassword($oldPassword);
        $history->setCreatedAt(Carbon::instance($this->now()));
        // $history->setSalt($entity->getSalt());
        //
        $hasPasswordPolicy->addPasswordHistory($history);

        $this->processedPasswords[$processedKey] = true;

        $stalePasswords = $this->passwordHistoryService->getHistoryItemsForCleanup($hasPasswordPolicy, $this->historyLimit);

        foreach ($stalePasswords as $stalePassword) {
            $entityManager->remove($stalePassword);
        }

        $entityManager->persist($history);

        $metadata = $entityManager->getClassMetadata($historyClass);
        $unitOfWork->computeChangeSet($metadata, $history);

        $changedAt = Carbon::instance($this->now());
        $hasPasswordPolicy->setPasswordChangedAt($changedAt);
        // We need to recompute the change set so we won't trigger updates instead of inserts.
        $unitOfWork->recomputeSingleEntityChangeSet($entityMeta, $hasPasswordPolicy);

        // Invalidate cache if enabled
        if ($this->passwordExpiryService instanceof PasswordExpiryServiceInterface) {
            $this->passwordExpiryService->invalidateCache($hasPasswordPolicy);
        }

        // Events are dispatched on postFlush, after the commit (REQ-ARCH-001 rule 7), never inside this flush.
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $this->pendingChanges[] = [$hasPasswordPolicy, $changedAt, count($stalePasswords), $history];
        }

        // Log password change
        if ($this->enableLogging && $this->logger instanceof LoggerInterface) {
            $userId = $hasPasswordPolicy->getId();
            $this->log($this->logLevel, 'Password changed successfully', [
                'user_id'                 => $userId,
                'entity_class'            => $hasPasswordPolicy::class,
                'history_entries_removed' => count($stalePasswords),
            ]);
        }

        return $history;
    }

    /**
     * Inside the flush transaction, right before the host's UPDATE of a subject whose password changed: locks its row
     * and checks that the stored password is still the one this request loaded.
     *
     * @throws PasswordChangeConflictException When another request changed it first (the flush rolls back)
     */
    public function preUpdate(PreUpdateEventArgs $preUpdateEventArgs): void
    {
        $entity = $preUpdateEventArgs->getObject();
        $key    = spl_object_id($entity);
        if (!$this->storedPasswordLock instanceof StoredPasswordLock || !isset($this->expectedPasswords[$key])
            || $this->expectedPasswords[$key][0] !== $entity) {
            return;
        }

        $expected = $this->expectedPasswords[$key][1];
        unset($this->expectedPasswords[$key]);

        $this->storedPasswordLock->assertUnchanged($preUpdateEventArgs->getObjectManager(), $entity, $this->passwordField, $expected);
    }

    /**
     * Dispatches, for each change of the flush that just committed and in order: PasswordHistoryCreatedEvent,
     * PasswordChangedEvent, then PasswordChangeRecordedEvent (scalar ids).
     */
    public function postFlush(): void
    {
        $pendingChanges          = $this->pendingChanges;
        $this->pendingChanges    = [];
        $this->expectedPasswords = [];
        if ($this->flushingManager instanceof EntityManagerInterface) {
            // The flush committed: its manager is open.
            unset($this->failedFlushManagers[spl_object_id($this->flushingManager)]);
            $this->flushingManager = null;
        }

        if (!$this->eventDispatcher instanceof EventDispatcherInterface) {
            return;
        }

        foreach ($pendingChanges as [$user, $changedAt, $removedEntriesCount, $history]) {
            $this->eventDispatcher->dispatch(new PasswordHistoryCreatedEvent($user, $history, $removedEntriesCount));
            $this->eventDispatcher->dispatch(new PasswordChangedEvent($user, $changedAt));
            $this->eventDispatcher->dispatch(new PasswordChangeRecordedEvent(
                SubjectId::classOf($user),
                SubjectId::of($user->getId()),
                DateTimeImmutable::createFromInterface($changedAt),
                $removedEntriesCount,
            ));
        }
    }

    public function reset(): void
    {
        // Every property spelled out (Igor checks reset() itself); resetFlushState() keeps $failedFlushManagers.
        $this->processedPasswords  = [];
        $this->pendingChanges      = [];
        $this->expectedPasswords   = [];
        $this->flushingManager     = null;
        $this->failedFlushManagers = [];
    }

    /**
     * Returns and forgets the entity managers whose password-changing flush failed since the last call (closed ones
     * need replacing; see {@see PasswordPolicyWorkerBoundaryListener}).
     *
     * @return list<EntityManagerInterface>
     */
    public function takeFailedFlushManagers(): array
    {
        $managers                  = array_values($this->failedFlushManagers);
        $this->failedFlushManagers = [];

        return $managers;
    }

    private function resetFlushState(): void
    {
        $this->processedPasswords = [];
        $this->pendingChanges     = [];
        $this->expectedPasswords  = [];
        $this->flushingManager    = null;
    }

    /**
     * Logs a message with the configured log level.
     *
     * @param string $level The log level (debug, info, notice, warning, error)
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        $context['bundle'] = 'PasswordPolicyBundle';

        match ($level) {
            'debug'   => $this->logger->debug($message, $context),
            'info'    => $this->logger->info($message, $context),
            'notice'  => $this->logger->notice($message, $context),
            'warning' => $this->logger->warning($message, $context),
            'error'   => $this->logger->error($message, $context),
            default   => $this->logger->info($message, $context),
        };
    }
}
