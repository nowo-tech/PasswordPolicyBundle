<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Exceptions;

use Exception;

/**
 * Exception thrown when there is a configuration error in the Password Policy Bundle.
 *
 * This exception is typically thrown when entity classes are not found or
 * do not implement the required interfaces.
 */
class ConfigurationException extends Exception
{

}
