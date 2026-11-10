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
// App\Application\User\ChangePasswordHandler
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
        private readonly ?PasswordPolicySubjectInterface $subject = null, // filled by the controller / adapter
    ) {
    }

    public function getPasswordPolicySubject(): ?PasswordPolicySubjectInterface
    {
        return $this->subject; // e.g. new PasswordPolicySubject(User::class, $userId, $currentHash, $changedAt)
    }
}
```

Or alias `PasswordPolicySubjectResolverInterface` to a resolver that looks the subject up from the command's id.
Violations, codes (`PASSWORD_IN_HISTORY`, `PASSWORD_EXTENSION`) and messages are the same as on the entity; the event is
`PasswordReuseDetectedEvent` (scalar) instead of `PasswordReuseAttemptedEvent`. An object the resolver does not
support still throws `ValidationException`.

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
