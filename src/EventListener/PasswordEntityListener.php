<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Doctrine event listener for managing password history and password change timestamps.
 *
 * This listener listens to Doctrine's onFlush event and automatically creates password
 * history entries when a password is changed, and updates the passwordChangedAt timestamp.
 */
#[AsDoctrineListener(event: Events::onFlush, priority: 500, connection: 'default')]
class PasswordEntityListener
{
  /**
   * Array of processed passwords to avoid duplicate history entries.
   *
   * @var array<string, PasswordHistoryInterface>
   */
  private array $processedPasswords = [];

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
    private readonly ?PasswordExpiryServiceInterface $passwordExpiryService = null
  )
  {
  }

  /**
   * Handles the Doctrine onFlush event to detect password changes.
   *
   * @param OnFlushEventArgs $onFlushEventArgs The event arguments containing entity manager and unit of work
   * @return void
   */
  #[ORM\OnFlush]
  public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
  {
    $em = $onFlushEventArgs->getObjectManager();
    $unitOfWork = $em->getUnitOfWork();
    //
    foreach ($unitOfWork->getIdentityMap() as $entities) {
      foreach($entities as $entity){
        if (is_a($entity, $this->entityClass, true) && $entity instanceof HasPasswordPolicyInterface) {
          $changeSet = $unitOfWork->getEntityChangeSet($entity);
          if (array_key_exists($this->passwordField, $changeSet) && array_key_exists(
            0,
            $changeSet[$this->passwordField]
          )) {
            $this->createPasswordHistory($em, $entity, $changeSet[$this->passwordField][0]);
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
   * @return PasswordHistoryInterface|null The created password history entry or null if creation failed
   */
  public function createPasswordHistory(
    EntityManagerInterface $entityManager,
    HasPasswordPolicyInterface $hasPasswordPolicy,
    ?string $oldPassword
  ): ?PasswordHistoryInterface {
    if (is_null($oldPassword) || $oldPassword === '') {
      $oldPassword = $hasPasswordPolicy->getPassword();
    }

    //
    if ($oldPassword === '' || $oldPassword === '0') {
      return null;
    }

    //
    if (array_key_exists($oldPassword, $this->processedPasswords)) {
      return null;
    }

    //
    $unitOfWork = $entityManager->getUnitOfWork();
    $entityMeta = $entityManager->getClassMetadata($hasPasswordPolicy::class);
    //
    $historyClass = $entityMeta->associationMappings[$this->passwordHistoryField]['targetEntity'];
    $mappedField = $entityMeta->associationMappings[$this->passwordHistoryField]['mappedBy'];
    //
    $history = new $historyClass();
    // Check if the history class implements the PasswordHistoryInterface interface.
    if (!$history instanceof PasswordHistoryInterface) {
      throw new RuntimeException(sprintf(
        '%s must implement %s',
        $historyClass,
        PasswordHistoryInterface::class
      ));
    }

    //
    $userSetter = 'set' . ucfirst((string) $mappedField);
    // Check if the history class has a setter method for the user relation.
    if (!method_exists($history, $userSetter)) {
      throw new RuntimeException(sprintf(
        'Cannot set user relation in password history class %s because method %s is missing',
        $historyClass,
        $userSetter
      ));
    }

    //
    $history->$userSetter($hasPasswordPolicy);
    $history->setPassword($oldPassword);
    $history->setCreatedAt(Carbon::now());
    // $history->setSalt($entity->getSalt());
    //
    $hasPasswordPolicy->addPasswordHistory($history);

    $this->processedPasswords[$oldPassword] = $history;

    $stalePasswords = $this->passwordHistoryService->getHistoryItemsForCleanup($hasPasswordPolicy, $this->historyLimit);

    foreach ($stalePasswords as $stalePassword) {
      $entityManager->remove($stalePassword);
    }

    $entityManager->persist($history);

    $metadata = $entityManager->getClassMetadata($historyClass);
    $unitOfWork->computeChangeSet($metadata, $history);

    $changedAt = Carbon::now();
    $hasPasswordPolicy->setPasswordChangedAt($changedAt);
    // We need to recompute the change set so we won't trigger updates instead of inserts.
    $unitOfWork->recomputeSingleEntityChangeSet($entityMeta, $hasPasswordPolicy);

    // Invalidate cache if enabled
    if ($this->passwordExpiryService && method_exists($this->passwordExpiryService, 'invalidateCache')) {
      $this->passwordExpiryService->invalidateCache($hasPasswordPolicy);
    }

    // Dispatch events
    if ($this->eventDispatcher) {
      // Dispatch PasswordHistoryCreatedEvent
      $historyEvent = new PasswordHistoryCreatedEvent($hasPasswordPolicy, $history, count($stalePasswords));
      $this->eventDispatcher->dispatch($historyEvent);
      
      // Dispatch PasswordChangedEvent
      $changedEvent = new PasswordChangedEvent($hasPasswordPolicy, $changedAt);
      $this->eventDispatcher->dispatch($changedEvent);
    }

    // Log password change
    if ($this->enableLogging && $this->logger) {
      $userId = method_exists($hasPasswordPolicy, 'getId') ? $hasPasswordPolicy->getId() : 'unknown';
      $this->log($this->logLevel, 'Password changed successfully', [
        'user_id' => $userId,
        'entity_class' => $hasPasswordPolicy::class,
        'history_entries_removed' => count($stalePasswords),
      ]);
    }

    return $history;
  }

  /**
   * Logs a message with the configured log level.
   *
   * @param string $level The log level (debug, info, notice, warning, error)
   * @param string $message The log message
   * @param array $context Additional context data
   * @return void
   */
  private function log(string $level, string $message, array $context = []): void
  {
    if (!$this->logger) {
      return;
    }

    $context['bundle'] = 'PasswordPolicyBundle';
    
    match ($level) {
      'debug' => $this->logger->debug($message, $context),
      'info' => $this->logger->info($message, $context),
      'notice' => $this->logger->notice($message, $context),
      'warning' => $this->logger->warning($message, $context),
      'error' => $this->logger->error($message, $context),
      default => $this->logger->info($message, $context),
    };
  }
}
