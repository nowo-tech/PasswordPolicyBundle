<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Model;

use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use stdClass;

final class PasswordExpiryConfigurationTest extends UnitTestCase
{
    public function testGetters(): void
    {
        $config = new PasswordExpiryConfiguration(
            HasPasswordPolicyInterface::class,
            60,
            ['dashboard', 'profile'],
            ['login', 'logout'],
            'app_reset_password',
        );

        $this->assertSame(HasPasswordPolicyInterface::class, $config->getEntityClass());
        $this->assertSame(60, $config->getExpiryDays());
        $this->assertSame(['dashboard', 'profile'], $config->getLockRoutes());
        $this->assertSame(['login', 'logout'], $config->getExcludedRoutes());
        $this->assertSame('app_reset_password', $config->getResetPasswordRouteName());
        $this->assertNull($config->getResetPasswordRoutePattern());
    }

    public function testOptionalResetPasswordRoutePattern(): void
    {
        $config = new PasswordExpiryConfiguration(
            HasPasswordPolicyInterface::class,
            60,
            [],
            [],
            'fallback_reset',
            'app_reset_*',
        );

        $this->assertSame('fallback_reset', $config->getResetPasswordRouteName());
        $this->assertSame('app_reset_*', $config->getResetPasswordRoutePattern());
    }

    public function testConstructorWithInvalidEntityThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must implement');

        new PasswordExpiryConfiguration(stdClass::class, 90, [], [], 'reset');
    }

    public function testConstructorWithEmptyResetRoute(): void
    {
        $config = new PasswordExpiryConfiguration(
            HasPasswordPolicyInterface::class,
            90,
            [],
            [],
            '',
        );
        $this->assertSame('', $config->getResetPasswordRouteName());
    }
}
