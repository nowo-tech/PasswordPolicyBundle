<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData\UserKit;

use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;

use function array_values;

/**
 * @internal
 */
final class UserKitSubjects
{
    /**
     * The subjects without duplicates (byte-exact class + id), first occurrence kept, in order.
     *
     * @param list<PasswordPolicyPersonalDataSubject> $subjects
     *
     * @return list<PasswordPolicyPersonalDataSubject>
     */
    public static function distinct(array $subjects): array
    {
        $distinct = [];
        foreach ($subjects as $subject) {
            $distinct[$subject->subjectClass . "\0" . $subject->subjectId] ??= $subject;
        }

        return array_values($distinct);
    }
}
