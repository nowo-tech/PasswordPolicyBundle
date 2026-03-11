<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;

use Carbon\Carbon;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function sprintf;

/**
 * Validator for the PasswordPolicy constraint.
 *
 * This validator checks if a password has been used before by comparing it against
 * the password history of the entity implementing HasPasswordPolicyInterface.
 */
class PasswordPolicyValidator extends ConstraintValidator
{
    /**
     * PasswordPolicyValidator constructor.
     *
     * @param PasswordPolicyServiceInterface $passwordPolicyService The service for checking password history
     * @param TranslatorInterface $translator The translator service for translating error messages
     * @param LoggerInterface|null $logger The logger service (optional, uses NullLogger if not provided)
     * @param bool $enableLogging Whether logging is enabled
     * @param string $logLevel The logging level to use
     * @param EventDispatcherInterface|null $eventDispatcher The event dispatcher (optional)
     * @param \Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService|null $configService The configuration service (optional)
     */
    public function __construct(
        private readonly PasswordPolicyServiceInterface $passwordPolicyService,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $enableLogging = true,
        private readonly string $logLevel = 'info',
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?\Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService $configService = null
    ) {
    }

    /**
     * Validates that the password has not been used before.
     *
     * @param mixed $value The plain password value to validate
     * @param Constraint $constraint The PasswordPolicy constraint instance
     *
     * @throws ValidationException If the entity does not implement HasPasswordPolicyInterface
     */
    public function validate($value, Constraint $constraint): void
    {
        if ($value === null) {
            return;
        }

        $entity = $this->context->getObject();

        if (!$entity instanceof HasPasswordPolicyInterface) {
            throw new ValidationException(message: sprintf('Expected validation entity to implements %s', HasPasswordPolicyInterface::class));
        }

        Carbon::setLocale($this->translator->getLocale());

        // First, check for exact password match
        $history = $this->passwordPolicyService->getHistoryByPassword($value, $entity);
        if ($history instanceof PasswordHistoryInterface && $constraint instanceof PasswordPolicy) {
            $this->handlePasswordReuse($entity, $history, $constraint, 'exact');

            return;
        }

        // Then, check for password extensions if enabled
        // Check if extension detection is enabled via constraint options, YAML config, or use default
        $entityClass      = $entity::class;
        $detectExtensions = $constraint->detectExtensions
            ?? $this->configService?->getEntityConfiguration($entityClass, 'detect_password_extensions', false)
            ?? false;
        $extensionMinLength = $constraint->extensionMinLength
            ?? $this->configService?->getEntityConfiguration($entityClass, 'extension_min_length', 4)
            ?? 4;

        if ($detectExtensions) {
            $extensionHistory = $this->passwordPolicyService->getHistoryByPasswordExtension($value, $entity, $extensionMinLength);
            if ($extensionHistory instanceof PasswordHistoryInterface && $constraint instanceof PasswordPolicy) {
                $this->handlePasswordReuse($entity, $extensionHistory, $constraint, 'extension');

                return;
            }
        }
    }

    /**
     * Handles password reuse detection (both exact matches and extensions).
     *
     * @param HasPasswordPolicyInterface $entity The entity with password history
     * @param PasswordHistoryInterface $history The matching password history entry
     * @param PasswordPolicy $constraint The constraint instance
     * @param string $type The type of match: 'exact' or 'extension'
     */
    private function handlePasswordReuse(
        HasPasswordPolicyInterface $entity,
        PasswordHistoryInterface $history,
        PasswordPolicy $constraint,
        string $type
    ): void {
        // Dispatch PasswordReuseAttemptedEvent
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $event = new PasswordReuseAttemptedEvent($entity, $history);
            $this->eventDispatcher->dispatch($event);
        }

        // Log password reuse attempt
        if ($this->enableLogging && $this->logger) {
            $userId         = $entity->getId();
            $userIdentifier = $entity instanceof \Symfony\Component\Security\Core\User\UserInterface
                ? $entity->getUserIdentifier()
                : (method_exists($entity, 'getEmail') ? $entity->getEmail() : 'unknown');
            $createdAt = $history->getCreatedAt();
            $message   = $type === 'extension'
                ? 'Password extension detected (new password is an extension of an old password)'
                : 'Password reuse attempt detected';
            $this->log($this->logLevel, $message, [
                'user_id'                => $userId,
                'user_identifier'        => $userIdentifier,
                'password_used_days_ago' => $createdAt instanceof DateTimeInterface ? Carbon::instance($createdAt)->diffInDays(Carbon::now()) : 0,
                'match_type'             => $type,
            ]);
        }

        $message = $constraint->message;
        if ($type === 'extension' && !empty($constraint->extensionMessage)) {
            $message = $constraint->extensionMessage;
        }

        $createdAt = $history->getCreatedAt();
        $this->context->buildViolation($message)
                      ->setParameter('{{ days }}', $createdAt instanceof DateTimeInterface ? Carbon::instance($createdAt)->diffForHumans() : '')
                      ->setCode($type === 'extension' ? PasswordPolicy::PASSWORD_EXTENSION : PasswordPolicy::PASSWORD_IN_HISTORY)
                      ->addViolation();
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
