<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit;

use Nowo\PasswordPolicyBundle\DependencyInjection\PasswordPolicyExtension;
use Nowo\PasswordPolicyBundle\NowoPasswordPolicyBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * Tests for NowoPasswordPolicyBundle.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class PasswordPolicyBundleTest extends TestCase
{
    private NowoPasswordPolicyBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new NowoPasswordPolicyBundle();
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();

        $this->assertInstanceOf(ExtensionInterface::class, $extension);
        $this->assertInstanceOf(PasswordPolicyExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $extension1 = $this->bundle->getContainerExtension();
        $extension2 = $this->bundle->getContainerExtension();

        $this->assertSame($extension1, $extension2);
    }

    public function testGetContainerExtensionAlias(): void
    {
        $extension = $this->bundle->getContainerExtension();
        $this->assertInstanceOf(PasswordPolicyExtension::class, $extension);

        $this->assertSame('nowo_password_policy', $extension->getAlias());
    }

    public function testDeprecatedPasswordPolicyBundleClassAlias(): void
    {
        $this->assertTrue(class_exists(\Nowo\PasswordPolicyBundle\PasswordPolicyBundle::class));
        $this->assertInstanceOf(NowoPasswordPolicyBundle::class, new \Nowo\PasswordPolicyBundle\PasswordPolicyBundle());
    }
}
