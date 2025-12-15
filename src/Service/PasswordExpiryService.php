<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Service;


use Carbon\Carbon;
use DateTime;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordExpiryConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PasswordExpiryService implements PasswordExpiryServiceInterface
{
  /**
   * @var PasswordExpiryConfiguration[]
   */
  private ?array $entities = null;


  /**
   * PasswordExpiryService constructor.
   */
  public function __construct(public TokenStorageInterface $tokenStorage, public UrlGeneratorInterface $urlGenerator)
  {
  }

  public function isPasswordExpired(): bool
  {
    /** @var HasPasswordPolicyInterface $user */
    if (($user = $this->getCurrentUser()) instanceof HasPasswordPolicyInterface) {
      foreach ($this->entities as $class => $config) {
        $passwordLastChange = $user->getPasswordChangedAt();
        if ($passwordLastChange instanceof DateTime && $user instanceof $class) {
          $expiresAt = (clone $passwordLastChange)->modify('+' . $config->getExpiryDays() . ' days');

          return $expiresAt <= Carbon::now();
        }
      }
    }


    return false;
  }

  /**
   * @return string
   */
  public function getLockedRoutes(?string $entityClass = null): array
  {
    $entityClass = $this->prepareEntityClass(entityClass: $entityClass);

    return isset($this->entities[$entityClass]) ? $this->entities[$entityClass]->getLockRoutes() : [];
  }

  /**
   * The function checks if a given route is locked based on the provided route name and entity class.
   *
   * @param string routeName The name of the route that you want to check if it is locked or not.
   * @param string entityClass The  parameter is an optional parameter that specifies the
   * class of the entity for which the route is being checked. If provided, it will be used to retrieve
   * the locked routes specific to that entity class. If not provided, the method will retrieve the
   * locked routes for all entity classes.
   *
   * @return bool a boolean value. It returns true if the given route name is found in the array of
   * locked routes, and false otherwise.
   */
  public function isLockedRoute(string $routeName, ?string $entityClass = null): bool
  {
    $lockedRoutes = $this->getLockedRoutes(entityClass: $entityClass);
    //
    //
    return in_array(needle: $routeName, haystack: $lockedRoutes);
  }

  public function getResetPasswordRouteName(): string
  {

  }

  public function getExcludedRoutes(?string $entityClass = null): array
  {
    $entityClass = $this->prepareEntityClass(entityClass: $entityClass);

    return isset($this->entities[$entityClass]) ? $this->entities[$entityClass]->getExcludedRoutes() : [];
  }

  public function addEntity(PasswordExpiryConfiguration $passwordExpiryConfiguration): void
  {
    $this->entities[$passwordExpiryConfiguration->getEntityClass()] = $passwordExpiryConfiguration;
  }

  private function getCurrentUser(): ?HasPasswordPolicyInterface
  {
    $token = $this->tokenStorage->getToken();
    if ($token && $user = $token->getUser()) {
      if ($user === 'anon.') {
        return null;
      }

      return $user instanceof HasPasswordPolicyInterface ? $user : null;
    }

    return null;
  }

  /**
   * @param string $entityClass
   * @return string
   */
  private function prepareEntityClass(?string $entityClass): ?string
  {
    if (is_null($entityClass) && $user = $this->getCurrentUser()) {
      return $user::class;
    }

    return $entityClass;
  }
}
