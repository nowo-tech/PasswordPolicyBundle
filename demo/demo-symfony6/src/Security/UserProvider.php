<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * User provider for loading users from the database.
 *
 * This provider loads users from the UserRepository to support authentication.
 */
class UserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * @param UserInterface $user The user to refresh
     * @return UserInterface The refreshed user
     * @throws UnsupportedUserException If the user is not supported
     * @throws UserNotFoundException If the user is not found
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $refreshedUser = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if (!$refreshedUser) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $user->getUserIdentifier()));
        }

        return $refreshedUser;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class The user class name
     * @return bool True if the class is supported
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Loads the user for the given user identifier (email).
     *
     * @param string $identifier The user identifier (email)
     * @return UserInterface The user
     * @throws UserNotFoundException If the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);
        if (!$user) {
            throw new UserNotFoundException(sprintf('User with email "%s" not found.', $identifier));
        }

        return $user;
    }
}

