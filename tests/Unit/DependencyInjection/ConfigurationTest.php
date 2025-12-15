<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\DependencyInjection;

use Nowo\PasswordPolicyBundle\DependencyInjection\Configuration;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use Symfony\Component\Config\Definition\Processor;

/**
 * Tests for Configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2024 Nowo.tech
 */
final class ConfigurationTest extends UnitTestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testGetConfigTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();
        $this->assertNotNull($treeBuilder);
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'reset_password_route_name' => 'user_reset_password',
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('entities', $config);
        $this->assertArrayHasKey('App\Entity\User', $config['entities']);
        $this->assertSame('password', $config['entities']['App\Entity\User']['password_field']);
        $this->assertSame('passwordHistory', $config['entities']['App\Entity\User']['password_history_field']);
        $this->assertSame(3, $config['entities']['App\Entity\User']['passwords_to_remember']);
        $this->assertSame(90, $config['entities']['App\Entity\User']['expiry_days']);
        $this->assertSame('user_reset_password', $config['entities']['App\Entity\User']['reset_password_route_name']);
        $this->assertSame([], $config['entities']['App\Entity\User']['notified_routes']);
        $this->assertSame([], $config['entities']['App\Entity\User']['excluded_notified_routes']);

        $this->assertArrayHasKey('expiry_listener', $config);
        $this->assertSame(0, $config['expiry_listener']['priority']);
        $this->assertArrayHasKey('error_msg', $config['expiry_listener']);
        $this->assertSame('error', $config['expiry_listener']['error_msg']['type']);
    }

    public function testCustomConfiguration(): void
    {
        $configs = [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'password_field' => 'userPassword',
                        'password_history_field' => 'history',
                        'passwords_to_remember' => 10,
                        'expiry_days' => 60,
                        'reset_password_route_name' => 'reset',
                        'notified_routes' => ['dashboard', 'profile'],
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

        $config = $this->processor->processConfiguration($this->configuration, $configs);

        $this->assertSame('userPassword', $config['entities']['App\Entity\User']['password_field']);
        $this->assertSame('history', $config['entities']['App\Entity\User']['password_history_field']);
        $this->assertSame(10, $config['entities']['App\Entity\User']['passwords_to_remember']);
        $this->assertSame(60, $config['entities']['App\Entity\User']['expiry_days']);
        $this->assertSame(['dashboard', 'profile'], $config['entities']['App\Entity\User']['notified_routes']);
        $this->assertSame(['logout'], $config['entities']['App\Entity\User']['excluded_notified_routes']);
        $this->assertSame(10, $config['expiry_listener']['priority']);
        $this->assertSame('lock', $config['expiry_listener']['lock_route']);
        $this->assertSame('warning', $config['expiry_listener']['error_msg']['type']);
    }

    public function testMultipleEntities(): void
    {
        $configs = [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'reset_password_route_name' => 'user_reset',
                    ],
                    'App\Entity\Admin' => [
                        'reset_password_route_name' => 'admin_reset',
                        'expiry_days' => 30,
                    ],
                ],
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $configs);

        $this->assertCount(2, $config['entities']);
        $this->assertArrayHasKey('App\Entity\User', $config['entities']);
        $this->assertArrayHasKey('App\Entity\Admin', $config['entities']);
        $this->assertSame(90, $config['entities']['App\Entity\User']['expiry_days']);
        $this->assertSame(30, $config['entities']['App\Entity\Admin']['expiry_days']);
    }

    public function testNullValuesUseDefaults(): void
    {
        $configs = [
            [
                'entities' => [
                    'App\Entity\User' => [
                        'reset_password_route_name' => 'reset',
                        'password_field' => null,
                        'passwords_to_remember' => null,
                        'expiry_days' => null,
                    ],
                ],
            ],
        ];

        $config = $this->processor->processConfiguration($this->configuration, $configs);

        $this->assertSame('password', $config['entities']['App\Entity\User']['password_field']);
        $this->assertSame(3, $config['entities']['App\Entity\User']['passwords_to_remember']);
        $this->assertSame(90, $config['entities']['App\Entity\User']['expiry_days']);
    }
}

