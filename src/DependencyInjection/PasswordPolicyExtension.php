<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\DependencyInjection;

use Exception;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener;
use Nowo\PasswordPolicyBundle\Exceptions\ConfigurationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryService;
use Nowo\PasswordPolicyBundle\Service\PasswordExpiryServiceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Dependency injection extension for the Password Policy Bundle.
 *
 * This extension loads the bundle configuration and registers all necessary services,
 * including entity listeners and the password expiry listener.
 */
class PasswordPolicyExtension extends Extension
{
    /**
     * Loads the bundle configuration and registers services.
     *
     * @param array            $configs          The configuration array
     * @param ContainerBuilder $containerBuilder The container builder
     *
     * @throws Exception If configuration is invalid
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $containerBuilder): void
    {
        $yamlFileLoader = new YamlFileLoader(
            $containerBuilder,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $yamlFileLoader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->addExpiryListener($containerBuilder, $config);
        $this->configureValidator($containerBuilder, $config);

        // Validate duplicate routes across entities
        $this->validateDuplicateRoutes($config['entities']);

        $definition = $containerBuilder->getDefinition(PasswordExpiryService::class);

        // Configure cache for PasswordExpiryService
        $cacheService = $containerBuilder->has('cache.app') ? new Reference('cache.app') : null;
        $definition->setArgument('$cache', $cacheService);
        $definition->setArgument('$cacheEnabled', $config['enable_cache'] ?? false);
        $definition->setArgument('$cacheTtl', $config['cache_ttl'] ?? 3600);

        foreach ($config['entities'] as $entityClass => $settings) {
            if (!class_exists($entityClass)) {
                throw new ConfigurationException(sprintf('Entity class %s not found', $entityClass));
            }

            // Validate reset_password_route_name is not empty
            if (empty($settings['reset_password_route_name'])) {
                throw new ConfigurationException(sprintf(
                    'reset_password_route_name is required for entity %s',
                    $entityClass
                ));
            }

            // Validate notified_routes are strings
            if (!empty($settings['notified_routes'])) {
                foreach ($settings['notified_routes'] as $route) {
                    if (!is_string($route) || empty($route)) {
                        throw new ConfigurationException(sprintf(
                            'Invalid notified_route for entity %s: routes must be non-empty strings',
                            $entityClass
                        ));
                    }
                }
            }

            // Validate excluded_notified_routes are strings
            if (!empty($settings['excluded_notified_routes'])) {
                foreach ($settings['excluded_notified_routes'] as $route) {
                    if (!is_string($route) || empty($route)) {
                        throw new ConfigurationException(sprintf(
                            'Invalid excluded_notified_route for entity %s: routes must be non-empty strings',
                            $entityClass
                        ));
                    }
                }
            }

            $this->addEntityListener($containerBuilder, $entityClass, $settings, $config, $definition);

            $passwordExpiryConfig = $containerBuilder->register(
                'password_expiry_configuration.' . $entityClass,
                PasswordExpiryConfiguration::class
            );
            $passwordExpiryConfig->setArguments([
              $entityClass,
              $settings['expiry_days'],
              $settings['notified_routes'],
              $settings['excluded_notified_routes'],
              $settings['reset_password_route_name'],
            ]);

            $definition->addMethodCall('addEntity', [$passwordExpiryConfig]);
        }
    }

    /**
     * Registers the password expiry listener service.
     *
     * @param ContainerBuilder $containerBuilder The container builder
     * @param array            $config           The configuration array
     *
     * @return Definition The service definition for the password expiry listener
     */
    private function addExpiryListener(ContainerBuilder $containerBuilder, array $config): Definition
    {
        // Get or create the service definition (it should already exist from services.yml)
        if (!$containerBuilder->hasDefinition(PasswordExpiryListener::class)) {
            $definition = $containerBuilder->register(PasswordExpiryListener::class)
              ->setAutowired(false)
              ->setAutoconfigured(false);
        } else {
            $definition = $containerBuilder->getDefinition(PasswordExpiryListener::class)
              ->setAutowired(false)
              ->setAutoconfigured(false);
        }

        // Set all arguments explicitly
        $definition
          ->addTag('kernel.event_listener', [
            'event' => 'kernel.request',
            'priority' => $config['expiry_listener']['priority'],
          ])
          ->setArguments([
            new Reference(PasswordExpiryServiceInterface::class), // $passwordExpiryService
            new Reference('request_stack'), // $requestStack
            new Reference('router'), // $urlGenerator
            new Reference('translator'), // $translator
            $config['expiry_listener']['error_msg']['type'], // $errorMessageType
            $config['expiry_listener']['error_msg']['text'], // $errorMessage
            $config['expiry_listener']['redirect_on_expiry'] ?? false, // $redirectOnExpiry
            $containerBuilder->has('logger') ? new Reference('logger') : null, // $logger (optional)
            $config['enable_logging'] ?? true, // $enableLogging
            $config['log_level'] ?? 'info', // $logLevel
            $containerBuilder->has('event_dispatcher') ? new Reference('event_dispatcher') : null, // $eventDispatcher (optional)
          ]);

        return $definition;
    }

    /**
     * Validates that there are no duplicate routes across different entities.
     *
     * @param array $entities The entities configuration
     *
     * @throws ConfigurationException If duplicate routes are found
     *
     * @return void
     */
    private function validateDuplicateRoutes(array $entities): void
    {
        $resetRouteMap = [];
        $notifiedRouteMap = [];

        foreach ($entities as $entityClass => $settings) {
            // Validate reset_password_route_name uniqueness
            $resetRoute = $settings['reset_password_route_name'] ?? null;
            if ($resetRoute) {
                if (isset($resetRouteMap[$resetRoute])) {
                    throw new ConfigurationException(sprintf(
                        'Duplicate reset_password_route_name "%s" found in entities %s and %s. Each entity must have a unique reset password route.',
                        $resetRoute,
                        $resetRouteMap[$resetRoute],
                        $entityClass
                    ));
                }
                $resetRouteMap[$resetRoute] = $entityClass;
            }

            // Check notified_routes for duplicates (warn but allow if properly excluded)
            $notifiedRoutes = $settings['notified_routes'] ?? [];
            foreach ($notifiedRoutes as $route) {
                if (isset($notifiedRouteMap[$route]) && $notifiedRouteMap[$route] !== $entityClass) {
                    // Check if the route is excluded in the other entity
                    $otherEntityClass = $notifiedRouteMap[$route];
                    $otherExcludedRoutes = $entities[$otherEntityClass]['excluded_notified_routes'] ?? [];
                    $currentExcludedRoutes = $settings['excluded_notified_routes'] ?? [];

                    // Only throw error if route is not excluded in both entities
                    if (!in_array($route, $otherExcludedRoutes) && !in_array($route, $currentExcludedRoutes)) {
                        throw new ConfigurationException(sprintf(
                            'Duplicate notified_route "%s" found in entities %s and %s. Either use unique routes per entity or add the route to excluded_notified_routes in both entities.',
                            $route,
                            $otherEntityClass,
                            $entityClass
                        ));
                    }
                }
                $notifiedRouteMap[$route] = $entityClass;
            }
        }
    }

    /**
     * Registers a Doctrine entity listener for password history management.
     *
     * @param ContainerBuilder $containerBuilder                The container builder
     * @param string           $entityClass                     The fully qualified class name of the entity
     * @param array            $settings                        The entity configuration settings
     * @param array            $config                          The full configuration array
     * @param Definition       $passwordExpiryServiceDefinition The PasswordExpiryService definition
     *
     * @throws ConfigurationException If the entity class does not implement HasPasswordPolicyInterface
     *
     * @return Definition The service definition for the entity listener
     */
    private function addEntityListener(
        ContainerBuilder $containerBuilder,
        string $entityClass,
        array $settings,
        array $config,
        Definition $passwordExpiryServiceDefinition
    ): Definition {
        if (!is_a($entityClass, HasPasswordPolicyInterface::class, true)) {
            throw new ConfigurationException(sprintf(
                "Entity %s doesn't implement %s interface",
                $entityClass,
                HasPasswordPolicyInterface::class
            ));
        }

        $snakeClass = strtolower(str_replace('\\', '_', $entityClass));
        $definition = $containerBuilder->autowire(
            'nowo_password_policy.entity_listener.' . $snakeClass,
            PasswordEntityListener::class
        );

        $definition->addTag('doctrine.event_listener', ['event' => 'onFlush']);

        $definition->setArgument('$passwordField', $settings['password_field']);
        $definition->setArgument('$passwordHistoryField', $settings['password_history_field']);
        $definition->setArgument('$historyLimit', $settings['passwords_to_remember']);
        $definition->setArgument('$entityClass', $entityClass);
        $definition->setArgument('$logger', $containerBuilder->has('logger') ? new Reference('logger') : null);
        $definition->setArgument('$enableLogging', $config['enable_logging'] ?? true);
        $definition->setArgument('$logLevel', $config['log_level'] ?? 'info');
        $definition->setArgument('$eventDispatcher', $containerBuilder->has('event_dispatcher') ? new Reference('event_dispatcher') : null);
        $definition->setArgument('$passwordExpiryService', new Reference(PasswordExpiryServiceInterface::class));

        return $definition;
    }

    /**
     * Configures the PasswordPolicyValidator service with logging parameters.
     *
     * @param ContainerBuilder $containerBuilder The container builder
     * @param array            $config           The configuration array
     *
     * @return void
     */
    private function configureValidator(ContainerBuilder $containerBuilder, array $config): void
    {
        if (!$containerBuilder->hasDefinition('Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator')) {
            return;
        }

        $definition = $containerBuilder->getDefinition('Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator');

        // Add logging parameters (will override if already set via autowiring)
        $definition->setArgument('$logger', $containerBuilder->has('logger') ? new Reference('logger') : null);
        $definition->setArgument('$enableLogging', $config['enable_logging'] ?? true);
        $definition->setArgument('$logLevel', $config['log_level'] ?? 'info');
        $definition->setArgument('$eventDispatcher', $containerBuilder->has('event_dispatcher') ? new Reference('event_dispatcher') : null);
    }

    /**
     * Returns the extension alias.
     *
     * @return string The extension alias
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
