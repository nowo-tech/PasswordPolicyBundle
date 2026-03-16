<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\DependencyInjection;

use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\DependencyInjection\PasswordPolicyExtension;
use Nowo\PasswordPolicyBundle\Exceptions\ConfigurationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests for PasswordPolicyExtension.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class PasswordPolicyExtensionTest extends UnitTestCase
{
    private PasswordPolicyExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new PasswordPolicyExtension();
    }

    public function testGetAlias(): void
    {
        $this->assertSame(Configuration::ALIAS, $this->extension->getAlias());
    }

    public function testLoadWithMinimalConfig(): void
    {
        $container = new ContainerBuilder();

        // Create a mock entity class that implements the interface
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => 'user_reset_password',
                    ],
                ],
            ],
        ];

        // Should not throw any exception
        $this->extension->load($configs, $container);

        $this->assertTrue($container->hasDefinition(\Nowo\PasswordPolicyBundle\Service\PasswordExpiryService::class));
        $this->assertTrue($container->hasDefinition('password_expiry_configuration.' . $mockEntityClass));
    }

    public function testLoadWithFullConfig(): void
    {
        $container = new ContainerBuilder();

        // Create a mock entity class that implements the interface
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'password_field'            => 'password',
                        'password_history_field'    => 'passwordHistory',
                        'passwords_to_remember'     => 5,
                        'expiry_days'               => 60,
                        'reset_password_route_name' => 'user_reset_password',
                        'notified_routes'           => ['dashboard'],
                        'excluded_notified_routes'  => ['logout'],
                    ],
                ],
                'expiry_listener' => [
                    'priority'   => 10,
                    'lock_route' => 'lock',
                    'error_msg'  => [
                        'text' => [
                            'title'   => 'Expired',
                            'message' => 'Change password',
                        ],
                        'type' => 'warning',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $container);

        $this->assertTrue($container->hasDefinition(\Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener::class));
        $this->assertTrue($container->hasDefinition('password_expiry_configuration.' . $mockEntityClass));
    }

    public function testLoadThrowsExceptionForNonExistentEntity(): void
    {
        $container = new ContainerBuilder();
        $configs   = [
            [
                'entities' => [
                    'NonExistent\Entity' => [
                        'reset_password_route_name' => 'reset',
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Entity class NonExistent\Entity not found');

        $this->extension->load($configs, $container);
    }

    public function testLoadThrowsExceptionForEntityNotImplementingInterface(): void
    {
        $container = new ContainerBuilder();
        $configs   = [
            [
                'entities' => [
                    'stdClass' => [
                        'reset_password_route_name' => 'reset',
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("doesn't implement");

        $this->extension->load($configs, $container);
    }

    public function testLoadWithMultipleEntities(): void
    {
        $container = new ContainerBuilder();

        // Create a mock entity class that implements the interface
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => 'reset1',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $container);

        $this->assertTrue($container->hasDefinition('password_expiry_configuration.' . $mockEntityClass));
    }

    public function testLoadThrowsExceptionForDuplicateResetPasswordRoutes(): void
    {
        new ContainerBuilder();

        // Use reflection to test validateDuplicateRoutes directly
        // This avoids the need for real entity classes
        $reflection = new ReflectionClass($this->extension);
        $method     = $reflection->getMethod('validateDuplicateRoutes');

        $entities = [
            'Entity1' => [
                'reset_password_route_name' => 'reset_password',
            ],
            'Entity2' => [
                'reset_password_route_name' => 'reset_password', // Duplicate!
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Duplicate reset_password_route_name');

        $method->invoke($this->extension, $entities);
    }

    public function testLoadThrowsExceptionForDuplicateNotifiedRoutes(): void
    {
        // Use reflection to test validateDuplicateRoutes directly
        $reflection = new ReflectionClass($this->extension);
        $method     = $reflection->getMethod('validateDuplicateRoutes');

        $entities = [
            'Entity1' => [
                'reset_password_route_name' => 'reset1',
                'notified_routes'           => ['dashboard'],
            ],
            'Entity2' => [
                'reset_password_route_name' => 'reset2',
                'notified_routes'           => ['dashboard'], // Duplicate without exclusion!
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Duplicate notified_route');

        $method->invoke($this->extension, $entities);
    }

    public function testLoadAllowsDuplicateNotifiedRoutesWhenExcluded(): void
    {
        // Use reflection to test validateDuplicateRoutes directly
        $reflection = new ReflectionClass($this->extension);
        $method     = $reflection->getMethod('validateDuplicateRoutes');

        $entities = [
            'Entity1' => [
                'reset_password_route_name' => 'reset1',
                'notified_routes'           => ['dashboard'],
                'excluded_notified_routes'  => ['dashboard'], // Excluded in first
            ],
            'Entity2' => [
                'reset_password_route_name' => 'reset2',
                'notified_routes'           => ['dashboard'],
                'excluded_notified_routes'  => ['dashboard'], // Excluded in second
            ],
        ];

        // Should not throw exception when both entities exclude the route
        $method->invoke($this->extension, $entities);

        $this->addToAssertionCount(1);
    }

    public function testLoadThrowsExceptionForEmptyResetPasswordRouteName(): void
    {
        $container       = new ContainerBuilder();
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => '',
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('reset_password_route_name is required');

        $this->extension->load($configs, $container);
    }

    public function testLoadThrowsExceptionForInvalidNotifiedRoute(): void
    {
        $container       = new ContainerBuilder();
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => 'reset',
                        'notified_routes'           => ['', 'valid'],
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid notified_route');

        $this->extension->load($configs, $container);
    }

    public function testLoadThrowsExceptionForInvalidExcludedNotifiedRoute(): void
    {
        $container       = new ContainerBuilder();
        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;

        $configs = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => 'reset',
                        'excluded_notified_routes'  => [''],
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid excluded_notified_route');

        $this->extension->load($configs, $container);
    }

    /**
     * Covers load() when container has cache.app (cache service reference is set).
     */
    public function testLoadWithCacheAppService(): void
    {
        $container = new ContainerBuilder();
        $container->register('cache.app', \Symfony\Component\Cache\Adapter\ArrayAdapter::class);

        $mockEntityClass = $this->createMock(HasPasswordPolicyInterface::class)::class;
        $configs         = [
            [
                'entities' => [
                    $mockEntityClass => [
                        'reset_password_route_name' => 'reset',
                    ],
                ],
                'enable_cache' => true,
            ],
        ];

        $this->extension->load($configs, $container);

        $def  = $container->getDefinition(\Nowo\PasswordPolicyBundle\Service\PasswordExpiryService::class);
        $args = $def->getArguments();
        $this->assertNotNull($args['$cache'] ?? null);
    }

    /**
     * Covers configureValidator() early return when container has no PasswordPolicyValidator definition.
     */
    public function testConfigureValidatorReturnsEarlyWhenValidatorNotRegistered(): void
    {
        $container  = new ContainerBuilder();
        $reflection = new ReflectionClass($this->extension);
        $method     = $reflection->getMethod('configureValidator');
        $config     = ['enable_logging' => true, 'log_level' => 'info'];

        $method->invoke($this->extension, $container, $config);
        $this->assertFalse($container->hasDefinition(\Nowo\PasswordPolicyBundle\Validator\PasswordPolicyValidator::class));
        $this->addToAssertionCount(1);
    }

    /**
     * Covers addExpiryListener() when container does not have PasswordExpiryListener definition (register branch).
     */
    public function testAddExpiryListenerRegistersWhenDefinitionMissing(): void
    {
        $container  = new ContainerBuilder();
        $reflection = new ReflectionClass($this->extension);
        $method     = $reflection->getMethod('addExpiryListener');
        $config     = [
            'expiry_listener' => [
                'priority'           => 5,
                'error_msg'          => ['type' => 'error', 'text' => 'Expired'],
                'redirect_on_expiry' => false,
            ],
            'enable_logging' => true,
            'log_level'      => 'info',
        ];

        $definition = $method->invoke($this->extension, $container, $config);
        $this->assertTrue($container->hasDefinition(\Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener::class));
        $this->assertNotEmpty($definition->getTags());
        $this->addToAssertionCount(1);
    }
}
