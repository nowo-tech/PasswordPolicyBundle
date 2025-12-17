<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Exceptions;

use Exception;

/**
 * Exception thrown when there is a validation error in the Password Policy Bundle.
 *
 * This exception is typically thrown when validation constraints fail or
 * when entities do not implement the required interfaces during validation.
 */
class ValidationException extends Exception
{
}
