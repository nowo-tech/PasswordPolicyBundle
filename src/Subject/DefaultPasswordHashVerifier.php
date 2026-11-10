<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Exception;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

use function password_get_info;
use function password_verify;

/**
 * Default {@see PasswordHashVerifierInterface}: password_verify() for password_hash() formats (bcrypt, argon2), then
 * the Symfony password hasher configured for the subject class (security.password_hashers) for other formats.
 * Hashes are never compared directly.
 */
final readonly class DefaultPasswordHashVerifier implements PasswordHashVerifierInterface
{
    public function __construct(
        private ?PasswordHasherFactoryInterface $passwordHasherFactory = null,
    ) {
    }

    public function verify(string $subjectClass, string $passwordHash, string $plainPassword): bool
    {
        if ($passwordHash === '') {
            return false;
        }

        if (password_verify($plainPassword, $passwordHash)) {
            return true;
        }

        // password_verify() answered definitively for the formats it recognises.
        if (password_get_info($passwordHash)['algo'] !== null || !$this->passwordHasherFactory instanceof PasswordHasherFactoryInterface) {
            return false;
        }

        try {
            return $this->passwordHasherFactory->getPasswordHasher($subjectClass)->verify($passwordHash, $plainPassword);
        } catch (Exception) {
            // No hasher configured for the class, or a hash the hasher cannot read.
            return false;
        }
    }
}
