<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Kernel;

use Nowo\PasswordPolicyBundle\NowoPasswordPolicyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Real kernel (FrameworkBundle + this bundle). Services such as logger, event_dispatcher and cache.app
 * belong to other bundles, so they are invisible to PasswordPolicyExtension::load() (isolated container).
 */
final class PasswordPolicyTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $expiryListenerConfig extra nowo_password_policy.expiry_listener options
     */
    public function __construct(private readonly array $expiryListenerConfig = [])
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new NowoPasswordPolicyBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-password-policy-test-kernel/' . $this->configHash() . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-password-policy-test-kernel/' . $this->configHash() . '/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        // Entity listeners are only referenced by DoctrineBundle (not installed here): keep them for inspection.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ($container->getDefinitions() as $id => $definition) {
                    if (str_starts_with($id, 'nowo_password_policy.entity_listener.')) {
                        $definition->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'php_errors'           => ['log' => true],
            'translator'           => ['enabled' => true, 'fallbacks' => ['en']],
            'validation'           => ['enabled' => true],
            'cache'                => ['app' => 'cache.adapter.array'],
        ]);
        $container->loadFromExtension('nowo_password_policy', [
            'enable_cache'    => true,
            'expiry_listener' => $this->expiryListenerConfig,
            'entities'        => [
                KernelUser::class => ['reset_password_route_name' => 'reset_password', 'expiry_days' => 90],
            ],
        ]);

        // No SecurityBundle in this kernel: provide the security services the bundle depends on.
        $container->register('security.token_storage', TokenStorage::class)->setPublic(true);
        $container->setAlias(TokenStorageInterface::class, 'security.token_storage');
        $container->register(UserPasswordHasherInterface::class, UserPasswordHasher::class)
            ->setArguments([new Definition(PasswordHasherFactory::class, [[KernelUser::class => ['algorithm' => 'bcrypt', 'cost' => 4]]])]);
        $container->register('logger', BufferingLogger::class)->setPublic(true);
        // Application-defined storage for the flash_throttle_storage_service option.
        $container->register('app.flash_storage', InMemoryFlashThrottleStorage::class);
    }

    private function configHash(): string
    {
        return md5(serialize($this->expiryListenerConfig));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('reset_password', '/reset-password');
    }
}
