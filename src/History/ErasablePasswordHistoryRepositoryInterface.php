<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\History;

use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;

/**
 * A history adapter that can delete every entry of a subject, used by
 * {@see PasswordPolicyPersonalDataEraserInterface}. The default Doctrine adapter implements it; implement it on your
 * own adapter so the eraser deletes your history rows too.
 */
interface ErasablePasswordHistoryRepositoryInterface extends PasswordHistoryRepositoryInterface
{
    /**
     * Deletes every history entry of the subject (byte-exact subject id) without flushing, and returns how many rows
     * were deleted. Zero when the subject or its history does not exist.
     */
    public function removeAll(string $subjectClass, string $subjectId): int;
}
