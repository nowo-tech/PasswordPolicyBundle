<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for the Password Policy Bundle.
 *
 * This class defines the structure and default values for the bundle configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * The configuration alias for this bundle.
     */
    public const ALIAS = 'nowo_password_policy';

    private const DEFAULT_PASSWORD_FIELD = 'password';

    private const DEFAULT_PASSWORD_HISTORY_FIELD = 'passwordHistory';

    private const DEFAULT_PASSWORDS_TO_REMEMBER = 3;

    private const DEFAULT_EXPIRY_LISTENER_PRIORITY = 0;

    private const DEFAULT_EXPIRY_DAYS = 90;

    private const DEFAULT_ERROR_MSG = [
      'title' => self::ALIAS . '.title',
      'message' => self::ALIAS . '.message',
    ];

    private const DEFAULT_ERROR_TYPE = 'error';

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder with the complete configuration structure
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root(self::ALIAS);
        }

        // @formatter:off
        $rootNode->fixXmlConfig('entity')
          ->children()
            ->arrayNode('entities')
              ->useAttributeAsKey('class')
              ->cannotBeEmpty()
              ->isRequired()
              ->info('Configuration for entities that implement HasPasswordPolicyInterface. Each entity class can have its own password policy settings.')
              ->arrayPrototype()
                ->addDefaultsIfNotSet()
                ->children()
                  ->scalarNode('password_field')
                    ->defaultValue(self::DEFAULT_PASSWORD_FIELD)
                    ->treatNullLike(self::DEFAULT_PASSWORD_FIELD)
                    ->info('The name of the password field in the entity. This field will be monitored for changes to track password history.')
                  ->end()
                  ->scalarNode('password_history_field')
                    ->defaultValue(self::DEFAULT_PASSWORD_HISTORY_FIELD)
                    ->treatNullLike(self::DEFAULT_PASSWORD_HISTORY_FIELD)
                    ->info('The name of the password history collection field in the entity. This should be a OneToMany or ManyToMany relationship to a PasswordHistoryInterface entity.')
                  ->end()
                  ->integerNode('passwords_to_remember')
                    ->defaultValue(self::DEFAULT_PASSWORDS_TO_REMEMBER)
                    ->treatNullLike(self::DEFAULT_PASSWORDS_TO_REMEMBER)
                    ->info('The maximum number of previous passwords to keep in history. When this limit is exceeded, the oldest passwords are automatically removed.')
                  ->end()
                  ->integerNode('expiry_days')
                    ->defaultValue(self::DEFAULT_EXPIRY_DAYS)
                    ->treatNullLike(self::DEFAULT_EXPIRY_DAYS)
                    ->info('Number of days after which a password expires. After this period, users will be notified or redirected to change their password.')
                  ->end()
                  ->scalarNode('reset_password_route_name')
                    ->isRequired()
                    ->info('The route name for password reset. This route will be used when redirect_on_expiry is enabled. Must be a valid route name in your application.')
                  ->end()
                  ->arrayNode('notified_routes')
                    ->scalarPrototype()->end()
                    ->info('List of route names where users will be notified if their password is expired or about to expire. The expiry listener will check these routes and show flash messages.')
                  ->end()
                  ->arrayNode('excluded_notified_routes')
                    ->scalarPrototype()->end()
                    ->info('List of route names excluded from password expiry checks. Useful for excluding login, logout, or password reset routes to prevent redirect loops.')
                  ->end()
                ->end()
              ->end()
            ->end()
            ->arrayNode('expiry_listener')
              ->addDefaultsIfNotSet()
              ->info('Configuration for the password expiry event listener that checks for expired passwords on each request.')
              ->children()
                ->integerNode('priority')
                  ->defaultValue(self::DEFAULT_EXPIRY_LISTENER_PRIORITY)
                  ->treatNullLike(self::DEFAULT_EXPIRY_LISTENER_PRIORITY)
                  ->info('Priority of the expiry listener. Higher values mean the listener runs earlier. Default is 0.')
                ->end()
                ->scalarNode('lock_route')
                  ->info('(Deprecated) Route to redirect when password is expired. Use redirect_on_expiry and reset_password_route_name instead.')
                ->end()
                ->booleanNode('redirect_on_expiry')
                  ->defaultFalse()
                  ->info('If true, automatically redirects users to the reset_password_route_name when their password expires. If false, only shows a flash message without redirecting.')
                ->end()
                ->arrayNode('error_msg')
                  ->addDefaultsIfNotSet()
                  ->info('Configuration for error messages displayed when password expires.')
                  ->children()
                    ->variableNode('text')
                      ->defaultValue(self::DEFAULT_ERROR_MSG)
                      ->treatNullLike(self::DEFAULT_ERROR_MSG)
                      ->info('Error message text. Can be a string or an array with "title" and "message" keys. Supports translation keys.')
                    ->end()
                    ->scalarNode('type')
                      ->defaultValue(self::DEFAULT_ERROR_TYPE)
                      ->treatNullLike(self::DEFAULT_ERROR_TYPE)
                      ->info('Flash message type. Common values: "error", "warning", "info", "success". This determines the CSS class and styling of the flash message.')
                    ->end()
                  ->end()
                ->end()
              ->end()
            ->end()
            ->scalarNode('log_level')
              ->defaultValue('info')
              ->info('Logging level for password policy events. Valid values: "debug", "info", "notice", "warning", "error". All password policy events (expiry detection, password changes, reuse attempts) will be logged at this level.')
            ->end()
            ->booleanNode('enable_logging')
              ->defaultTrue()
              ->info('Enable or disable logging for password policy events. When enabled, important events like password expiry, password changes, and reuse attempts will be logged using Symfony Logger.')
            ->end()
            ->booleanNode('enable_cache')
              ->defaultFalse()
              ->info('Enable caching for password expiry checks. When enabled, expiry status is cached per user to improve performance. Cache is automatically invalidated when password changes.')
            ->end()
            ->scalarNode('cache_ttl')
              ->defaultValue(3600)
              ->info('Cache time-to-live in seconds. Default is 3600 (1 hour). Only used when enable_cache is true.')
            ->end()
          ->end();
        //@formatter:on

        return $treeBuilder;
    }
}
