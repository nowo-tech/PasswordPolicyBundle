<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Nowo\PasswordPolicyBundle\Event\PasswordReuseDetectedEvent;
use Nowo\PasswordPolicyBundle\Exceptions\ValidationException;
use Nowo\PasswordPolicyBundle\Model\CurrentPasswordHistory;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Service\CurrentPasswordMatcherInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyServiceInterface;
use Nowo\PasswordPolicyBundle\Subject\PasswordPolicySubjectResolverInterface;
use Nowo\PasswordPolicyBundle\Subject\PasswordReuseCheckerInterface;
use Nowo\PasswordPolicyBundle\Util\LogIdentifier;
use Nowo\PasswordPolicyBundle\Util\SubjectId;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_object;
use function is_string;
use function sprintf;

/**
 * Validator for the PasswordPolicy constraint.
 *
 * This validator checks if a password has been used before by comparing it against
 * the password history of the entity implementing HasPasswordPolicyInterface. On any other object (a command, a
 * DTO) the subject resolver supplies the entity or a read-only PasswordPolicySubjectInterface, which is checked
 * through the history port.
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
     * @param PasswordPolicyConfigurationService|null $configService The configuration service (optional)
     * @param PasswordPolicySubjectResolverInterface|null $subjectResolver Finds the subject when the validated object is not the entity (optional)
     * @param PasswordReuseCheckerInterface|null $reuseChecker Checks read-only subjects through the history port (optional)
     */
    public function __construct(
        private readonly PasswordPolicyServiceInterface $passwordPolicyService,
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $enableLogging = true,
        private readonly string $logLevel = 'info',
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?PasswordPolicyConfigurationService $configService = null,
        private readonly ?ClockInterface $clock = null,
        private readonly ?PasswordPolicySubjectResolverInterface $subjectResolver = null,
        private readonly ?PasswordReuseCheckerInterface $reuseChecker = null,
    ) {
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
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

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $entity = $this->context->getObject();

        if (!$entity instanceof HasPasswordPolicyInterface) {
            // A command/DTO: ask the subject resolver for the entity or a read-only subject.
            $subject = is_object($entity) ? $this->subjectResolver?->resolve($entity) : null;

            if ($subject instanceof PasswordPolicySubjectInterface && !$subject instanceof HasPasswordPolicyInterface
                && $this->reuseChecker instanceof PasswordReuseCheckerInterface && $constraint instanceof PasswordPolicy) {
                $this->validateSubject($value, $subject, $constraint, $this->reuseChecker);

                return;
            }

            if (!$subject instanceof HasPasswordPolicyInterface) {
                throw new ValidationException(message: sprintf('Expected validation entity to implements %s', HasPasswordPolicyInterface::class));
            }

            $entity = $subject;
        }

        // First, check for exact password match
        $history = $this->passwordPolicyService->getHistoryByPassword($value, $entity);
        if ($history instanceof PasswordHistoryInterface && $constraint instanceof PasswordPolicy) {
            $this->handlePasswordReuse($entity, $history, $constraint, 'exact');

            return;
        }

        // The current password only enters the history on the next change: reject "changing" to it.
        if ($constraint instanceof PasswordPolicy
            && $constraint->checkCurrentPassword
            && $this->passwordPolicyService instanceof CurrentPasswordMatcherInterface
            && $this->passwordPolicyService->matchesCurrentPassword($value, $entity)
        ) {
            $this->handlePasswordReuse($entity, new CurrentPasswordHistory($entity->getPassword(), $entity->getPasswordChangedAt()), $constraint, 'current');

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
     * Checks a read-only subject through the history port and reports a violation like the entity path.
     */
    private function validateSubject(
        string $value,
        PasswordPolicySubjectInterface $subject,
        PasswordPolicy $constraint,
        PasswordReuseCheckerInterface $reuseChecker,
    ): void {
        $match = $reuseChecker->findReuse(
            $value,
            $subject,
            $constraint->checkCurrentPassword,
            $constraint->detectExtensions,
            $constraint->extensionMinLength,
        );

        if (!$match instanceof PasswordReuseMatch) {
            return;
        }

        $subjectClass = $subject->getPasswordPolicySubjectClass();
        $subjectId    = $subject->getPasswordPolicySubjectId();

        $this->eventDispatcher?->dispatch(new PasswordReuseDetectedEvent($subjectClass, $subjectId, $match->type, $match->usedAt));

        if ($this->enableLogging && $this->logger instanceof LoggerInterface) {
            $this->log($this->logLevel, $match->type === PasswordReuseMatch::TYPE_EXTENSION
                ? 'Password extension detected (new password is an extension of an old password)'
                : 'Password reuse attempt detected', [
                    'user_id'                => $subjectId,
                    'entity_class'           => $subjectClass,
                    'password_used_days_ago' => $match->usedAt instanceof DateTimeInterface ? Carbon::instance($match->usedAt)->diffInDays(Carbon::instance($this->now())) : 0,
                    'match_type'             => $match->type,
                ]);
        }

        $isExtension = $match->type === PasswordReuseMatch::TYPE_EXTENSION;
        $message     = $isExtension && !empty($constraint->extensionMessage) ? $constraint->extensionMessage : $constraint->message;

        $this->context->buildViolation($message)
                      ->setParameter('{{ days }}', $this->humanDays($match->usedAt))
                      ->setCode($isExtension ? PasswordPolicy::PASSWORD_EXTENSION : PasswordPolicy::PASSWORD_IN_HISTORY)
                      ->addViolation();
    }

    private function humanDays(?DateTimeInterface $usedAt): string
    {
        if (!$usedAt instanceof DateTimeInterface) {
            return '';
        }

        $localized = Carbon::instance($usedAt);
        $localized->locale($this->translator->getLocale());

        return $localized->diffForHumans();
    }

    /**
     * Handles password reuse detection (both exact matches and extensions).
     *
     * @param HasPasswordPolicyInterface $entity The entity with password history
     * @param PasswordHistoryInterface $history The matching password history entry
     * @param PasswordPolicy $constraint The constraint instance
     * @param string $type The type of match: 'exact', 'current' or 'extension'
     */
    private function handlePasswordReuse(
        HasPasswordPolicyInterface $entity,
        PasswordHistoryInterface $history,
        PasswordPolicy $constraint,
        string $type
    ): void {
        // Dispatch PasswordReuseAttemptedEvent, then its scalar twin PasswordReuseDetectedEvent (since 1.13.0, as the
        // read-only subject path does; REQ-ARCH-001 rule 4)
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            $event = new PasswordReuseAttemptedEvent($entity, $history);
            $this->eventDispatcher->dispatch($event);

            $usedAt = $history->getCreatedAt();
            $this->eventDispatcher->dispatch(new PasswordReuseDetectedEvent(
                SubjectId::classOf($entity),
                SubjectId::of($entity->getId()),
                $this->matchType($type),
                $usedAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($usedAt) : null,
            ));
        }

        // Log password reuse attempt
        if ($this->enableLogging && $this->logger instanceof LoggerInterface) {
            $userId         = $entity->getId();
            $userIdentifier = LogIdentifier::redact($entity instanceof UserInterface
                ? $entity->getUserIdentifier()
                : (method_exists($entity, 'getEmail') ? (string) $entity->getEmail() : 'unknown'));
            $createdAt = $history->getCreatedAt();
            $message   = $type === 'extension'
                ? 'Password extension detected (new password is an extension of an old password)'
                : 'Password reuse attempt detected';
            $this->log($this->logLevel, $message, [
                'user_id'                => $userId,
                'user_identifier'        => $userIdentifier,
                'password_used_days_ago' => $createdAt instanceof DateTimeInterface ? Carbon::instance($createdAt)->diffInDays(Carbon::instance($this->now())) : 0,
                'match_type'             => $type,
            ]);
        }

        $message = $constraint->message;
        if ($type === 'extension' && !empty($constraint->extensionMessage)) {
            $message = $constraint->extensionMessage;
        }

        $createdAt = $history->getCreatedAt();
        $days      = '';
        if ($createdAt instanceof DateTimeInterface) {
            $localizedCreatedAt = Carbon::instance($createdAt);
            $localizedCreatedAt->locale($this->translator->getLocale());
            $days = $localizedCreatedAt->diffForHumans();
        }

        $this->context->buildViolation($message)
                      ->setParameter('{{ days }}', $days)
                      ->setCode($type === 'extension' ? PasswordPolicy::PASSWORD_EXTENSION : PasswordPolicy::PASSWORD_IN_HISTORY)
                      ->addViolation();
    }

    /**
     * The {@see PasswordReuseMatch} type of an entity-path match.
     *
     * @return PasswordReuseMatch::TYPE_*
     */
    private function matchType(string $type): string
    {
        return match ($type) {
            PasswordReuseMatch::TYPE_CURRENT   => PasswordReuseMatch::TYPE_CURRENT,
            PasswordReuseMatch::TYPE_EXTENSION => PasswordReuseMatch::TYPE_EXTENSION,
            default                            => PasswordReuseMatch::TYPE_EXACT,
        };
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
