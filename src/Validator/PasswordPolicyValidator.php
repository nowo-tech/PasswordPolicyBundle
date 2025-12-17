<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;


use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Carbon\Carbon;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     */
    public function __construct(
      private readonly PasswordPolicyServiceInterface $passwordPolicyService,
      private TranslatorInterface $translator,
      private readonly ?LoggerInterface $logger = null,
      private readonly bool $enableLogging = true,
      private readonly string $logLevel = 'info',
      private readonly ?EventDispatcherInterface $eventDispatcher = null
    )
    {
    }

    /**
     * Validates that the password has not been used before.
     *
     * @param mixed $value The plain password value to validate
     * @param Constraint $constraint The PasswordPolicy constraint instance
     * @return void
     * @throws ValidationException If the entity does not implement HasPasswordPolicyInterface
     */
    public function validate($value, Constraint $constraint): void
    {
        if (is_null($value)) {
            return;
        }

        $entity = $this->context->getObject();

        if (!$entity instanceof HasPasswordPolicyInterface) {
            throw new ValidationException(message: sprintf('Expected validation entity to implements %s',
                HasPasswordPolicyInterface::class));
        }

        Carbon::setLocale($this->translator->getLocale());

        $history = $this->passwordPolicyService->getHistoryByPassword($value, $entity);
        if ($history instanceof PasswordHistoryInterface) {
            // Dispatch PasswordReuseAttemptedEvent
            if ($this->eventDispatcher) {
                $event = new PasswordReuseAttemptedEvent($entity, $history);
                $this->eventDispatcher->dispatch($event);
            }

            // Log password reuse attempt
            if ($this->enableLogging && $this->logger) {
                $userId = method_exists($entity, 'getId') ? $entity->getId() : 'unknown';
                $userIdentifier = 'unknown';
                if (method_exists($entity, 'getUserIdentifier')) {
                    /** @var callable $getUserIdentifier */
                    $getUserIdentifier = [$entity, 'getUserIdentifier'];
                    $userIdentifier = $getUserIdentifier();
                } elseif (method_exists($entity, 'getEmail')) {
                    /** @var callable $getEmail */
                    $getEmail = [$entity, 'getEmail'];
                    $userIdentifier = $getEmail();
                }
                $this->log($this->logLevel, 'Password reuse attempt detected', [
                    'user_id' => $userId,
                    'user_identifier' => $userIdentifier,
                    'password_used_days_ago' => Carbon::instance($history->getCreatedAt())->diffInDays(Carbon::now()),
                ]);
            }

            $this->context->buildViolation($constraint->message)
                          ->setParameter('{{ days }}', Carbon::instance($history->getCreatedAt())->diffForHumans())
                          ->setCode(PasswordPolicy::PASSWORD_IN_HISTORY)
                          ->addViolation();
        }
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
