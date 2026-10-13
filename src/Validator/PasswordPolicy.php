<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint for validating that a password has not been used before.
 *
 * This constraint ensures that users cannot reuse passwords that are stored
 * in their password history.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class PasswordPolicy extends Constraint
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
     * null (default) uses the entity's `detect_password_extensions` YAML setting (false when unset).
     *
     * @var bool|null
     */
    public $detectExtensions;

    /**
     * Minimum length of the base password to consider for extension detection.
     *
     * null (default) uses the entity's `extension_min_length` YAML setting (4 when unset).
     *
     * @var int|null
     */
    public $extensionMinLength;

    /**
     * Whether to reject the entity's current password too (it only enters the history on the next change).
     *
     * Requires the entity to still hold the current hash when it is validated, which is the usual flow
     * (validate the plain password, then hash and set it). Disable it if your application hashes and
     * sets the new password before validating the entity.
     *
     * @var bool
     */
    public $checkCurrentPassword = true;
}
