<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use DateTimeImmutable;
use DateTimeInterface;
use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\Exceptions\ConfigurationException;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordReuseMatch;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Util\PasswordExtensionCandidates;

use function sprintf;

/**
 * Default {@see PasswordReuseCheckerInterface}: same order and rules as the entity check (newest
 * passwords_to_remember history entries, then the current password, then extensions).
 */
final readonly class PasswordReuseChecker implements PasswordReuseCheckerInterface
{
    public function __construct(
        private PasswordHistoryRepositoryInterface $repository,
        private PasswordHashVerifierInterface $hashVerifier,
        private ?PasswordPolicyConfigurationService $configService = null,
    ) {
    }

    public function findReuse(
        string $plainPassword,
        PasswordPolicySubjectInterface $subject,
        bool $checkCurrentPassword = true,
        ?bool $detectExtensions = null,
        ?int $extensionMinLength = null,
    ): ?PasswordReuseMatch {
        $class   = $this->configuredClass($subject->getPasswordPolicySubjectClass());
        $limit   = (int) $this->setting($class, 'passwords_to_remember', Configuration::DEFAULT_PASSWORDS_TO_REMEMBER);
        $history = $this->repository->findRecent($class, $subject->getPasswordPolicySubjectId(), $limit);

        foreach ($history as $entry) {
            if ($this->hashVerifier->verify($class, $entry->passwordHash, $plainPassword)) {
                return new PasswordReuseMatch(PasswordReuseMatch::TYPE_EXACT, $entry->createdAt);
            }
        }

        $currentHash = $subject->getPasswordHash();
        if ($checkCurrentPassword && $currentHash !== null && $currentHash !== ''
            && $this->hashVerifier->verify($class, $currentHash, $plainPassword)) {
            $changedAt = $subject->getPasswordChangedAt();

            return new PasswordReuseMatch(
                PasswordReuseMatch::TYPE_CURRENT,
                $changedAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($changedAt) : null,
            );
        }

        if (!(bool) ($detectExtensions ?? $this->setting($class, 'detect_password_extensions', false))) {
            return null;
        }

        $minLength = (int) ($extensionMinLength ?? $this->setting($class, 'extension_min_length', 4));
        foreach (PasswordExtensionCandidates::collect($plainPassword, $minLength) as $basePassword) {
            foreach ($history as $entry) {
                if ($this->hashVerifier->verify($class, $entry->passwordHash, $basePassword)) {
                    return new PasswordReuseMatch(PasswordReuseMatch::TYPE_EXTENSION, $entry->createdAt);
                }
            }
        }

        return null;
    }

    /**
     * The configured key for the subject class; without a configuration service (manual construction) the class as is.
     *
     * @throws ConfigurationException When no nowo_password_policy.entities key applies to the class
     */
    private function configuredClass(string $class): string
    {
        if (!$this->configService instanceof PasswordPolicyConfigurationService) {
            return $class;
        }

        return $this->configService->resolveEntityClass($class)
            ?? throw new ConfigurationException(sprintf('Password policy subject class %s matches no nowo_password_policy.entities key: configure it (or a parent class / interface) so its settings apply.', $class));
    }

    private function setting(string $class, string $key, mixed $default): mixed
    {
        return $this->configService?->getEntityConfiguration($class, $key, $default) ?? $default;
    }
}
