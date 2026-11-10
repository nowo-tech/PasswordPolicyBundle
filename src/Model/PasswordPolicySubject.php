<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Model;

use DateTimeImmutable;
use DateTimeInterface;
use Error;
use Nowo\PasswordPolicyBundle\Util\SubjectId;

/**
 * Immutable {@see PasswordPolicySubjectInterface} value: build it in your adapter from your domain user.
 */
final readonly class PasswordPolicySubject implements PasswordPolicySubjectInterface
{
    private ?DateTimeImmutable $passwordChangedAt;

    public function __construct(
        private string $subjectClass,
        private string $subjectId,
        private ?string $passwordHash = null,
        ?DateTimeInterface $passwordChangedAt = null,
    ) {
        $this->passwordChangedAt = $passwordChangedAt instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($passwordChangedAt)
            : null;
    }

    /**
     * Read-only snapshot of a {@see HasPasswordPolicyInterface} entity.
     */
    public static function fromEntity(HasPasswordPolicyInterface $entity): self
    {
        try {
            $hash = $entity->getPassword();
        } catch (Error) {
            // Typed property not initialised yet (entity being registered).
            $hash = null;
        }

        return new self(SubjectId::classOf($entity), SubjectId::of($entity->getId()), $hash, $entity->getPasswordChangedAt());
    }

    public function getPasswordPolicySubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getPasswordPolicySubjectId(): string
    {
        return $this->subjectId;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getPasswordChangedAt(): ?DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }
}
