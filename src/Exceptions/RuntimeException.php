<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Exceptions;

use Exception;

/**
 * Exception thrown when there is a runtime error in the Password Policy Bundle.
 *
 * This exception is typically thrown when runtime checks fail, such as when
 * entities do not implement required interfaces or methods are missing.
 */
class RuntimeException extends Exception
{
}
