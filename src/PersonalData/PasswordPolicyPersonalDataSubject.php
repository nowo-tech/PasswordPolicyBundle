<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use InvalidArgumentException;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;
use Nowo\PasswordPolicyBundle\Util\SubjectId;

use function sprintf;
use function str_contains;
use function strlen;

/**
 * Whose password policy data is erased or exported: the subject class and the subject id exactly as the history
 * store keeps them (the same pair as {@see PasswordPolicySubjectInterface} and the history port).
 *
 * Values are kept byte for byte: no trimming, no case folding. Empty values, NUL bytes, a class longer than 255
 * bytes and an id longer than 128 bytes are rejected.
 */
final readonly class PasswordPolicyPersonalDataSubject
{
    public const MAX_CLASS_LENGTH = 255;

    public const MAX_ID_LENGTH = 128;

    private function __construct(
        public string $subjectClass,
        public string $subjectId,
    ) {
    }

    /**
     * The subject class (a key of nowo_password_policy.entities, or the entity class) and its id as a string.
     *
     * @throws InvalidArgumentException when a value is empty, contains a NUL byte or is too long
     */
    public static function fromSubjectId(string $subjectClass, string $subjectId): self
    {
        self::assertValid('subject class', $subjectClass, self::MAX_CLASS_LENGTH);
        self::assertValid('subject id', $subjectId, self::MAX_ID_LENGTH);

        return new self($subjectClass, $subjectId);
    }

    /**
     * The subject of a {@see HasPasswordPolicyInterface} entity (its class without Doctrine proxy prefix and its id).
     *
     * @throws InvalidArgumentException when the entity has no id yet
     */
    public static function fromEntity(HasPasswordPolicyInterface $entity): self
    {
        return self::fromSubjectId(SubjectId::classOf($entity), SubjectId::of($entity->getId()));
    }

    /**
     * The subject of a read-only {@see PasswordPolicySubjectInterface}.
     *
     * @throws InvalidArgumentException when the subject has no class or id
     */
    public static function fromPolicySubject(PasswordPolicySubjectInterface $subject): self
    {
        return self::fromSubjectId($subject->getPasswordPolicySubjectClass(), $subject->getPasswordPolicySubjectId());
    }

    /**
     * Whether both values are byte-for-byte equal.
     */
    public function equals(self $other): bool
    {
        return $this->subjectClass === $other->subjectClass && $this->subjectId === $other->subjectId;
    }

    private static function assertValid(string $label, string $value, int $maxLength): void
    {
        if ($value === '') {
            throw new InvalidArgumentException(sprintf('The %s must not be empty.', $label));
        }

        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException(sprintf('The %s must not contain a NUL byte.', $label));
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('The %s must not be longer than %d bytes.', $label, $maxLength));
        }
    }
}
