<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Subject;

use Nowo\PasswordPolicyBundle\Model\PasswordPolicySubjectInterface;

/**
 * SPI (since 1.12.0): the read-only subject of the current request, for the expiry check when the security user is not a
 * `HasPasswordPolicyInterface` entity (a DDD host's security adapter).
 *
 * The default, {@see NullCurrentPasswordPolicySubjectProvider}, returns null: only entity users are checked, as before
 * 1.12.0. Alias this interface to {@see TokenPasswordPolicySubjectProvider} (the token user is, or exposes, the subject)
 * or to your own provider (for example a lookup by the user identifier). Build the subject server side, never from
 * request data.
 */
interface CurrentPasswordPolicySubjectProviderInterface
{
    public function getCurrentSubject(): ?PasswordPolicySubjectInterface;
}
