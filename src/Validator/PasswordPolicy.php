<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for validating that a password has not been used before.
 *
 * This constraint ensures that users cannot reuse passwords that are stored
 * in their password history.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PasswordPolicy extends Constraint
{
    /**
     * Error code for when a password is found in the history.
     */
    public const PASSWORD_IN_HISTORY = 'PASSWORD_IN_HISTORY';

    /**
     * Error code for when a password is an extension of an old password.
     */
    public const PASSWORD_EXTENSION = 'PASSWORD_EXTENSION';

    /**
     * The error message template for exact password matches.
     *
     * @var string
     */
    public $message = 'Cannot change your password to an old one. You used this password {{ days }}';

    /**
     * The error message template for password extensions.
     *
     * @var string
     */
    public $extensionMessage = 'Cannot change your password to an extension of an old one. You used a similar password {{ days }}';

    /**
     * Whether to detect password extensions (e.g., "password123" is an extension of "password").
     *
     * @var bool
     */
    public $detectExtensions = false;

    /**
     * Minimum length of the base password to consider for extension detection.
     *
     * @var int
     */
    public $extensionMinLength = 4;
}
