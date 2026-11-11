<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

/**
 * One entity manager for every class (test double without call recording).
 */
final readonly class SingleManagerRegistry implements ManagerRegistry
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function getDefaultConnectionName(): string
    {
        return 'default';
    }

    public function getConnection(?string $name = null): Connection
    {
        return $this->em->getConnection();
    }

    public function getConnections(): array
    {
        return ['default' => $this->em->getConnection()];
    }

    public function getConnectionNames(): array
    {
        return ['default' => 'default'];
    }

    public function getDefaultManagerName(): string
    {
        return 'default';
    }

    public function getManager(?string $name = null): ObjectManager
    {
        return $this->em;
    }

    public function getManagers(): array
    {
        return ['default' => $this->em];
    }

    public function resetManager(?string $name = null): ObjectManager
    {
        return $this->em;
    }

    public function getManagerNames(): array
    {
        return ['default' => 'default'];
    }

    public function getRepository(string $persistentObject, ?string $persistentManagerName = null): ObjectRepository
    {
        return $this->em->getRepository($persistentObject);
    }

    public function getManagerForClass(string $class): ObjectManager
    {
        return $this->em;
    }
}
