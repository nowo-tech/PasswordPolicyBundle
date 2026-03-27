# Usage

This document describes typical usage of the Password Policy Bundle. For full configuration options, see [CONFIGURATION.md](CONFIGURATION.md). For events and extensibility, see [EVENTS.md](EVENTS.md).

## Table of contents

- [Basic usage](#basic-usage)
- [Password expiry](#password-expiry)
- [Password history](#password-history)
- [Customization](#customization)
- [Translation overrides](#translation-overrides)

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

## Password history

The bundle stores hashed passwords in the history entity when the user changes password. The validator prevents reusing any of the last N passwords (configurable via `passwords_to_remember`).

## Customization

- **Events:** Subscribe to `PasswordExpiredEvent`, `PasswordChangedEvent`, `PasswordReuseAttemptedEvent`, `PasswordHistoryCreatedEvent` for custom logic (see [EVENTS.md](EVENTS.md)).
- **Logging:** Enable and set level via `enable_logging` and `log_level` in configuration.
- **Cache:** Enable optional caching for expiry checks with `enable_cache: true` (requires `symfony/cache`).

## Translation overrides

The bundle translation domain is `PasswordPolicyBundle`. You can override any message from the application layer while keeping bundle translations as fallback.

1. Create or edit an app translation file with the same domain, for example:
   - `translations/PasswordPolicyBundle.en.yaml`
   - `translations/PasswordPolicyBundle.es.yaml`
2. Override only the keys you need. If a key is not defined in your app file, Symfony automatically uses the bundle value from `src/Resources/translations/`.

Example:

```yaml
nowo_password_policy:
  expired:
    title: "Your password has expired."
```
