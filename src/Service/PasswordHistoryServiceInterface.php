<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;

/**
 * Interface for password history service.
 *
 * This service handles the cleanup of password history entries when the
 * configured limit is exceeded.
 */
interface PasswordHistoryServiceInterface
{
    /**
     * Gets password history items that should be removed to maintain the history limit.
     *
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     * @param int                        $historyLimit      The maximum number of password history entries to keep
     *
     * @return array Array of password history items that should be removed
     */
    public function getHistoryItemsForCleanup(HasPasswordPolicyInterface $hasPasswordPolicy, int $historyLimit): array;
}
