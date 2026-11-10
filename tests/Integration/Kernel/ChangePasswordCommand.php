<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Integration\Kernel;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectAwareInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;

/** Application command validated with #[PasswordPolicy]: no entity, a read-only subject. */
final class ChangePasswordCommand implements PasswordPolicySubjectAwareInterface
{
    public function __construct(
        #[PasswordPolicy]
        public string $newPassword,
        private readonly HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null $subject,
    ) {
    }

    public function getPasswordPolicySubject(): HasPasswordPolicyInterface|PasswordPolicySubjectInterface|null
    {
        return $this->subject;
    }
}
