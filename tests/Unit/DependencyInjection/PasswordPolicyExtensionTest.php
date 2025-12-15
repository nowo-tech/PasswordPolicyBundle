<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\DependencyInjection;

use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\DependencyInjection\PasswordPolicyExtension;
use Nowo\PasswordPolicyBundle\Exceptions\ConfigurationException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests for PasswordPolicyExtension.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2024 Nowo.tech
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
        $configs = [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'reset_password_route_name' => 'user_reset_password',
                    ],
                ],
            ],
        ];

        // Should not throw any exception
        $this->extension->load($configs, $container);

        $this->assertTrue($container->hasDefinition('Nowo\PasswordPolicyBundle\Service\PasswordExpiryService'));
        $this->assertTrue($container->hasDefinition('password_expiry_configuration.App\Entity\User'));
    }

    public function testLoadWithFullConfig(): void
    {
        $container = new ContainerBuilder();
        $configs = [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'password_field' => 'password',
                        'password_history_field' => 'passwordHistory',
                        'passwords_to_remember' => 5,
                        'expiry_days' => 60,
                        'reset_password_route_name' => 'user_reset_password',
                        'notified_routes' => ['dashboard'],
                        'excluded_notified_routes' => ['logout'],
                    ],
                ],
                'expiry_listener' => [
                    'priority' => 10,
                    'lock_route' => 'lock',
                    'error_msg' => [
                        'text' => [
                            'title' => 'Expired',
                            'message' => 'Change password',
                        ],
                        'type' => 'warning',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $container);

        $this->assertTrue($container->hasDefinition('Nowo\PasswordPolicyBundle\EventListener\PasswordExpiryListener'));
        $this->assertTrue($container->hasDefinition('password_expiry_configuration.App\Entity\User'));
    }

    public function testLoadThrowsExceptionForNonExistentEntity(): void
    {
        $container = new ContainerBuilder();
        $configs = [
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
        $configs = [
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
        $mockEntityClass = get_class($this->createMock(HasPasswordPolicyInterface::class));
        
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
}

