<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Traits;


use DateTimeInterface;
use Carbon\Carbon;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait for password history entities.
 *
 * This trait provides the basic structure for password history entries,
 * including password storage, creation timestamp, and salt management.
 */
trait PasswordHistoryTrait
{

    /**
     * The hashed password.
     *
     * @var string|null
     * @ORM\Column(type="string")
     * @ORM\Id()
     */
    private ?string $password = null;

    /**
     * The password salt (optional, for legacy password hashing).
     *
     * @var string|null
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $salt = null;

    /**
     * The creation date and time of this password history entry.
     *
     * @var DateTimeInterface|null
     * @ORM\Column(type="datetime")
     */
    private ?DateTimeInterface $createdAt = null;

    /**
     * Gets the hashed password.
     *
     * @return string The hashed password
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Sets the hashed password.
     *
     * @param string $password The hashed password
     * @return self
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Gets the creation date and time.
     *
     * @return DateTime|null The creation date or null if not set
     */
    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation date and time.
     *
     * @param DateTimeInterface $createdAt The creation date
     * @return self
     */
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Updates timestamps before persisting the entity.
     *
     * If the creation date is not set, it will be set to the current time.
     *
     * @ORM\PrePersist
     * @return void
     */
    public function updatedTimestamps(): void
    {

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(Carbon::now());
        }
    }

    /**
     * Gets the password salt.
     *
     * @return string|null The password salt or null if not set
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * Sets the password salt.
     *
     * @param string|null $salt The password salt
     * @return void
     */
    public function setSalt(?string $salt): void
    {
        $this->salt = $salt;
    }

}
