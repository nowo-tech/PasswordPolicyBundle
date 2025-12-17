<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;


use Symfony\Component\Validator\Constraint;

/**
 * Constraint for validating that a password has not been used before.
 *
 * This constraint ensures that users cannot reuse passwords that are stored
 * in their password history.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
class PasswordPolicy extends Constraint
{
    /**
     * Error code for when a password is found in the history.
     */
    const PASSWORD_IN_HISTORY = 'PASSWORD_IN_HISTORY';

    /**
     * The error message template.
     *
     * @var string
     */
    public $message = 'Cannot change your password to an old one. You used this password {{ days }}';
}
