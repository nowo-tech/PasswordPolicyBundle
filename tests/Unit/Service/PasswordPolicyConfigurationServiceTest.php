<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordPolicyConfigurationService;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class PasswordPolicyConfigurationServiceTest extends UnitTestCase
{
    public function testSetAndGetEntityConfiguration(): void
    {
        $service = new PasswordPolicyConfigurationService();
        $service->setEntityConfiguration('App\Entity\User', [
            'detect_password_extensions' => true,
            'extension_min_length'       => 6,
        ]);

        $this->assertTrue($service->getEntityConfiguration('App\Entity\User', 'detect_password_extensions', false));
        $this->assertSame(6, $service->getEntityConfiguration('App\Entity\User', 'extension_min_length', 4));
    }

    public function testGetEntityConfigurationReturnsDefaultWhenKeyMissing(): void
    {
        $service = new PasswordPolicyConfigurationService();
        $service->setEntityConfiguration('App\Entity\User', ['detect_password_extensions' => true]);

        $this->assertSame(4, $service->getEntityConfiguration('App\Entity\User', 'extension_min_length', 4));
        $this->assertNull($service->getEntityConfiguration('App\Entity\User', 'unknown_key'));
    }

    public function testHasEntityConfiguration(): void
    {
        $service = new PasswordPolicyConfigurationService();
        $service->setEntityConfiguration('App\Entity\User', ['detect_password_extensions' => true]);

        $this->assertTrue($service->hasEntityConfiguration('App\Entity\User', 'detect_password_extensions'));
        $this->assertFalse($service->hasEntityConfiguration('App\Entity\User', 'extension_min_length'));
        $this->assertFalse($service->hasEntityConfiguration('Other\Entity', 'detect_password_extensions'));
    }

    public function testResolveEntityClass(): void
    {
        $service = new PasswordPolicyConfigurationService();
        $service->setEntityConfiguration(HasPasswordPolicyInterface::class, ['passwords_to_remember' => 10]);
        $service->setEntityConfiguration('App\Domain\User', []);

        $this->assertSame('App\Domain\User', $service->resolveEntityClass('App\Domain\User'));
        $this->assertSame('App\Domain\User', $service->resolveEntityClass('Proxies\__CG__\App\Domain\User'));
        $this->assertSame(HasPasswordPolicyInterface::class, $service->resolveEntityClass(KernelUser::class));
        $this->assertNull($service->resolveEntityClass('App\Other'));
    }
}
