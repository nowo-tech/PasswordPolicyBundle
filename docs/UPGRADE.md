# Upgrade Guide

This guide provides step-by-step instructions for upgrading the Password Policy Bundle between versions.

## General Upgrade Process

1. **Backup your configuration**: Always backup your `config/packages/nowo_password_policy.yaml` file before upgrading
2. **Check the changelog**: Review [CHANGELOG.md](CHANGELOG.md) for breaking changes in the target version
3. **Update composer**: Run `composer update nowo-tech/password-policy-bundle`
4. **Update configuration**: Apply any configuration changes required for the new version
5. **Clear cache**: Run `php bin/console cache:clear`
6. **Test your application**: Verify that password policy functionality works as expected

## Upgrade Instructions by Version

### Upgrading to 0.0.4

**Release Date**: 2025-12-17

#### What's New

- **PHP 8 Attribute Fix**: Fixed `PasswordPolicy` constraint to properly work as PHP 8 attribute
- **Demo Route Fixes**: Fixed route name references in demo templates
- **Improved Demo Styling**: Enhanced visual styling for use cases pages

#### Breaking Changes

None. This is a bug fix release.

#### Configuration Changes

No configuration changes required.

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

