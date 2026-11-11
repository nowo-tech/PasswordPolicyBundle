<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

/**
 * SPI: the password policy subjects of a user object, for the UserKit eraser and export provider.
 *
 * The default, {@see DefaultPasswordPolicyPersonalDataSubjectResolver}, asks the bundle's subject resolver. Set
 * `nowo_password_policy.personal_data.subject_resolver` to your own service when the user object is not the subject.
 */
interface PasswordPolicyPersonalDataSubjectResolverInterface
{
    /**
     * @param string|null $originalIdentifier the user identifier before any eraser ran (UserKit), when known
     *
     * @return list<PasswordPolicyPersonalDataSubject> an empty list when the user has no password policy data
     */
    public function subjectsFor(object $user, ?string $originalIdentifier = null): array;
}
