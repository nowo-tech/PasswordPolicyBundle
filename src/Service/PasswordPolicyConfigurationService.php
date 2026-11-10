<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Util\SubjectId;

use function array_keys;
use function is_a;

/**
 * Service for storing and retrieving password policy configurations per entity.
 *
 * This service allows the validator to access entity-specific configuration
 * (like detect_password_extensions) that is defined in YAML configuration.
 */
final class PasswordPolicyConfigurationService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $entityConfigurations = [];

    /**
     * Sets the configuration for an entity.
     *
     * @param string $entityClass The fully qualified entity class name
     * @param array<string, mixed> $config The configuration array for this entity
     */
    public function setEntityConfiguration(string $entityClass, array $config): void
    {
        // @igor-ignore - Service coordinates I/O or request-scoped work; not unsafe worker singleton state.
        $this->entityConfigurations[$entityClass] = $config;
    }

    /**
     * Gets the configuration for an entity.
     *
     * @param string $entityClass The fully qualified entity class name
     * @param string $key The configuration key to retrieve
     * @param mixed $default The default value if the key doesn't exist
     *
     * @return mixed The configuration value or default
     */
    public function getEntityConfiguration(string $entityClass, string $key, mixed $default = null): mixed
    {
        return $this->entityConfigurations[$entityClass][$key] ?? $default;
    }

    /**
     * Checks if a configuration key exists for an entity.
     *
     * @param string $entityClass The fully qualified entity class name
     * @param string $key The configuration key to check
     *
     * @return bool True if the configuration exists, false otherwise
     */
    public function hasEntityConfiguration(string $entityClass, string $key): bool
    {
        return isset($this->entityConfigurations[$entityClass][$key]);
    }

    /**
     * The configured class (a key of nowo_password_policy.entities) whose settings apply to $class: the exact key,
     * the class without a Doctrine proxy prefix, then the first key $class is an instance of. Null when none applies.
     */
    public function resolveEntityClass(string $class): ?string
    {
        if (isset($this->entityConfigurations[$class])) {
            return $class;
        }

        $class = SubjectId::withoutProxyPrefix($class);
        if (isset($this->entityConfigurations[$class])) {
            return $class;
        }

        foreach (array_keys($this->entityConfigurations) as $configuredClass) {
            if (is_a($class, $configuredClass, true)) {
                return $configuredClass;
            }
        }

        return null;
    }
}
