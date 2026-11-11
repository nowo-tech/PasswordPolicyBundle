<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

/**
 * Port: exports the password policy data the bundle keeps for one subject (GDPR art. 15 / 20).
 *
 * Change dates only: password hashes are never exported.
 */
interface PasswordPolicyPersonalDataExporterInterface
{
    /**
     * JSON-serialisable: `count` and `entries`, newest first, each `changed_at` an ATOM date string (null when the
     * entry has no date).
     *
     * @return array{count: int, entries: list<array{changed_at: string|null}>}
     */
    public function export(PasswordPolicyPersonalDataSubject $subject): array;
}
