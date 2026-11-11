<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface;

use function count;

use const DATE_ATOM;
use const PHP_INT_MAX;

/**
 * Default {@see PasswordPolicyPersonalDataExporterInterface}: every entry of the history port, change dates only.
 */
final readonly class PasswordPolicyPersonalDataExporter implements PasswordPolicyPersonalDataExporterInterface
{
    public function __construct(
        private PasswordHistoryRepositoryInterface $historyRepository,
    ) {
    }

    public function export(PasswordPolicyPersonalDataSubject $subject): array
    {
        $entries = [];
        foreach ($this->historyRepository->findRecent($subject->subjectClass, $subject->subjectId, PHP_INT_MAX) as $entry) {
            // Never the hash.
            $entries[] = ['changed_at' => $entry->createdAt?->format(DATE_ATOM)];
        }

        return ['count' => count($entries), 'entries' => $entries];
    }
}
