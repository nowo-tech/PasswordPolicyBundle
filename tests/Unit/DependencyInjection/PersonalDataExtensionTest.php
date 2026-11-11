<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\DependencyInjection;

use Nowo\PasswordPolicyBundle\DependencyInjection\PasswordPolicyExtension;
use Nowo\PasswordPolicyBundle\PersonalData\DefaultPasswordPolicyPersonalDataSubjectResolver;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraser;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporter;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporterInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubjectResolverInterface;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitEraser;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitExportProvider;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\KernelUser;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function interface_exists;

final class PersonalDataExtensionTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PasswordPolicyExtension())->load([[
            'entities' => [KernelUser::class => ['reset_password_route_name' => 'reset_password']],
        ] + $config], $container);

        return $container;
    }

    public function testPortsAreAlwaysRegistered(): void
    {
        $container = $this->load(['personal_data' => ['enabled' => false]]);

        self::assertSame(PasswordPolicyPersonalDataEraser::class, (string) $container->getAlias(PasswordPolicyPersonalDataEraserInterface::class));
        self::assertTrue($container->getAlias(PasswordPolicyPersonalDataEraserInterface::class)->isPublic());
        self::assertSame(PasswordPolicyPersonalDataExporter::class, (string) $container->getAlias(PasswordPolicyPersonalDataExporterInterface::class));
        self::assertSame(DefaultPasswordPolicyPersonalDataSubjectResolver::class, (string) $container->getAlias(PasswordPolicyPersonalDataSubjectResolverInterface::class));

        // The eraser forgets the entry of the same throttle storage the expiry listener writes.
        $storage = $container->getDefinition(PasswordPolicyPersonalDataEraser::class)->getArgument('$flashThrottleStorage');
        self::assertInstanceOf(Reference::class, $storage);
        self::assertSame('nowo_password_policy.expiry_flash_throttle_storage.session', (string) $storage);
    }

    public function testUserKitAdaptersFollowTheInstallAndTheSwitch(): void
    {
        $installed = interface_exists(PersonalDataEraserInterface::class);
        $container = $this->load();

        self::assertSame($installed, $container->hasDefinition(PasswordHistoryUserKitEraser::class));
        self::assertSame($installed, $container->hasDefinition(PasswordHistoryUserKitExportProvider::class));
        if ($installed) {
            self::assertSame([['priority' => 100]], $container->getDefinition(PasswordHistoryUserKitEraser::class)->getTag('nowo_user_kit.personal_data.eraser'));
            self::assertSame([['priority' => 60]], $container->getDefinition(PasswordHistoryUserKitExportProvider::class)->getTag('nowo_user_kit.personal_data.export_provider'));
        }

        $disabled = $this->load(['personal_data' => ['enabled' => false]]);
        self::assertFalse($disabled->hasDefinition(PasswordHistoryUserKitEraser::class));
        self::assertFalse($disabled->hasDefinition(PasswordHistoryUserKitExportProvider::class));
    }

    public function testCustomSubjectResolverAndCacheStorage(): void
    {
        $container = $this->load([
            'personal_data'   => ['subject_resolver' => 'app.password_policy_subjects'],
            'expiry_listener' => ['flash_throttle_storage' => 'cache'],
        ]);

        self::assertSame('app.password_policy_subjects', (string) $container->getAlias(PasswordPolicyPersonalDataSubjectResolverInterface::class));
        self::assertSame('nowo_password_policy.expiry_flash_throttle_storage.cache', (string) $container->getDefinition(PasswordPolicyPersonalDataEraser::class)->getArgument('$flashThrottleStorage'));
    }
}
