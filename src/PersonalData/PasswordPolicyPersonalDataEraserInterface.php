<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\PersonalData;

use Nowo\PasswordPolicyBundle\Event\PasswordPolicyPersonalDataErasedEvent;

/**
 * Port: erases the password policy data the bundle manages for one subject (GDPR art. 17).
 *
 * Deletes the subject's password history rows (through the history port, when the adapter supports it) and the
 * expiry flash throttle entry of a shared storage. Idempotent: a second call returns zero counts.
 *
 * Transactions: when no transaction is open on the subject's connection the call opens one and dispatches
 * {@see PasswordPolicyPersonalDataErasedEvent} after its commit. Inside the caller's transaction it joins it and does
 * NOT dispatch the event (the returned result is the outcome). It never flushes the caller's unit of work.
 */
interface PasswordPolicyPersonalDataEraserInterface
{
    public function erase(PasswordPolicyPersonalDataSubject $subject): PasswordPolicyErasureResult;
}
