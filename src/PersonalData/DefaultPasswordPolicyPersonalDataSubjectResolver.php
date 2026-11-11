<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use InvalidArgumentException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Subject\PasswordPolicySubjectResolverInterface;

/**
 * Default {@see PasswordPolicyPersonalDataSubjectResolverInterface}: the subject the bundle's
 * {@see PasswordPolicySubjectResolverInterface} finds for the user object (a HasPasswordPolicyInterface entity, a
 * PasswordPolicySubjectInterface or a PasswordPolicySubjectAwareInterface). The original identifier is not used: the
 * history is keyed by subject id.
 */
final readonly class DefaultPasswordPolicyPersonalDataSubjectResolver implements PasswordPolicyPersonalDataSubjectResolverInterface
{
    public function __construct(
        private PasswordPolicySubjectResolverInterface $subjectResolver,
    ) {
    }

    public function subjectsFor(object $user, ?string $originalIdentifier = null): array
    {
        $subject = $this->subjectResolver->resolve($user);

        try {
            return match (true) {
                $subject instanceof HasPasswordPolicyInterface     => [PasswordPolicyPersonalDataSubject::fromEntity($subject)],
                $subject instanceof PasswordPolicySubjectInterface => [PasswordPolicyPersonalDataSubject::fromPolicySubject($subject)],
                default                                            => [],
            };
        } catch (InvalidArgumentException) {
            // No id yet (never persisted): nothing is stored for it.
            return [];
        }
    }
}
