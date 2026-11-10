<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Traits;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait for password history entities.
 *
 * This trait provides the basic structure for password history entries,
 * including password storage, creation timestamp, and salt management.
 *
 * Mapping: the `@ORM\...` docblocks below are legacy Doctrine annotations. Only the annotation driver of Doctrine
 * ORM 2 reads them (ORM 3 has no annotation driver), and they are kept unchanged so existing ORM 2 annotation
 * mappings do not change. With attributes, XML or ORM 3 the trait maps nothing: map `password`, `salt` (nullable) and
 * `createdAt` in your history entity (or its XML mapping) with your own identifier — do not use the password hash
 * as the identifier. See docs/USAGE.md, "Password history".
 */
trait PasswordHistoryTrait
{
    /**
     * The hashed password.
     *
     * Legacy ORM 2 annotations (ignored by ORM 3 and by the attribute/XML drivers); do not rely on the Id mapping.
     *
     * @ORM\Column(type="string")
     *
     * @ORM\Id()
     */
    private ?string $password = null;

    /**
     * The password salt (optional, for legacy password hashing).
     *
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $salt = null;

    /**
     * The creation date and time of this password history entry.
     *
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
        return $this->password ?? '';
    }

    /**
     * Sets the hashed password.
     *
     * @param string $password The hashed password
     */
    public function setPassword(string $password): self
    {
        // @igor-ignore - Not shared worker service state.
        $this->password = $password;

        return $this;
    }

    /**
     * Gets the creation date and time.
     *
     * @return DateTimeInterface|null The creation date or null if not set
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation date and time.
     *
     * @param DateTimeInterface $createdAt The creation date
     */
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        // @igor-ignore - Not shared worker service state.
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Updates timestamps before persisting the entity.
     *
     * If the creation date is not set, it will be set to the current time.
     *
     * @ORM\PrePersist
     */
    public function updatedTimestamps(): void
    {

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(new DateTimeImmutable());
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
     */
    public function setSalt(?string $salt): void
    {
        // @igor-ignore - Not shared worker service state.
        $this->salt = $salt;
    }
}
