<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData\UserKit;

use DateTimeImmutable;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporterInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\PersonalDataExportProviderInterface;

use function count;
use function usort;

use const PHP_INT_MIN;

/**
 * UserKit export section `password_history`: `{count, entries: [{changed_at}]}` (change dates only, newest first,
 * never hashes) over every subject of the user. Registered with UserKit installed and
 * `nowo_password_policy.personal_data.enabled` true.
 */
final readonly class PasswordHistoryUserKitExportProvider implements PersonalDataExportProviderInterface
{
    public const KEY = 'password_history';

    public const PRIORITY = 60;

    public function __construct(
        private PasswordPolicyPersonalDataExporterInterface $exporter,
        private PasswordPolicyPersonalDataSubjectResolverInterface $subjects,
    ) {
    }

    public function getSectionKey(): string
    {
        return self::KEY;
    }

    public function supports(object $user): bool
    {
        return $this->subjects->subjectsFor($user) !== [];
    }

    /**
     * @return array{count: int, entries: list<array{changed_at: string|null}>}
     */
    public function export(object $user): array
    {
        $entries = [];
        foreach (UserKitSubjects::distinct($this->subjects->subjectsFor($user)) as $subject) {
            foreach ($this->exporter->export($subject)['entries'] as $entry) {
                $entries[] = $entry;
            }
        }

        // Newest first across subjects; undated entries last.
        usort($entries, static fn (array $a, array $b): int => self::timestamp($b['changed_at']) <=> self::timestamp($a['changed_at']));

        return ['count' => count($entries), 'entries' => $entries];
    }

    private static function timestamp(?string $changedAt): int
    {
        return $changedAt === null ? PHP_INT_MIN : (new DateTimeImmutable($changedAt))->getTimestamp();
    }
}
