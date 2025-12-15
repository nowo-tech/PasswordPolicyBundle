<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;

class PasswordExpiryConfiguration
{
    private readonly string $entityClass;

    /**
     * PasswordExpiryConfiguration constructor.
     *
     * @param string $lockRoutes
     */
    public function __construct(
        string $class,
        private readonly int $expiryDays,
        private readonly array $lockRoutes = [],
        private readonly array $excludedRoutes = []
    ) {
        if (!is_a($class, HasPasswordPolicyInterface::class, true)) {
            throw new RuntimeException(sprintf(
                'Entity %s must implement %s interface',
                $class,
                HasPasswordPolicyInterface::class
            ));
        }

        $this->entityClass = $class;
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getExpiryDays(): int
    {
        return $this->expiryDays;
    }

    /**
     * @return string
     */
    public function getLockRoutes(): array
    {
        return $this->lockRoutes;
    }

    public function getExcludedRoutes(): array
    {
        return $this->excludedRoutes;
    }
}
