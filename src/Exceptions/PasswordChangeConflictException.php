<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Exceptions;

use Nowo\TransactionDoctrine\WriteUnit\LockConflictExceptionInterface;

/**
 * The stored password of a subject changed after the subject was loaded: another request changed it first (REQ-RUNTIME-003).
 *
 * Thrown inside the host's flush by the default history listener when `password_change_lock` is on, before the host's
 * UPDATE runs, so the whole flush rolls back: neither the new password nor its history row is written. The password
 * policy (reuse, history) was checked against a state that no longer exists; the client must load the subject again
 * and repeat the change, which is then validated against the current history.
 *
 * It is a lock conflict ({@see LockConflictExceptionInterface}): with nowo-tech/transaction-doctrine's Symfony bundle
 * registered, the host's request answers 409 + `Retry-After` + `X-Conflict-Code: CONFLICT_001` (CONFLICT_002, no
 * `Retry-After`, when something else of the request was already committed). Hosts with their own error handling use
 * `ConflictCode::forRequest($request, $exception)`. The message is constant and client-safe (no id, no hash).
 */
final class PasswordChangeConflictException extends RuntimeException implements LockConflictExceptionInterface
{
    public const MESSAGE = 'The password was changed by another request. Load the account again and retry.';

    /**
     * @param class-string $subjectClass Class of the subject whose stored password changed
     */
    public function __construct(public readonly string $subjectClass)
    {
        parent::__construct(self::MESSAGE);
    }
}
