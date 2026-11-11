<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\DependencyInjection;

use Nowo\PasswordPolicyBundle\DependencyInjection\Compiler\UserKitEraserErasableHistoryPass;
use Nowo\PasswordPolicyBundle\History\DoctrinePasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\NowoPasswordPolicyBundle;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitEraser;
use Nowo\PasswordPolicyBundle\Tests\Integration\Kernel\InMemoryPasswordHistoryRepository;
use Nowo\PasswordPolicyBundle\Tests\Unit\Mocks\NonErasablePasswordHistoryRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function implode;
use function str_contains;

final class UserKitEraserErasableHistoryPassTest extends TestCase
{
    private function container(string $adapterClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(PasswordHistoryUserKitEraser::class, PasswordHistoryUserKitEraser::class);
        $container->register('app.history', $adapterClass);
        $container->setAlias(PasswordHistoryRepositoryInterface::class, 'app.history');

        return $container;
    }

    private function process(ContainerBuilder $container): void
    {
        (new UserKitEraserErasableHistoryPass())->process($container);
    }

    public function testErasableAdaptersKeepTheEraser(): void
    {
        foreach ([DoctrinePasswordHistoryRepository::class, InMemoryPasswordHistoryRepository::class] as $class) {
            $container = $this->container($class);
            $this->process($container);

            self::assertTrue($container->hasDefinition(PasswordHistoryUserKitEraser::class), $class);
            self::assertSame([], $container->getCompiler()->getLog());
        }
    }

    public function testNonErasableAdapterRemovesTheEraserAndLogsWhy(): void
    {
        $container = $this->container(NonErasablePasswordHistoryRepository::class);
        $this->process($container);

        self::assertFalse($container->hasDefinition(PasswordHistoryUserKitEraser::class));
        $log = implode("\n", $container->getCompiler()->getLog());
        self::assertTrue(str_contains($log, 'password_history'));
        self::assertTrue(str_contains($log, NonErasablePasswordHistoryRepository::class));
    }

    public function testParameterisedClassIsResolved(): void
    {
        $container = $this->container('%app.history.class%');
        $container->setParameter('app.history.class', NonErasablePasswordHistoryRepository::class);
        $this->process($container);

        self::assertFalse($container->hasDefinition(PasswordHistoryUserKitEraser::class));
    }

    public function testUndeterminableAdaptersKeepTheEraser(): void
    {
        $factory = $this->container(NonErasablePasswordHistoryRepository::class);
        $factory->getDefinition('app.history')->setFactory([new Reference('app.factory'), 'create']);
        $this->process($factory);
        self::assertTrue($factory->hasDefinition(PasswordHistoryUserKitEraser::class));

        $child = $this->container(NonErasablePasswordHistoryRepository::class);
        $child->setDefinition('app.history', new ChildDefinition('app.parent'));
        $this->process($child);
        self::assertTrue($child->hasDefinition(PasswordHistoryUserKitEraser::class));

        $unknown = $this->container('App\Missing\HistoryAdapter');
        $this->process($unknown);
        self::assertTrue($unknown->hasDefinition(PasswordHistoryUserKitEraser::class));
    }

    public function testNothingToDoWithoutTheEraserOrTheAdapter(): void
    {
        $withoutEraser = new ContainerBuilder();
        $withoutEraser->register('app.history', NonErasablePasswordHistoryRepository::class);
        $withoutEraser->setAlias(PasswordHistoryRepositoryInterface::class, 'app.history');
        $this->process($withoutEraser);
        self::assertSame([], $withoutEraser->getCompiler()->getLog());

        $withoutAdapter = new ContainerBuilder();
        $withoutAdapter->register(PasswordHistoryUserKitEraser::class, PasswordHistoryUserKitEraser::class);
        $this->process($withoutAdapter);
        self::assertTrue($withoutAdapter->hasDefinition(PasswordHistoryUserKitEraser::class));
    }

    public function testTheBundleRegistersThePass(): void
    {
        $container = new ContainerBuilder();
        (new NowoPasswordPolicyBundle())->build($container);

        $registered = false;
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            $registered = $registered || $pass instanceof UserKitEraserErasableHistoryPass;
        }

        self::assertTrue($registered);
    }
}
