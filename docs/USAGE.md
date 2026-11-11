# Usage

This document describes typical usage of the Password Policy Bundle. For full configuration options, see [CONFIGURATION.md](CONFIGURATION.md). For events and extensibility, see [EVENTS.md](EVENTS.md).

## Table of contents

- [Basic usage](#basic-usage)
- [Password expiry](#password-expiry)
- [Password history](#password-history)
- [Customization](#customization)
- [Translation overrides](#translation-overrides)
- [Extension points (non-final classes)](#extension-points-non-final-classes)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
- [GDPR erasure and export](#gdpr-erasure-and-export)
- [Forms in Twig (REQ-TWIG-005)](#forms-in-twig-req-twig-005)

## Basic usage

1. **Implement interfaces** in your user entity and password history entity (see [CONFIGURATION.md](CONFIGURATION.md)).
2. **Add the validator** to your plain password field:

```php
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;

class User implements HasPasswordPolicyInterface
{
    /**
     * @PasswordPolicy()
     */
    private ?string $plainPassword = null;
}
```

3. **Configure the bundle** in `config/packages/nowo_password_policy.yaml` (per-entity policies, expiry, history count, routes, etc.).

## Password expiry

When `expiry_days` is set for an entity, the bundle checks password age on each request (according to `notified_routes` and `excluded_notified_routes`). If the password has expired, the user can be redirected to the reset route or shown a flash message (see `expiry_listener` in configuration).

For performance and predictable behaviour, prefer **literal** route names in `notified_routes`, keep that list **minimal**, and exclude login, logout, password reset, and API routes via `excluded_notified_routes`. See [Route configuration recommendations](CONFIGURATION.md#route-configuration-recommendations).

## Password history

The bundle stores hashed passwords in the history entity when the user changes password. The validator prevents reusing any of the last N passwords (configurable via `passwords_to_remember`). To keep the history elsewhere or record it from your own use case, see [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application); for the history entity mapping see [Mapping the history entity](#mapping-the-history-entity).

## Customization

- **Events:** Subscribe to `PasswordExpiredEvent`, `PasswordChangedEvent`, `PasswordReuseAttemptedEvent`, `PasswordHistoryCreatedEvent` for custom logic (see [EVENTS.md](EVENTS.md)).
- **Logging:** Enable and set level via `enable_logging` and `log_level` in configuration.
- **Cache:** Enable optional caching for expiry checks with `enable_cache: true` (requires `symfony/cache`).

## Translation overrides

The bundle translation domain is `NowoPasswordPolicyBundle`. You can override any message from the application layer while keeping bundle translations as fallback.

1. Create or edit an app translation file with the same domain, for example:
   - `translations/NowoPasswordPolicyBundle.en.yaml`
   - `translations/NowoPasswordPolicyBundle.es.yaml`
2. Override only the keys you need. If a key is not defined in your app file, Symfony automatically uses the bundle value from `src/Resources/translations/`.

Example:

```yaml
nowo_password_policy:
  expired:
    title: "Your password has expired."
```

## Extension points (non-final classes)

Most services are `final`. The following classes are intentionally **not** final (REQ-PHP-001) because hosts extend them or their subclasses are part of the 1.x API; prefer the interfaces when you only need a different behaviour:

| Class | Why it is open | Prefer |
| --- | --- | --- |
| `Service\PasswordExpiryService`, `Service\PasswordHistoryService` | Subclasses may override single methods (custom expiry rules, history pruning) | `PasswordExpiryServiceInterface`, `PasswordHistoryServiceInterface` + service decoration |
| `Validator\PasswordPolicyValidator` | Custom constraint validators may extend it to add rules | Decorate the validator service |
| `EventListener\PasswordEntityListener`, `EventListener\PasswordExpiryListener` | Registered by the extension with explicit arguments; subclasses can replace the service class | Listen to the bundle events instead |
| `Event\*` | Event subclasses carry extra host data | — |
| `Exceptions\*` | Hosts may catch and extend the bundle exceptions | — |

They may become `final` in a future major version; UPGRADING.md will announce it.

## Using it from a DDD / hexagonal application

The bundle is an **infrastructure** component. By default it works on your user entity: the entity implements
`HasPasswordPolicyInterface`, a password-history entity is mapped with Doctrine ORM, and a Doctrine `onFlush` listener
records the history. Since 1.10.0 a hexagonal host can keep its domain free of those types through ports, each one an
interface with a default adapter that reproduces the behaviour above (alias the interface to replace it):

| Port | Default adapter | What it is for |
| --- | --- | --- |
| `History\PasswordHistoryRepositoryInterface` (`findRecent()`, `add()`, `prune()`, scalar subject class + id) | `History\DoctrinePasswordHistoryRepository` (the entity's history association; persists, never flushes) | Where the history lives: your aggregate, a DBAL table, an API… |
| `History\PasswordHistoryRecorderInterface` (`record($subjectClass, $subjectId, $previousHash, ?$changedAt)`) | `History\PasswordHistoryRecorder` | Records the previous hash from your use case, prunes to `passwords_to_remember`, dispatches `PasswordChangeRecordedEvent` |
| `Subject\PasswordPolicySubjectResolverInterface` (`resolve(object $validatedObject)`) | `Subject\DefaultPasswordPolicySubjectResolver` | Finds the subject of `#[PasswordPolicy]` on a command/DTO |
| `Subject\PasswordReuseCheckerInterface` (`findReuse($plain, $subject, …): ?PasswordReuseMatch`) | `Subject\PasswordReuseChecker` | History / current / extension check of a read-only subject; callable from your use case |
| `Subject\PasswordHashVerifierInterface` (`verify($subjectClass, $hash, $plain)`) | `Subject\DefaultPasswordHashVerifier` (`password_verify()`, then the Symfony hasher of the class) | Hash verification without touching an entity |

Read-only model: `Model\PasswordPolicySubjectInterface` (`getPasswordPolicySubjectClass()`,
`getPasswordPolicySubjectId()`, `getPasswordHash(): ?string`, `getPasswordChangedAt(): ?DateTimeInterface` — no
Doctrine `Collection`, no setters) with the immutable `Model\PasswordPolicySubject` (`PasswordPolicySubject::fromEntity()`
snapshots a `HasPasswordPolicyInterface` entity), `Model\PasswordHistoryEntry` and `Model\PasswordReuseMatch`.
`HasPasswordPolicyInterface` stays as the Doctrine-adapter variant.

### History without the Doctrine listener

Configure the subject class (it must exist, it does not have to implement `HasPasswordPolicyInterface`) with
`history_listener: false`, alias the repository port to your adapter and call the recorder from your use case:

```yaml
nowo_password_policy:
  entities:
    App\Domain\User\User:
      history_listener: false
      passwords_to_remember: 5
      reset_password_route_name: app_reset_password

services:
  Nowo\PasswordPolicyBundle\History\PasswordHistoryRepositoryInterface: '@App\Infrastructure\Security\DbalPasswordHistoryRepository'
```

```php
// App\Application\User\ChangePasswordHandler — $command->userId is set server-side from the authenticated user
public function __invoke(ChangePassword $command): void
{
    $user         = $this->users->get(new UserId($command->userId));
    $previousHash = $user->passwordHash();
    $user->changePassword($this->hasher->hash($command->newPassword), $this->clock->now());
    $this->users->save($user);

    $this->passwordHistory->record(User::class, $command->userId, $previousHash, $this->clock->now());
}
```

With `history_listener: false` the bundle no longer stamps `passwordChangedAt` for that class: your aggregate owns it.
Keep the listener (the default) and you must not call the recorder for the same change, or the history gets the
previous hash twice.

### `#[PasswordPolicy]` on a command or DTO

When the validated object is not a `HasPasswordPolicyInterface` entity, the validator asks the subject resolver. The
default resolver accepts an object that is a `PasswordPolicySubjectInterface`, or one that implements
`PasswordPolicySubjectAwareInterface` (`getPasswordPolicySubject()`), which may return the entity (checked exactly like
the entity itself) or a read-only subject (checked through the history port):

```php
final class ChangePassword implements PasswordPolicySubjectAwareInterface
{
    public function __construct(
        public readonly string $userId,
        #[PasswordPolicy(detectExtensions: true)]
        public readonly string $newPassword,
        private readonly PasswordPolicySubjectInterface $subject,
    ) {
    }

    public function getPasswordPolicySubject(): PasswordPolicySubjectInterface
    {
        return $this->subject;
    }
}

// In the controller: only the new password comes from the request; the subject comes from the logged-in user.
$account = $this->security->getUser();                          // your SecurityUser adapter
$user    = $this->users->get(new UserId($account->getUserIdentifier()));
$command = new ChangePassword(
    $user->id()->toString(),
    $form->get('newPassword')->getData(),
    new PasswordPolicySubject(User::class, $user->id()->toString(), $user->passwordHash(), $user->passwordChangedAt()),
);
```

> **Warning:** the default resolver trusts the subject the object exposes. Build it from the authenticated user (the
> security token) or a server-side lookup, never from request data, and do not let the serializer or a form populate a
> `PasswordPolicySubjectInterface` or the user id: a client could otherwise pick another or a fresh subject (no history)
> and bypass the reuse check.

Returning `null` is an error: the validator throws `ValidationException` (fail closed). Or alias
`PasswordPolicySubjectResolverInterface` to a resolver that looks the subject up from the authenticated user.
Violations, codes (`PASSWORD_IN_HISTORY`, `PASSWORD_EXTENSION`) and messages are the same as on the entity; the event is
`PasswordReuseDetectedEvent` (scalar) instead of `PasswordReuseAttemptedEvent`. An object the resolver does not
support still throws `ValidationException`.

The subject class must match a `nowo_password_policy.entities` key (exactly, without a Doctrine proxy prefix, or as a
subclass / implementation of the key); otherwise the checker and the recorder throw `ConfigurationException` instead of
silently applying default settings.

Hashes are verified with `password_verify()` (bcrypt and argon2, including Symfony's pre-hashing of bcrypt passwords
over 72 bytes or with a NUL byte). For other formats (legacy `migrate_from` hashes, MessageDigest, Pbkdf2, custom
hashers) the verifier asks the Symfony hasher configured **for the subject class**: add a `security.password_hashers`
entry for your domain class (it is often not the security user class), or alias `PasswordHashVerifierInterface` —
otherwise those hashes never match and their reuse is accepted.

### Events

Translate the bundle events into your own events in the infrastructure layer; do not pass them into the domain.
`PasswordChangeRecordedEvent` (dispatched after the Doctrine flush committed, or by the recorder) and
`PasswordReuseDetectedEvent` carry scalar ids only. The entity events (`PasswordChangedEvent`,
`PasswordHistoryCreatedEvent`, `PasswordReuseAttemptedEvent`, `PasswordExpiredEvent`) now also expose
`getSubjectClass()` and `getSubjectId()`. See [EVENTS.md](EVENTS.md).

### Mapping the history entity

`PasswordHistoryTrait` carries legacy Doctrine *annotations* that only the ORM 2 annotation driver reads (they are
kept so ORM 2 annotation mappings do not change). With attributes, XML or ORM 3 the trait maps nothing: map
`password`, `salt` (nullable) and `createdAt` yourself — for attributes, redeclare the properties in your entity
(`#[ORM\Column] private ?string $password = null;`, PHP accepts a compatible redeclaration of a trait property) — and
use your own id column, not the password hash.

Known limitations (planned improvements):

- **Expiry** still reads the security token user, which must implement `HasPasswordPolicyInterface` (no expiry subject
  resolver yet). If your security user is a separate adapter class, implement the interface there (delegating to the
  entity) or call `PasswordExpiryServiceInterface` yourself.
- The `PasswordPolicyServiceInterface` / `CurrentPasswordMatcherInterface` / `PasswordHistoryServiceInterface` /
  `PasswordExpiryServiceInterface` signatures still take the entity; for formats `password_verify()` does not
  recognise, the **entity** check still clones the entity or temporarily calls `setPassword()` on it (the read-only
  subject path uses `PasswordHashVerifierInterface` instead).
- Configuration is keyed by class name (`nowo_password_policy.entities.<Class>`), and the history recorded by the
  default listener needs the bidirectional `OneToMany(mappedBy: …)` association, a no-argument history constructor and a
  `set<MappedBy>()` setter.

## GDPR erasure and export

The bundle keeps personal data about a user in two places: the **password history** rows (hashes and change dates)
and, with `expiry_listener.flash_throttle_storage: cache` (or a custom storage implementing
`ForgettableExpiryFlashThrottleStorageInterface`), one **expiry flash throttle** entry per user in the shared cache.
Two public ports (namespace `Nowo\PasswordPolicyBundle\PersonalData`) erase and export them for one person, so the
host does not query the history table itself. They are plain PHP: no Doctrine, Symfony or HTTP types in their
signatures.

```php
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataEraserInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataExporterInterface;
use Nowo\PasswordPolicyBundle\PersonalData\PasswordPolicyPersonalDataSubject;

$subject = PasswordPolicyPersonalDataSubject::fromEntity($user);               // HasPasswordPolicyInterface entity
// or ::fromSubjectId(User::class, '42'), or ::fromPolicySubject($readOnlySubject)

$data   = $exporter->export($subject); // ['count' => 2, 'entries' => [['changed_at' => '2026-03-01T10:00:00+00:00'], ...]]
$result = $eraser->erase($subject);    // PasswordPolicyErasureResult
$result->toArray();                    // ['password_history' => 2, 'expiry_flash_throttle' => 1]
$result->total();                      // 3
```

**Subject.** `PasswordPolicyPersonalDataSubject` holds the subject class and the subject id exactly as the history
port keys them. Values are kept byte for byte (no trimming, no case folding); empty values, NUL bytes, a class longer
than 255 bytes and an id longer than 128 bytes are rejected with `InvalidArgumentException`.

**Erasure** (`PasswordPolicyPersonalDataEraserInterface::erase()`):

- Deletes every password history row of the subject through the history port's
  `ErasablePasswordHistoryRepositoryInterface::removeAll()`. The default Doctrine adapter deletes the rows of the
  subject's `OneToMany` history association with DQL, by primary key in batches of 500, after re-checking the owner and
  the subject id byte for byte (`usera` never erases `UserA`, `jose` never erases `josé`, even under a case- or
  accent-insensitive MySQL collation). It never flushes your entity manager and removes the deleted entries from a
  loaded collection, so a later flush of yours does not touch them. A custom history adapter that does not implement
  `ErasablePasswordHistoryRepositoryInterface` cannot be erased: the port erases nothing from it and logs an `error`,
  and the UserKit eraser is **not registered** (a compiler pass removes it and writes the reason to the container
  compilation log), so UserKit never reports `password_history` as erased when it was not. Implement `removeAll()` on
  your adapter.
- Forgets the subject's expiry flash throttle entry when the throttle storage is the cache one. The session storage
  is not touched: its entries end with the user's sessions.
- Idempotent: a second call returns zero counts. Unknown subjects return zero counts.
- Does not touch the user entity itself (password, `passwordChangedAt`): erasing the account is the host's job.

**Transactions and the event.** When no transaction is open on the subject's Doctrine connection, `erase()` opens one
and dispatches `PasswordPolicyPersonalDataErasedEvent` (subject class, subject id, counts; scalars only) **after its
commit**. Inside your own transaction it joins it and does **not** dispatch the event (your transaction may still roll
back): the returned result is the outcome. Without Doctrine (your own history adapter) the port cannot tell whether
you have a transaction open, so it does not dispatch the event either. The cache throttle entry is forgotten
immediately, whatever your transaction does.

**Export** (`PasswordPolicyPersonalDataExporterInterface::export()`): `{count, entries}` with one `changed_at` (ATOM
date string, `null` when the entry has no date) per history entry, newest first. Password hashes are **never**
exported.

### UserKit bridge

With [nowo-tech/user-kit-bundle](https://github.com/nowo-tech/UserKitBundle) installed (and at least one entity under
`nowo_password_policy.entities`), the bundle registers, with no configuration:

| UserKit service | Key | Tag priority | What it does |
|---|---|---|---|
| `PasswordHistoryUserKitEraser` | `password_history` | 100 | Runs the eraser port once per distinct subject of the user, inside UserKit's transaction, **before** the host identity eraser (it needs the user row to find the history) |
| `PasswordHistoryUserKitExportProvider` | `password_history` | 60 | Export section `{count, entries: [{changed_at}]}` over every subject of the user |

The subjects of a user come from `PasswordPolicyPersonalDataSubjectResolverInterface`. The default asks the bundle's
subject resolver (`HasPasswordPolicyInterface` entity, `PasswordPolicySubjectInterface` or
`PasswordPolicySubjectAwareInterface`). When the UserKit user is not the password policy subject, set
`nowo_password_policy.personal_data.subject_resolver` to your own service. Set `personal_data.enabled: false` when your
application already erases and exports the password history.

Because the eraser runs inside UserKit's transaction, `PasswordPolicyPersonalDataErasedEvent` is not dispatched there;
listen to UserKit's own events instead.

When the password history lives on a **different connection** than the UserKit user's entity manager, the port does
not join UserKit's transaction: it commits its own, so if a later eraser fails and UserKit rolls back, the password
history stays erased while the rest of the account is restored (a partial erasure; running the erasure again is safe,
the port is idempotent).

UserKit refuses two export sections with the same key (`LogicException`): if your application already registers a
`password_history` export provider, remove it or set `personal_data.enabled: false`.

## Forms in Twig (REQ-TWIG-005)

Every UI form — bundle views and the demo apps — is a PHP Symfony Form Type (FormKit types when FormKit is in the stack) rendered with `form_start`, the REQ-TWIG-003 child loop and `form_end`; submit / cancel buttons may stay outside the loop:

```twig
{{ form_start(form) }}
    {% for child in form %}
        {% if not child.rendered %}
            {{ form_row(child) }}
        {% endif %}
    {% endfor %}
    <button type="submit">Save</button>
{{ form_end(form) }}
```

CSRF-only actions (delete, logout-style buttons, toggles) use an empty Form Type too, and login forms map `_username` / `_password` / `_csrf_token` (token id `authenticate`) on a Form Type. Host overrides and demos must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>` markup: `make check-no-raw-html-form` (part of `make release-check`) fails when it appears.
