<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\Event\PasswordChangeRecordedEvent;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default {@see PasswordHistoryRecorderInterface}: stores the previous hash through the history port, prunes it to
 * passwords_to_remember and dispatches {@see PasswordChangeRecordedEvent} with scalar ids.
 *
 * It does not flush or commit: call it inside the transaction of your use case (the Doctrine adapter persists, your
 * flush writes). The event is dispatched when record() returns.
 */
final readonly class PasswordHistoryRecorder implements PasswordHistoryRecorderInterface
{
    public function __construct(
        private PasswordHistoryRepositoryInterface $repository,
        private ?PasswordPolicyConfigurationService $configService = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?ClockInterface $clock = null,
        private ?LoggerInterface $logger = null,
        private bool $enableLogging = true,
        private string $logLevel = 'info',
    ) {
    }

    public function record(string $subjectClass, string $subjectId, string $previousHash, ?DateTimeInterface $changedAt = null): int
    {
        if ($previousHash === '' || $previousHash === '0') {
            return 0;
        }

        $changedAt = $changedAt instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($changedAt)
            : ($this->clock?->now() ?? new DateTimeImmutable());
        $limit = (int) ($this->configService?->getEntityConfiguration($subjectClass, 'passwords_to_remember', Configuration::DEFAULT_PASSWORDS_TO_REMEMBER)
            ?? Configuration::DEFAULT_PASSWORDS_TO_REMEMBER);

        // @igor-ignore - Writes through the history port (persistence I/O), no state is kept in this service.
        $this->repository->add($subjectClass, $subjectId, $previousHash, $changedAt);
        $removed = $this->repository->prune($subjectClass, $subjectId, $limit);

        $this->eventDispatcher?->dispatch(new PasswordChangeRecordedEvent($subjectClass, $subjectId, $changedAt, $removed));

        if ($this->enableLogging && $this->logger instanceof LoggerInterface) {
            $context = [
                'user_id'                 => $subjectId,
                'entity_class'            => $subjectClass,
                'history_entries_removed' => $removed,
                'bundle'                  => 'PasswordPolicyBundle',
            ];
            match ($this->logLevel) {
                'debug'   => $this->logger->debug('Password changed successfully', $context),
                'notice'  => $this->logger->notice('Password changed successfully', $context),
                'warning' => $this->logger->warning('Password changed successfully', $context),
                'error'   => $this->logger->error('Password changed successfully', $context),
                default   => $this->logger->info('Password changed successfully', $context),
            };
        }

        return $removed;
    }
}
