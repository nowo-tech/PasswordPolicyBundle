# Installation

This guide covers installing Password Policy Bundle in a Symfony application.

## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Manual registration](#manual-registration)
- [Next steps](#next-steps)

## Requirements

- **PHP** >= 8.1, < 8.6
- **Symfony** ^6.0 || ^7.0 || ^8.0
- **Doctrine ORM**
- **nesbot/carbon** ^3.9

Optional: **symfony/cache** (only if `enable_cache: true` is used in configuration).

## Install with Composer

```bash
composer require nowo-tech/password-policy-bundle
```

Use a constraint such as `^1.0` to stay on the current major version.

## Register the bundle

### With Symfony Flex

If you use Symfony Flex, the bundle is registered automatically and a default configuration file is created at `config/packages/nowo_password_policy.yaml`.

### Manual registration

1. **Register the bundle** in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\PasswordPolicyBundle\PasswordPolicyBundle::class => ['all' => true],
];
```

2. **Create configuration.** Add `config/packages/nowo_password_policy.yaml` (or rely on the Flex recipe) with at least **`nowo_password_policy.entities`** — that node is **required** for the bundle to compile. See [CONFIGURATION.md](CONFIGURATION.md) for all options.

## Next steps

- Implement `HasPasswordPolicyInterface` and `PasswordHistoryInterface` in your entities.
- Add the `@PasswordPolicy()` constraint to your password field.
- Configure entities and expiry in `config/packages/nowo_password_policy.yaml`.

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md) for details.
