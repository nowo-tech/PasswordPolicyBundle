<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\EventListener;

use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
// attributes
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
//
use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;

#[AsDoctrineListener(event: Events::onFlush, priority: 500, connection: 'default')]
class PasswordEntityListener
{
  private array $processedPasswords = [];

  /**
   * PasswordEntityListener constructor.
   */
  public function __construct(public PasswordHistoryServiceInterface $passwordHistoryService, private readonly string $passwordField, private readonly string $passwordHistoryField, private readonly int $historyLimit, private readonly string $entityClass)
  {
  }

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
 * The function `createPasswordHistory` creates a new password history entry for a given entity,
 * storing the old password and associating it with the entity.
 *
 * @param EntityManagerInterface em EntityManagerInterface object, used for managing entities and
 * performing database operations.
 * @param HasPasswordPolicyInterface entity The `entity` parameter is an object that implements the
 * `HasPasswordPolicyInterface` interface. It represents the entity for which the password history is
 * being created.
 * @param oldPassword The `oldPassword` parameter is a nullable string that represents the previous
 * password of the entity. If it is null or an empty string, the method will use the current password
 * of the entity.
 *
 * @return ?PasswordHistoryInterface an instance of the PasswordHistoryInterface or null.
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

    $hasPasswordPolicy->setPasswordChangedAt(Carbon::now());
    // We need to recompute the change set so we won't trigger updates instead of inserts.
    $unitOfWork->recomputeSingleEntityChangeSet($entityMeta, $hasPasswordPolicy);

    return $history;
  }
}
