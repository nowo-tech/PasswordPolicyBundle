<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PasswordPolicyBundle\Event\PasswordPolicyPersonalDataErasedEvent;
use Nowo\PasswordPolicyBundle\History\ErasablePasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\ExpiryFlashThrottleStorageInterface;
use Nowo\PasswordPolicyBundle\Service\ExpiryFlash\ForgettableExpiryFlashThrottleStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function class_exists;

/**
 * Default {@see PasswordPolicyPersonalDataEraserInterface}.
 *
 * 1. Password history: {@see ErasablePasswordHistoryRepositoryInterface::removeAll()} of the history port (the default
 *    Doctrine adapter deletes the rows of the subject's history association with DQL, no flush). An adapter that does
 *    not implement it is skipped and logged at error level (the UserKit eraser is not registered for it): your own
 *    store erases its rows.
 * 2. Expiry flash throttle: the subject's entry of a {@see ForgettableExpiryFlashThrottleStorageInterface} (the cache
 *    storage). Session entries end with the user's sessions.
 *
 * Stateless: safe in FrankenPHP worker mode.
 */
final readonly class PasswordPolicyPersonalDataEraser implements PasswordPolicyPersonalDataEraserInterface
{
    /** Namespace prefix of Doctrine's generated proxies: the expiry listener keys the throttle by the runtime class. */
    private const DOCTRINE_PROXY_PREFIX = 'Proxies\__CG__\\';

    public function __construct(
        private PasswordHistoryRepositoryInterface $historyRepository,
        private ?ManagerRegistry $managerRegistry = null,
        private ?ExpiryFlashThrottleStorageInterface $flashThrottleStorage = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function erase(PasswordPolicyPersonalDataSubject $subject): PasswordPolicyErasureResult
    {
        $connection = $this->connection($subject->subjectClass);
        // Own the transaction only when none is open; inside the caller's one, join it and leave the commit to it.
        $ownTransaction = $connection instanceof Connection && !$connection->isTransactionActive();
        $history        = $ownTransaction
            ? (int) $connection->transactional(fn (): int => $this->eraseHistory($subject))
            : $this->eraseHistory($subject);

        $result = new PasswordPolicyErasureResult($history, $this->forgetFlashThrottle($subject));

        // After our own commit only: never inside the caller's transaction (it may still roll back).
        if ($ownTransaction) {
            $this->eventDispatcher?->dispatch(new PasswordPolicyPersonalDataErasedEvent($subject->subjectClass, $subject->subjectId, $result->toArray()));
        }

        return $result;
    }

    private function eraseHistory(PasswordPolicyPersonalDataSubject $subject): int
    {
        if ($this->historyRepository instanceof ErasablePasswordHistoryRepositoryInterface) {
            // @igor-ignore - Deletes through the history port (persistence I/O), no state is kept in this service.
            return $this->historyRepository->removeAll($subject->subjectClass, $subject->subjectId);
        }

        $this->logger?->error('Password history not erased: the history adapter does not implement ErasablePasswordHistoryRepositoryInterface.', [
            'bundle'        => 'PasswordPolicyBundle',
            'entity_class'  => $subject->subjectClass,
            'adapter_class' => $this->historyRepository::class,
        ]);

        return 0;
    }

    private function forgetFlashThrottle(PasswordPolicyPersonalDataSubject $subject): int
    {
        if (!$this->flashThrottleStorage instanceof ForgettableExpiryFlashThrottleStorageInterface) {
            return 0;
        }

        $forgotten = 0;
        foreach ([$subject->subjectClass, self::DOCTRINE_PROXY_PREFIX . $subject->subjectClass] as $class) {
            // @igor-ignore - Shared storage I/O, no state is kept in this service.
            if ($this->flashThrottleStorage->forget($class . ':' . $subject->subjectId)) {
                ++$forgotten;
            }
        }

        return $forgotten;
    }

    private function connection(string $subjectClass): ?Connection
    {
        if (!$this->managerRegistry instanceof ManagerRegistry || !class_exists($subjectClass)) {
            return null;
        }

        $entityManager = $this->managerRegistry->getManagerForClass($subjectClass);

        return $entityManager instanceof EntityManagerInterface ? $entityManager->getConnection() : null;
    }
}
