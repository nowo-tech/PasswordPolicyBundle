<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\DependencyInjection\Compiler;

use Nowo\PasswordPolicyBundle\History\ErasablePasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;
use Nowo\PasswordPolicyBundle\PersonalData\UserKit\PasswordHistoryUserKitEraser;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function class_exists;
use function interface_exists;
use function is_a;
use function is_string;
use function sprintf;

/**
 * Removes the UserKit `password_history` eraser when the history adapter aliased to
 * {@see PasswordHistoryRepositoryInterface} cannot erase (does not implement
 * {@see ErasablePasswordHistoryRepositoryInterface}), and records why in the container compilation log.
 *
 * UserKit has no "skipped" outcome: an eraser that runs is reported as done. Not registering it keeps
 * `password_history` out of the keys UserKit reports, instead of reporting an erasure that did not happen.
 * When the adapter class cannot be determined (factory, child definition, unknown class), the eraser stays.
 */
final class UserKitEraserErasableHistoryPass implements CompilerPassInterface
{
    /** {@see PasswordHistoryUserKitEraser::KEY}, as a literal: reading the constant would autoload a class whose UserKit interface may be missing. */
    private const ERASER_KEY = 'password_history';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PasswordHistoryUserKitEraser::class) || !$container->has(PasswordHistoryRepositoryInterface::class)) {
            return;
        }

        $adapter = $container->findDefinition(PasswordHistoryRepositoryInterface::class);
        if ($adapter instanceof ChildDefinition || $adapter->getFactory() !== null) {
            return;
        }

        $class = $container->getParameterBag()->resolveValue($adapter->getClass());
        if (!is_string($class) || (!class_exists($class) && !interface_exists($class))) {
            return;
        }

        if (is_a($class, ErasablePasswordHistoryRepositoryInterface::class, true)) {
            return;
        }

        $container->removeDefinition(PasswordHistoryUserKitEraser::class);
        $container->log($this, sprintf(
            'UserKit eraser "%s" not registered: the password history adapter %s does not implement %s, so UserKit erasure would report the history as erased while it is not. Implement removeAll() on your adapter.',
            self::ERASER_KEY,
            $class,
            ErasablePasswordHistoryRepositoryInterface::class,
        ));
    }
}
