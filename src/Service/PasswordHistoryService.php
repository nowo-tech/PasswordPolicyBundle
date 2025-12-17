<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

/**
 * Service for managing password history cleanup.
 *
 * This service handles the removal of old password history entries when the
 * configured limit is exceeded.
 */
class PasswordHistoryService implements PasswordHistoryServiceInterface
{
    /**
     * Gets password history items that should be removed to maintain the history limit.
     *
     * The method sorts password history by creation date (newest first) and identifies
     * items beyond the specified limit for removal.
     *
     * @param HasPasswordPolicyInterface $hasPasswordPolicy The entity to check password history for
     * @param int                        $historyLimit      The maximum number of password history entries to keep
     *
     * @return array Array of password history items that should be removed
     */
    public function getHistoryItemsForCleanup(HasPasswordPolicyInterface $hasPasswordPolicy, int $historyLimit): array
    {
        $historyCollection = $hasPasswordPolicy->getPasswordHistory();

        $len = $historyCollection->count();
        $removedItems = [];

        if ($len > $historyLimit) {
            $historyArray = $historyCollection->toArray();

            usort($historyArray, function (PasswordHistoryInterface $a, PasswordHistoryInterface $b): int|float {
                $aTs = $a->getCreatedAt()->format(format: 'U');
                $bTs = $b->getCreatedAt()->format(format: 'U');

                return $bTs - $aTs;
            });

            $historyForCleanup = array_slice(array: $historyArray, offset: $historyLimit);

            foreach ($historyForCleanup as $item) {
                $hasPasswordPolicy->removePasswordHistory($item);
                $removedItems[] = $item;
            }
        }

        return $removedItems;
    }
}
