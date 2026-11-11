<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData\UserKit;

use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubjectResolverInterface;
use Nowo\UserKitBundle\PersonalData\ErasureRequest;
use Nowo\UserKitBundle\PersonalData\PersonalDataEraserInterface;

use function get_object_vars;
use function is_string;

/**
 * UserKit personal-data eraser `password_history`: runs {@see PasswordPolicyPersonalDataEraserInterface} once per
 * subject of the user. Registered (priority 100, before the host identity eraser) when UserKit is installed and
 * `nowo_password_policy.personal_data.enabled` is true. Joins UserKit's transaction; no flush.
 */
final readonly class PasswordHistoryUserKitEraser implements PersonalDataEraserInterface
{
    public const KEY = 'password_history';

    public const PRIORITY = 100;

    public function __construct(
        private PasswordPolicyPersonalDataEraserInterface $eraser,
        private PasswordPolicyPersonalDataSubjectResolverInterface $subjects,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function supports(object $user): bool
    {
        return $this->subjects->subjectsFor($user) !== [];
    }

    public function erase(ErasureRequest $request): void
    {
        // UserKit >= 1.12 captures the identifier before any eraser ran; older versions have no such property.
        $original = get_object_vars($request)['originalIdentifier'] ?? null;

        foreach (UserKitSubjects::distinct($this->subjects->subjectsFor($request->user, is_string($original) ? $original : null)) as $subject) {
            $this->eraser->erase($subject);
        }
    }
}
