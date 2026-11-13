<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectAwareInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function is_object;

/**
 * Opt-in {@see CurrentPasswordPolicySubjectProviderInterface} (since 1.12.0): the security token user when it is a
 * {@see PasswordPolicySubjectInterface}, or the subject it exposes through {@see PasswordPolicySubjectAwareInterface}.
 * An entity user (`HasPasswordPolicyInterface`) is left to the entity check. Enable it by aliasing
 * CurrentPasswordPolicySubjectProviderInterface to this class in your services configuration (see docs/ARCHITECTURE.md).
 *
 * Reads the token on every call; keeps no state (FrankenPHP worker safe).
 */
final readonly class TokenPasswordPolicySubjectProvider implements CurrentPasswordPolicySubjectProviderInterface
{
    public function __construct(private ?TokenStorageInterface $tokenStorage = null)
    {
    }

    public function getCurrentSubject(): ?PasswordPolicySubjectInterface
    {
        $user = $this->tokenStorage?->getToken()?->getUser();
        if (!is_object($user) || $user instanceof HasPasswordPolicyInterface) {
            return null;
        }

        if ($user instanceof PasswordPolicySubjectInterface) {
            return $user;
        }

        if ($user instanceof PasswordPolicySubjectAwareInterface) {
            $subject = $user->getPasswordPolicySubject();

            return $subject instanceof PasswordPolicySubjectInterface && !$subject instanceof HasPasswordPolicyInterface ? $subject : null;
        }

        return null;
    }
}
