<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Mocks;

use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Traits\PasswordHistoryTrait;

/**
 * Class PasswordHistoryMock.
 * Mocked class
 */
class PasswordHistoryMock implements PasswordHistoryInterface
{
    use PasswordHistoryTrait;

    private $user;

    /**
     * @param $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }
}
