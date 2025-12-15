<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;

interface PasswordHistoryServiceInterface
{
    public function getHistoryItemsForCleanup(HasPasswordPolicyInterface $hasPasswordPolicy, int $historyLimit): array;
}
