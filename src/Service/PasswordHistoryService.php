<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;


use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

class PasswordHistoryService implements PasswordHistoryServiceInterface
{
    /**
     *
     * @return array Removed items
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
            }
        }

        return $removedItems;
    }

}
