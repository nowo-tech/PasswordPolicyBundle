<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

/**
 * Service for storing and retrieving password policy configurations per entity.
 *
 * This service allows the validator to access entity-specific configuration
 * (like detect_password_extensions) that is defined in YAML configuration.
 */
class PasswordPolicyConfigurationService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $entityConfigurations = [];

    /**
     * Sets the configuration for an entity.
     *
     * @param string $entityClass The fully qualified entity class name
     * @param array  $config      The configuration array for this entity
     *
     * @return void
     */
    public function setEntityConfiguration(string $entityClass, array $config): void
    {
        $this->entityConfigurations[$entityClass] = $config;
    }

    /**
     * Gets the configuration for an entity.
     *
     * @param string $entityClass The fully qualified entity class name
     * @param string $key         The configuration key to retrieve
     * @param mixed  $default     The default value if the key doesn't exist
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
     * @param string $key         The configuration key to check
     *
     * @return bool True if the configuration exists, false otherwise
     */
    public function hasEntityConfiguration(string $entityClass, string $key): bool
    {
        return isset($this->entityConfigurations[$entityClass][$key]);
    }
}

