<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Traits\PasswordHistoryTrait;

/**
 * Class PasswordHistoryMock.
 * Mocked class.
 */
class PasswordHistoryMock implements PasswordHistoryInterface
{
    use PasswordHistoryTrait;

    private ?HasPasswordPolicyInterface $user = null;

    public function setUser(?HasPasswordPolicyInterface $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?HasPasswordPolicyInterface
    {
        return $this->user;
    }
}
