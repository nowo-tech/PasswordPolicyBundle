<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Mockery;
use Mockery\MockInterface;
use Nowo\PasswordPolicyBundle\EventListener\PasswordEntityListener;
use Nowo\PasswordPolicyBundle\EventListener\PasswordPolicyWorkerBoundaryListener;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Service\PasswordHistoryServiceInterface;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;
use ReflectionProperty;
use Symfony\Contracts\Service\ResetInterface;

use function spl_object_id;

/**
 * Audit UK-PP-1: a failed password-changing flush closes the host's entity manager; without kernel reset nothing else
 * replaces it, so the boundary does (REQ-RUNTIME-003 rule 2: closed managers only, open ones never touched).
 */
final class PasswordPolicyWorkerBoundaryClosedManagerTest extends UnitTestCase
{
    public function testBoundaryReplacesOnlyTheClosedManagerOfAFailedPasswordFlush(): void
    {
        $closed = $this->manager(false);
        $open   = $this->manager(true);
        $other  = $this->manager(false);

        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldReceive('getManagerNames')->andReturn(['default' => 'doctrine.orm.default_entity_manager', 'audit' => 'doctrine.orm.audit_entity_manager']);
        $registry->shouldReceive('getManager')->with('default')->andReturn($other);
        $registry->shouldReceive('getManager')->with('audit')->andReturn($closed);
        $registry->shouldReceive('resetManager')->once()->with('audit')->andReturn($this->manager(true));

        $listener = $this->listenerWithFailedFlushes($closed, $open);
        $plain    = Mockery::mock(ResetInterface::class);
        $plain->shouldReceive('reset')->once();

        (new PasswordPolicyWorkerBoundaryListener([$listener, $plain], $registry))->clear();

        self::assertSame([], $listener->takeFailedFlushManagers(), 'Taken once: the next boundary does nothing');
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function testClosedManagerUnknownToTheRegistryIsLeftAlone(): void
    {
        $closed   = $this->manager(false);
        $registry = Mockery::mock(ManagerRegistry::class);
        $registry->shouldReceive('getManagerNames')->andReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->shouldReceive('getManager')->with('default')->andReturn($this->manager(true));
        $registry->shouldNotReceive('resetManager');

        (new PasswordPolicyWorkerBoundaryListener([$this->listenerWithFailedFlushes($closed)], $registry))->clear();
        (new PasswordPolicyWorkerBoundaryListener([$this->listenerWithFailedFlushes($closed)]))->clear();

        $this->addToAssertionCount(1);
    }

    private function manager(bool $open): EntityManagerInterface&MockInterface
    {
        $manager = Mockery::mock(EntityManagerInterface::class);
        $manager->shouldReceive('isOpen')->andReturn($open);

        return $manager;
    }

    private function listenerWithFailedFlushes(EntityManagerInterface ...$managers): PasswordEntityListener
    {
        $listener = new PasswordEntityListener(Mockery::mock(PasswordHistoryServiceInterface::class), 'password', 'passwordHistory', 3, HasPasswordPolicyInterface::class);
        $failed   = [];
        foreach ($managers as $manager) {
            $failed[spl_object_id($manager)] = $manager;
        }
        (new ReflectionProperty(PasswordEntityListener::class, 'failedFlushManagers'))->setValue($listener, $failed);

        return $listener;
    }
}
