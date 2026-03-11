<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Traits\PasswordHistoryTrait;

/**
 * Class PasswordHistoryMock.
 * Mocked class.
 */
class PasswordHistoryMock implements PasswordHistoryInterface
{
    use PasswordHistoryTrait;

    /** @var \Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface|null */
    private $user = null;

    public function setUser(?\Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?\Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface
    {
        return $this->user;
    }
}
