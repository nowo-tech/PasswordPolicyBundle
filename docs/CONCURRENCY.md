# Concurrency (REQ-RUNTIME-003)

How the bundle's writes behave when the same data is written at the same time (two requests, two workers, a retried
request). Worker mode with the kernel not reset is covered in [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [Summary](#summary)
- [Write paths](#write-paths)
- [The password-change lock](#the-password-change-lock)
- [Conflict codes](#conflict-codes)
- [Host options](#host-options)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Summary

- The bundle has **no write route, controller or Messenger handler of its own**. It writes inside the host's own unit
  of work. The password history rows join the host's flush (Doctrine `onFlush`), so they commit or roll back with the
  password change itself (rule 1). The bundle never flushes, clears or resets the host's EntityManager (rule 2).
- **Read-modify-write of the password** (rule 4): since 1.14.0 the history listener locks the subject's row inside the
  host's flush and checks that the stored password is still the one the request loaded (`password_change_lock`, on by
  default). Two concurrent changes of one user no longer overwrite each other. The later one fails with
  `PasswordChangeConflictException`, a lock conflict, and writes nothing.
- **Conflict answer** (rule 6): the bundle answers no HTTP request itself, so it never answers for a host route. Its
  conflict exception implements transaction-doctrine's `LockConflictExceptionInterface`. With
  `NowoTransactionDoctrineBundle` registered, the host's request answers **409 + `Retry-After` + `CONFLICT_001`**, or
  `CONFLICT_002` without `Retry-After` when the request had already committed something.
- Unique-constraint violations and lost connections are not conflicts. The bundle does not catch them (the host's
  flush fails with the DBAL exception).

## Write paths

| Path | Transaction | Concurrency control | Conflict answer |
| --- | --- | --- | --- |
| Password change of a configured entity (`PasswordEntityListener`: history row INSERT, stale rows DELETE, `passwordChangedAt`) | The host's flush transaction (ORM `UnitOfWork::commit`): history rows and the host's UPDATE commit together | `password_change_lock` (default): row lock in `preUpdate` (`FOR NO KEY UPDATE` on PostgreSQL, `FOR UPDATE` on MySQL / MariaDB) plus a stored-vs-loaded password comparison | `PasswordChangeConflictException` (lock conflict). With transaction-doctrine's bundle: 409 + `CONFLICT_001` (or `CONFLICT_002`). Deadlocks and lock-wait timeouts stay DBAL exceptions, which that bundle maps to the same answer |
| Insert of a new subject (`passwordChangedAt` stamped in `onFlush`) | The host's flush | None needed: a new row has no history. A duplicate id is the host's unique violation | None (a unique violation is a real error) |
| `PasswordHistoryRecorder` / `DoctrinePasswordHistoryRepository::add()` / `prune()` (`history_listener: false`) | The caller's transaction: the adapter persists and removes, it never flushes | **The host's.** Lock the subject before validating (see [Residual risks](#residual-risks)) | The host's |
| GDPR erasure (`PasswordPolicyPersonalDataEraser`, UserKit eraser) | Its own `Connection::transactional()` when no transaction is open, else the caller's (it joins it and never commits) | DQL `DELETE` by primary key and exact owner, in batches of 500. Idempotent: a concurrent second erasure deletes 0 rows | Deadlock or lock-wait timeout: the DBAL exception is rethrown after the rollback (rule 8), so nothing is committed |
| Expiry flash throttle (cache pool or session) | No database | Last writer wins; the worst case is one extra flash | None |
| Validator, expiry checker, exporters | Reads only | n/a | n/a |

## The password-change lock

Two requests that change one user's password both validate the new password against the history they loaded. Before
1.14.0, the later commit silently overwrote the earlier one. The earlier new password never reached the history, so it
could be set again at once. Both requests also recorded the same old hash, which pushed an older password out of the
`passwords_to_remember` window one change early.

With `password_change_lock: true`:

1. In `onFlush`, the listener records the loaded (old) password of each subject whose password changed. This is the
   change set's original value.
2. In `preUpdate`, right before the host's UPDATE and inside the flush transaction, the `StoredPasswordLock` service
   runs `SELECT <password> FROM <table> WHERE <id> = ? FOR UPDATE` on the host's own connection. The bundle opens,
   wraps and commits nothing, and it never wraps another connection.
3. If the stored value differs, or the row is gone, it throws `PasswordChangeConflictException`. The ORM rolls the
   whole flush back: no new password and no history row. As after any failed flush, the ORM closes that
   EntityManager. The host replaces it (transaction-doctrine's write units and Symfony's `doctrine` registry do).
4. Otherwise the lock is held until the host's commit. An overlapping change of the same user waits for it, then
   sees the new password and fails as in step 3.

The client loads the account again and repeats the change. The new password is then validated against the current
history. The bundle never retries by itself, because the policy check must run again on the fresh state.

PostgreSQL uses `FOR NO KEY UPDATE`. That lock does not conflict with the key-share lock the history row's foreign key
takes, so the lock adds no deadlock. On MySQL / MariaDB, the history INSERT takes a shared lock on the user row (the
foreign key check). Two overlapping flushes can then end in a deadlock (1213) instead of the conflict exception. This
already happened before 1.14.0 with the host's own UPDATE. The outcome is the same: one flush fails with a transient,
retry-safe error, and nothing of it is written.

## Conflict codes

| Code | When | Answer (with `NowoTransactionDoctrineBundle`) |
| --- | --- | --- |
| `CONFLICT_001` | `PasswordChangeConflictException`, deadlock, lock-wait timeout or serialization failure, and nothing of the request committed | 409 (or the configured 503) + `Retry-After` + `X-Conflict-Code: CONFLICT_001` |
| `CONFLICT_002` | The same, but the request had already committed something (request commit watermark, transaction-doctrine ≥ 1.6) | 409 without `Retry-After` + `X-Conflict-Code: CONFLICT_002`: read again, do not repeat blindly |

Hosts with their own error handling compute the code with
`Nowo\TransactionDoctrine\ConflictCode::forRequest($request, $exception)`. They take the headers from
`ConflictResponsePolicy::headersFor()`. The exception message (`PasswordChangeConflictException::MESSAGE`) is constant
and client-safe: no id, no hash. `$subjectClass` names the subject class for logs.

## Host options

| Option | Default | Effect |
| --- | --- | --- |
| `nowo_password_policy.entities.<class>.password_change_lock` | `true` | The row lock and comparison above. `false` restores the behaviour before 1.14.0 (no lock, a lost update is possible) |
| `nowo_password_policy.entities.<class>.history_listener` | `true` | With `false` the listener (and its lock) is not registered for the class. The host records the history and owns the concurrency |
| transaction-doctrine `conflict_response.status` | `409` | `503` for hosts that want it (that bundle's option, not this one's) |
| transaction-doctrine `commit_detection` | on | The request watermark that turns `CONFLICT_001` into `CONFLICT_002` |
| Database lock timeouts (`innodb_lock_wait_timeout`, `lock_timeout`) | server defaults | How long an overlapping change waits for the row lock. Keep them short on web requests |

## Tests and CI

- `tests/Integration/Doctrine/PasswordChangeRaceDoctrineTest` uses two EntityManagers on two real sessions with the
  XML-mapped (DDD) host. Every session sets a 1 s lock timeout. The test covers:
  - a later concurrent change, which fails with the conflict and writes nothing (`CONFLICT_001`), after which the retry
    records both old hashes;
  - the same race without the lock, which shows the lost history row;
  - an overlapping flush, which waits for the row lock and times out (a transient lock-wait timeout: 1205 / 55P03),
    after which the session is usable;
  - sequential changes in one manager, which are not conflicts;
  - a stored password changed behind the manager.
- `tests/Unit/Concurrency/StoredPasswordLockTest` checks the lock clause per platform.
- CI job "Doctrine integration - mysql / mariadb / postgres" runs MySQL 8.4, MariaDB 11.4 and PostgreSQL 16. These
  jobs are required checks on `main`. "Dependency floor (nowo-tech/transaction-* lowest, demo lock)" runs the suite
  and PHPStan on the lowest allowed transaction-doctrine and checks the demo lock.

## Residual risks

- **`history_listener: false`.** The bundle's recorder only persists. A host that records the history itself must lock
  the subject before it validates the new password. For example, use `$entityManager->lock($user,
  LockMode::PESSIMISTIC_WRITE)` inside its transaction, or a `version` column. Otherwise two concurrent changes can
  still lose a history entry.
- **Writes outside the ORM.** A password changed with DQL `UPDATE` or plain DBAL bypasses the listener: no history
  and no lock. A managed entity changed after such a write fails with the conflict, as intended.
- **SQLite** has no row lock. The comparison still runs, and SQLite's database write lock serialises writers.
- **Other platforms** (SQL Server, Oracle) use DBAL's `FOR UPDATE` translation. They are not covered by CI.
- **Stale history pruning.** `passwords_to_remember` is applied per flush. With the lock, the flushes of one user are
  serialised, so two changes never prune the same rows at once.
