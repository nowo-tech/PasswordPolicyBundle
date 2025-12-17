<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;


use Nowo\PasswordPolicyBundle\Exceptions\RuntimeException;

/**
 * Configuration class for password expiry settings per entity.
 *
 * This class holds the configuration for password expiry, including the number
 * of days before expiry, locked routes, and excluded routes.
 */
class PasswordExpiryConfiguration
{

  /**
   * The fully qualified class name of the entity.
   *
   * @var string
   */
  private readonly string $entityClass;

  /**
   * PasswordExpiryConfiguration constructor.
   *
   * @param string $class The fully qualified class name of the entity
   * @param int $expiryDays The number of days before a password expires
   * @param array $lockRoutes Array of route names that are locked when password expires
   * @param array $excludedRoutes Array of route names excluded from expiry checks
   * @param string $resetPasswordRouteName The route name for password reset
   * @throws RuntimeException If the entity class does not implement HasPasswordPolicyInterface
   */
  public function __construct(
    string $class,
    private readonly int $expiryDays,
    private readonly array $lockRoutes = [],
    private readonly array $excludedRoutes = [],
    private readonly string $resetPasswordRouteName = ''
  ) {
    if (!is_a($class, HasPasswordPolicyInterface::class, true)) {
      throw new RuntimeException(sprintf(
        'Entity %s must implement %s interface',
        $class,
        HasPasswordPolicyInterface::class
      ));
    }

    $this->entityClass = $class;
  }

  /**
   * Gets the entity class name.
   *
   * @return string The fully qualified class name of the entity
   */
  public function getEntityClass(): string
  {
    return $this->entityClass;
  }

  /**
   * Gets the number of days before a password expires.
   *
   * @return int The number of expiry days
   */
  public function getExpiryDays(): int
  {
    return $this->expiryDays;
  }

  /**
   * Gets the list of locked routes.
   *
   * @return array Array of route names that are locked when password expires
   */
  public function getLockRoutes(): array
  {
    return $this->lockRoutes;
  }

  /**
   * Gets the list of excluded routes.
   *
   * @return array Array of route names excluded from expiry checks
   */
  public function getExcludedRoutes(): array
  {
    return $this->excludedRoutes;
  }

  /**
   * Gets the reset password route name.
   *
   * @return string The route name for password reset
   */
  public function getResetPasswordRouteName(): string
  {
    return $this->resetPasswordRouteName;
  }

}
