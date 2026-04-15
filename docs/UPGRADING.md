# Upgrade Guide

This guide provides step-by-step instructions for upgrading the Password Policy Bundle between versions.

## Table of contents

- [General Upgrade Process](#general-upgrade-process)
- [Upgrade Instructions by Version](#upgrade-instructions-by-version)
  - [Upgrading to 0.0.11](#upgrading-to-0011)
  - [Upgrading to 0.0.10](#upgrading-to-0010)
  - [Upgrading to 0.0.9](#upgrading-to-009)
  - [Upgrading to 0.0.8](#upgrading-to-008)
  - [Upgrading to 0.0.6](#upgrading-to-006)
  - [Upgrading to 0.0.5](#upgrading-to-005)
  - [Upgrading to 0.0.4](#upgrading-to-004)
  - [Upgrading to 0.0.3](#upgrading-to-003)
  - [Upgrading to 0.0.2](#upgrading-to-002)
  - [Upgrading to 0.0.1](#upgrading-to-001)
- [Troubleshooting Upgrades](#troubleshooting-upgrades)
  - [Common Issues](#common-issues)
  - [Getting Help](#getting-help)
- [Version Compatibility](#version-compatibility)
- [Notes](#notes)

## General Upgrade Process

1. **Backup your configuration**: Always backup your `config/packages/nowo_password_policy.yaml` file before upgrading
2. **Check the changelog**: Review [CHANGELOG.md](CHANGELOG.md) for breaking changes in the target version
3. **Update composer**: Run `composer update nowo-tech/password-policy-bundle`
4. **Update configuration**: Apply any configuration changes required for the new version
5. **Clear cache**: Run `php bin/console cache:clear`
6. **Test your application**: Verify that password policy functionality works as expected

## Upgrade Instructions by Version

### Upgrading to 0.0.11

**Release Date**: 2026-04-15

#### What's New

- **Route name patterns**: You can use globs (`*`, `?`) or delimited PCRE (`~…~`, `#…#`, `/…/`) in `notified_routes` and `excluded_notified_routes` instead of listing every route name. Optional `reset_password_route_pattern` picks the reset route name from the application router (first match in alphabetical order; see [CONFIGURATION.md](CONFIGURATION.md#route-name-patterns)).
- **Configuration validation**: Invalid PCRE patterns in those fields fail at compile time. Duplicate `notified_routes` checks across multiple entities ignore **pattern-like** entries (wildcards or delimited regex), because overlap is only meaningful at runtime.

#### Breaking Changes

None for normal applications using the bundle’s services. If you maintain a **custom implementation** of `PasswordExpiryServiceInterface` (e.g. in tests), add `isRouteExcluded(string $routeName, ?string $entityClass = null): bool` to match the interface.

#### Configuration Changes

Optional. You can keep existing YAML-only lists of literal route names; behaviour is unchanged. To adopt patterns, see [CONFIGURATION.md](CONFIGURATION.md#route-name-patterns).

#### Upgrade Steps

1. Update the bundle:

   ```bash
   composer update nowo-tech/password-policy-bundle
   ```

2. Clear cache:

   ```bash
   php bin/console cache:clear
   ```

3. Run your test suite.

---

### Upgrading to 0.0.10

**Release Date**: 2026-04-15

#### What's New

- **Tooling & docs**: GitHub templates (`CODEOWNERS`, PR template, security policy), `sync-releases` workflow, `validate-translations` Makefile target, translation override notes in [USAGE.md](USAGE.md), and README/test wording aligned with project standards.
- **Demos**: Stricter `.env.example` / `.gitignore` layout; per-demo `PORT` and `DEFAULT_URI`; MySQL only on the Docker network (no host port mapping). Demo `release-check` runs `update-bundle-all` before tests.
- **Developer experience**: `make setup-hooks` installs a real `pre-commit` file under `.git/hooks/`; `php-coverage-percent.sh` works with colored PHPUnit output.

#### Breaking Changes

None for bundle runtime configuration. If you maintain a **fork of the demos**, refresh `.env` / `.env.example` from this release and remove any reliance on exposing MySQL on the host.

#### Configuration Changes

None required for `nowo_password_policy` YAML in your application.

#### Upgrade Steps

1. Update the bundle:

   ```bash
   composer update nowo-tech/password-policy-bundle
   ```

2. Clear cache:

   ```bash
   php bin/console cache:clear
   ```

3. Run your test suite.

---

### Upgrading to 0.0.9

**Release Date**: 2026-03-16

#### What's New

- **Logging System**: Configurable logging for password expiry, reuse attempts, history creation and route errors (integrated with Symfony’s `LoggerInterface`).
- **Symfony Events**: New bundle events (`PasswordExpiredEvent`, `PasswordHistoryCreatedEvent`, `PasswordChangedEvent`, `PasswordReuseAttemptedEvent`) for extending behaviour in your application.
- **Performance Cache**: Optional cache for password expiry checks (using `cache.app`) with TTL and automatic invalidation when the password changes.
- **Multiple Entities Validation**: Stronger validation of `reset_password_route_name`, notified routes and duplicate route conflicts when configuring multiple entities.
- **Documentation & coverage**: Updated docs (configuration, events) and **100% line coverage** across bundle `src/` classes.

#### Breaking Changes

None. This is a backward‑compatible feature release.

#### Configuration Changes

No required configuration changes if you already followed the configuration for previous versions.

You can optionally:

- Enable logging (if not already enabled) and configure `log_level`.
- Enable caching for expiry checks with `enable_cache: true` and `cache_ttl`.

See `docs/CONFIGURATION.md` and `docs/EVENTS.md` for examples.

#### Upgrade Steps

1. Update the bundle:

   ```bash
   composer update nowo-tech/password-policy-bundle
   ```

2. Clear cache:

   ```bash
   php bin/console cache:clear
   ```

3. Run your test suite and ensure password expiry, history and validation flows still behave as expected.

---

### Upgrading to 0.0.8

**Release Date**: 2025-03-11

#### What's New

- **Tests**: All tests pass with PHP 8.1+ and Symfony 6.4 / 7 / 8; user mocks comply with `TokenInterface::getUser()` return type (`?UserInterface`).
- **PHPStan**: Level 8 with 0 errors; mock properties use intersection types in docblocks for correct static analysis.
- **CI**: Release workflow improvements (changelog extraction, `body_path` for release notes).

#### Breaking Changes

None. Backward-compatible release.

#### Upgrade Steps

1. Update the bundle: `composer update nowo-tech/password-policy-bundle`
2. Clear cache: `php bin/console cache:clear`

---

### Upgrading to 0.0.6

**Release Date**: 2025-03-10

#### What's New

- **Demo and documentation**: Login and cache fixes for Symfony 8 demo, new README screenshots, `make cache-clear` in demos
- **PHPStan**: Level 8 with 0 errors; Mockery extension and Symfony dev deps; all fixes in code (no exclusions)
- **Demo Symfony 8**: Doctrine `server_version` 8.0, PHP `intl` in Docker, optional cache clear on dev startup
- **PasswordExpiryListener**: Token storage is now injected via constructor (`TokenStorageInterface` as second argument). Normal apps using the bundle’s DI do not need changes.

#### Breaking Changes

None. Backward-compatible release.

#### Upgrade Steps

1. Update the bundle: `composer update nowo-tech/password-policy-bundle`
2. Clear cache: `php bin/console cache:clear`

No configuration or code changes required. If you instantiate `PasswordExpiryListener` yourself (e.g. in tests), add `TokenStorageInterface` as the second constructor argument after `PasswordExpiryServiceInterface`.

---

### Upgrading to 0.0.5

**Release Date**: 2025-12-17

#### What's New

- **Enhanced Password Reuse Detection**: Completely rewritten password verification logic for better reliability
  - More robust handling of different hash algorithms
  - Better error handling and fallback mechanisms
  - Improved compatibility with Symfony's password hashers
- **Password Extension Detection**: New feature to prevent users from using extensions of old passwords
  - Detects when users add numbers or characters to their old passwords
  - Configurable per entity or per field
  - Separate error messages for exact matches vs extensions
- **Improved Demo Projects**: Fixed structure issues in demo projects
  - Controllers and forms now in correct locations
  - Updated constraint syntax for Symfony 7/8 compatibility

#### Breaking Changes

None. This is a backward-compatible enhancement release.

#### Configuration Changes

**New Optional Configuration Options**:

You can now enable password extension detection per entity:

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            # ... existing configuration ...
            detect_password_extensions: true  # NEW: Enable extension detection
            extension_min_length: 4           # NEW: Minimum length for extension detection (default: 4)
```

**Default Behavior**: 
- `detect_password_extensions` defaults to `false` (disabled by default)
- `extension_min_length` defaults to `4`
- If not specified, extension detection is disabled, maintaining backward compatibility

#### Code Changes Required

**Optional**: If you want to enable extension detection for specific fields, you can use constraint attributes:

```php
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;

class User
{
    /**
     * @PasswordPolicy(
     *     detectExtensions=true,
     *     extensionMinLength=4,
     *     extensionMessage="Cannot use an extension of an old password"
     * )
     */
    private ?string $plainPassword = null;
}
```

**No code changes required** if you don't want to use extension detection.

#### Upgrade Steps

1. **Update composer.json**:
   ```bash
   composer require nowo-tech/password-policy-bundle:^0.0.5
   ```

2. **Update your configuration** (optional):
   If you want to enable password extension detection, add the new options to your configuration:
   ```yaml
   nowo_password_policy:
       entities:
           App\Entity\User:
               detect_password_extensions: true
               extension_min_length: 4
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Verify installation**:
   Check that your configuration is valid:
   ```bash
   php bin/console debug:config nowo_password_policy
   ```

#### Migration Notes

- **No database changes required**: This version does not require any database migrations
- **No code changes required**: Existing code will continue to work without modifications
- **Optional feature**: Extension detection is disabled by default, so existing behavior is preserved
- **Improved reliability**: Password reuse detection is now more reliable and should work better with different hash algorithms

---

### Upgrading to 0.0.4

**Release Date**: 2025-12-17

#### What's New

- **PHP 8 Attribute Fix**: Fixed `PasswordPolicy` constraint to properly work as PHP 8 attribute
- **Demo Route Fixes**: Fixed route name references in demo templates
- **Improved Demo Styling**: Enhanced visual styling for use cases pages
- **Password Reuse Detection**: Improved password reuse detection with better algorithm support
- **Symfony Compatibility**: Fixed Request API usage for Symfony 6, 7, and 8 compatibility
- **Password History Management**: Added `removePasswordHistory()` method to interface

#### Breaking Changes

None. This is a bug fix and enhancement release.

#### Configuration Changes

No configuration changes required.

#### Code Changes Required

**Important**: If you have entities implementing `HasPasswordPolicyInterface`, you must add the `removePasswordHistory()` method:

```php
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

public function removePasswordHistory(PasswordHistoryInterface $passwordHistory): static
{
    if ($this->passwordHistory->contains($passwordHistory)) {
        $this->passwordHistory->removeElement($passwordHistory);
    }
    return $this;
}
```

This method is required for the bundle to properly manage password history limits.

#### Upgrade Steps

1. **Update composer.json**:
   ```bash
   composer require nowo-tech/password-policy-bundle:^0.0.4
   ```

2. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

3. **Verify installation**:
   Check that your configuration is valid:
   ```bash
   php bin/console debug:config nowo_password_policy
   ```

#### Migration Notes

- **No code changes required**: Existing code will continue to work without modifications
- **No database changes required**: This version does not require any database migrations
- **Bug fix only**: This release fixes issues with PHP 8 attribute support and demo route references

---

### Upgrading to 0.0.3

**Release Date**: 2025-12-17

#### What's New

- **Comprehensive Demo Use Cases System**: New demonstration system in all demo projects
- **Refactored Demo Templates**: Improved template structure with base templates and reusable partials
- **Enhanced Configuration**: Added `redirect_on_expiry` option and improved logging configuration
- **Documentation Fixes**: Fixed duplicate sections in CHANGELOG.md and CONFIGURATION.md

#### Breaking Changes

None. This is a backward-compatible release.

#### Configuration Changes

**New Optional Configuration Option**:

The `redirect_on_expiry` option has been added to `expiry_listener` configuration:

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            # ... existing configuration ...
    expiry_listener:
        priority: 0
        redirect_on_expiry: false  # NEW: Set to true to enable automatic redirection
        error_msg:
            # ... existing configuration ...
```

**Default Behavior**: If not specified, `redirect_on_expiry` defaults to `false`, maintaining backward compatibility.

#### Upgrade Steps

1. **Update composer.json**:
   ```bash
   composer require nowo-tech/password-policy-bundle:^0.0.3
   ```

2. **Update your configuration** (optional):
   If you want to enable automatic redirection when passwords expire, add the new option:
   ```yaml
   nowo_password_policy:
       expiry_listener:
           redirect_on_expiry: true  # Enable automatic redirection
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Verify installation**:
   Check that your configuration is valid:
   ```bash
   php bin/console debug:config nowo_password_policy
   ```

#### Migration Notes

- **No database changes required**: This version does not require any database migrations
- **No code changes required**: Existing code will continue to work without modifications
- **Optional feature**: The new `redirect_on_expiry` option is optional and disabled by default

---

### Upgrading to 0.0.2

**Release Date**: 2025-12-16

#### What's New

- **Logging System**: Complete logging implementation for important bundle events
- **Symfony Events**: Custom events system for extensibility
- **Cache System**: Optional caching for password expiry checks
- **Multiple Entities Support**: Enhanced validation and documentation
- **Critical Bug Fixes**: Fixed several critical issues

#### Breaking Changes

None. This is a backward-compatible release.

#### Configuration Changes

**New Optional Configuration Options**:

1. **Logging Configuration**:
   ```yaml
   nowo_password_policy:
       enable_logging: true  # NEW: Enable/disable logging
       log_level: info       # NEW: Logging level (debug, info, notice, warning, error)
   ```

2. **Cache Configuration**:
   ```yaml
   nowo_password_policy:
       enable_cache: true    # NEW: Enable caching for password expiry checks
       cache_ttl: 3600       # NEW: Cache time-to-live in seconds
   ```

**Default Behavior**: 
- `enable_logging` defaults to `true`
- `log_level` defaults to `info`
- `enable_cache` defaults to `false`
- `cache_ttl` defaults to `3600` seconds

#### Upgrade Steps

1. **Update composer.json**:
   ```bash
   composer require nowo-tech/password-policy-bundle:^0.0.2
   ```

2. **Update your configuration** (optional):
   Add logging and cache configuration if desired:
   ```yaml
   nowo_password_policy:
       enable_logging: true
       log_level: info
       # enable_cache: false  # Optional: enable for performance
       # cache_ttl: 3600
   ```

3. **Clear cache**:
   ```bash
   php bin/console cache:clear
   ```

4. **Verify installation**:
   ```bash
   php bin/console debug:config nowo_password_policy
   ```

#### Migration Notes

- **No database changes required**: This version does not require any database migrations
- **No code changes required**: Existing code will continue to work without modifications
- **Optional features**: All new features are optional and have sensible defaults

---

### Upgrading to 0.0.1

**Release Date**: Initial release

#### What's New

- Initial release of Password Policy Bundle
- Password history tracking
- Password expiry enforcement
- Configurable password policies per entity
- Doctrine lifecycle events integration
- Customizable expiry notifications and routes
- Validator constraint for password policy validation

#### Breaking Changes

N/A - This is the initial release.

#### Configuration

Basic configuration example:

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            password_field: password
            password_history_field: passwordHistory
            passwords_to_remember: 3
            expiry_days: 90
            reset_password_route_name: reset_password
            notified_routes:
                - dashboard
                - profile
            excluded_notified_routes:
                - login
                - logout
    expiry_listener:
        priority: 0
        error_msg:
            text:
                title: nowo_password_policy.title
                message: nowo_password_policy.message
            type: error
```

---

## Troubleshooting Upgrades

### Common Issues

#### Issue: "Unrecognized option" error after upgrade

**Solution**: Clear Symfony cache and update composer dependencies:
```bash
php bin/console cache:clear
composer update nowo-tech/password-policy-bundle
```

#### Issue: Configuration validation errors

**Solution**: Check your configuration against the latest documentation:
```bash
php bin/console debug:config nowo_password_policy
```

#### Issue: Services not found after upgrade

**Solution**: Clear cache and rebuild container:
```bash
php bin/console cache:clear
php bin/console cache:warmup
```

### Getting Help

If you encounter issues during upgrade:

1. Check the [CHANGELOG.md](CHANGELOG.md) for known issues
2. Review the [CONFIGURATION.md](CONFIGURATION.md) for configuration examples
3. Open an issue on [GitHub](https://github.com/nowo-tech/password-policy-bundle/issues)

---

## Version Compatibility

| Bundle Version | Symfony Version | PHP Version |
|---------------|-----------------|-------------|
| 0.0.11        | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.10        | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.6         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.5         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.4         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.3         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.2         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |
| 0.0.1         | 6.0, 7.0, 8.0   | 8.1, 8.2, 8.3, 8.4, 8.5 |

---

## Notes

- Always test upgrades in a development environment first
- Keep backups of your configuration files
- Review breaking changes in the changelog before upgrading
- Some features may require additional Symfony components (e.g., cache, event dispatcher)
- The bundle maintains backward compatibility within major versions (0.x.x)

