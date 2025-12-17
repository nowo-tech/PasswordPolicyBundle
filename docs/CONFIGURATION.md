# Configuration Guide

This document describes how to configure the Password Policy Bundle.

## Configuration File

The bundle configuration is defined in `config/packages/nowo_password_policy.yaml`:

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            password_field: password
            password_history_field: passwordHistory
            passwords_to_remember: 5
            expiry_days: 60
            reset_password_route_name: user_reset_password
            notified_routes: 
                - user_profile
                - user_settings
            excluded_notified_routes: 
                - user_logout
    expiry_listener:
        priority: 0
        redirect_on_expiry: false
        error_msg:
            text:
                title: 'Your password expired.'
                message: 'You need to change it'
            type: 'error'
    enable_logging: true
    log_level: info
```

## Configuration Options

### Entity Configuration

Each entity that implements `HasPasswordPolicyInterface` must be configured under `entities`:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `password_field` | `string` | `'password'` | The name of the password field in the entity. This field will be monitored for changes to track password history. |
| `password_history_field` | `string` | `'passwordHistory'` | The name of the password history collection field in the entity. This should be a OneToMany or ManyToMany relationship to a PasswordHistoryInterface entity. |
| `passwords_to_remember` | `int` | `3` | The maximum number of previous passwords to keep in history. When this limit is exceeded, the oldest passwords are automatically removed. |
| `expiry_days` | `int` | `90` | Number of days after which a password expires. After this period, users will be notified or redirected to change their password. |
| `reset_password_route_name` | `string` | **required** | The route name for password reset. This route will be used when `redirect_on_expiry` is enabled. Must be a valid route name in your application. |
| `notified_routes` | `array` | `[]` | List of route names where users will be notified if their password is expired or about to expire. The expiry listener will check these routes and show flash messages. |
| `excluded_notified_routes` | `array` | `[]` | List of route names excluded from password expiry checks. Useful for excluding login, logout, or password reset routes to prevent redirect loops. |

### Expiry Listener Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `priority` | `int` | `0` | Priority of the expiry listener. Higher values mean the listener runs earlier. Default is 0. |
| `lock_route` | `string` | - | (Deprecated) Route to redirect when password is expired. Use `redirect_on_expiry` and `reset_password_route_name` instead. |
| `redirect_on_expiry` | `bool` | `false` | If `true`, automatically redirects users to the `reset_password_route_name` when their password expires. If `false`, only shows a flash message without redirecting. |
| `error_msg.text.title` | `string` | - | Error message title. Can be a string or translation key. Supports translation keys. |
| `error_msg.text.message` | `string` | - | Error message body. Can be a string or translation key. Supports translation keys. |
| `error_msg.type` | `string` | `'error'` | Flash message type. Common values: "error", "warning", "info", "success". This determines the CSS class and styling of the flash message. |

### Logging Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_logging` | `bool` | `true` | Enable or disable logging for password policy events. When enabled, important events like password expiry, password changes, and reuse attempts will be logged using Symfony Logger. |
| `log_level` | `string` | `'info'` | Logging level for password policy events. Valid values: "debug", "info", "notice", "warning", "error". All password policy events (expiry detection, password changes, reuse attempts) will be logged at this level. |

### Cache Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_cache` | `bool` | `false` | Enable caching for password expiry checks. When enabled, expiry status is cached per user to improve performance. Cache is automatically invalidated when password changes. Requires Symfony Cache component. |
| `cache_ttl` | `int` | `3600` | Cache time-to-live in seconds. Default is 3600 (1 hour). Only used when `enable_cache` is `true`. The cache key includes user ID, class, and password change timestamp, so it's automatically invalidated when password changes. |

## How It Works

### Password History

The bundle uses Doctrine lifecycle events (`onFlush`) to:
1. Track password changes
2. Store old passwords in history
3. Update `passwordChangedAt` timestamp
4. Limit history to configured number of passwords

### Password Expiry

The expiry listener checks on each request:
1. Calculates days since last password change
2. Compares with configured `expiry_days`
3. Shows flash message with configured text
4. If `redirect_on_expiry` is `true`, redirects to `reset_password_route_name` automatically

**Note**: By default, only a flash message is shown. To enable automatic redirection, set `redirect_on_expiry: true` in the configuration.

**Important**: The bundle uses Doctrine `onFlush` event. Any entity changes after password history recalculation will not be persisted.

### Caching

When `enable_cache` is `true`, the bundle caches password expiry status per user to improve performance:

- **Cache Key**: Includes user ID, entity class, and password change timestamp
- **Automatic Invalidation**: Cache is automatically invalidated when a password changes
- **TTL**: Configurable via `cache_ttl` (default: 3600 seconds / 1 hour)
- **Requirements**: Requires Symfony Cache component (`cache.app` service)

**Benefits**:
- Reduces database queries on each request
- Improves response time for applications with many concurrent users
- Cache automatically stays in sync with password changes

**When to Enable**:
- Applications with high traffic
- Multiple password expiry checks per request
- When performance is a concern

**When to Disable**:
- Development environments
- When real-time expiry status is critical
- If cache service is not available

## Examples

### Basic Configuration

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            reset_password_route_name: user_reset_password
    expiry_listener:
        redirect_on_expiry: false
    enable_logging: true
    log_level: info
```

### Advanced Configuration

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            password_field: password
            password_history_field: passwordHistory
            passwords_to_remember: 10
            expiry_days: 30
            reset_password_route_name: user_reset_password
            notified_routes: 
                - user_dashboard
                - user_profile
            excluded_notified_routes: 
                - user_logout
                - api_login
        App\Entity\Admin:
            passwords_to_remember: 20
            expiry_days: 15
            reset_password_route_name: admin_reset_password
            notified_routes: 
                - admin_dashboard
    expiry_listener:
        priority: 10
        redirect_on_expiry: true
        error_msg:
            text:
                title: 'Password Expired'
                message: 'Your password has expired. Please change it to continue.'
            type: 'warning'
    enable_logging: true
    log_level: info
    enable_cache: true
    cache_ttl: 3600
```

## Multiple Entities Configuration

The bundle supports configuring multiple entities with different password policies. This is useful when you have different user types (e.g., regular users and administrators) that require different password policies.

### Important Considerations

1. **Unique Routes**: Each entity must have a unique `reset_password_route_name`. The bundle validates this at configuration time and will throw an error if duplicates are found.

2. **Route Conflicts**: While `notified_routes` can overlap between entities, it's recommended to use entity-specific routes or properly configure `excluded_notified_routes` to avoid conflicts.

3. **Entity Matching**: The bundle automatically matches the current authenticated user to the correct entity configuration based on the user's class.

### Example: Multiple Entities

```yaml
nowo_password_policy:
    entities:
        # Regular users
        App\Entity\User:
            passwords_to_remember: 5
            expiry_days: 90
            reset_password_route_name: user_reset_password  # Must be unique
            notified_routes: 
                - user_dashboard
                - user_profile
            excluded_notified_routes: 
                - user_logout
                - user_reset_password
        
        # Administrators with stricter policy
        App\Entity\Admin:
            passwords_to_remember: 10
            expiry_days: 30
            reset_password_route_name: admin_reset_password  # Must be unique
            notified_routes: 
                - admin_dashboard
                - admin_settings
            excluded_notified_routes: 
                - admin_logout
                - admin_reset_password
        
        # API users with different policy
        App\Entity\ApiUser:
            passwords_to_remember: 3
            expiry_days: 180
            reset_password_route_name: api_reset_password  # Must be unique
            notified_routes: []
            excluded_notified_routes: 
                - api_login
                - api_logout
    expiry_listener:
        priority: 0
        redirect_on_expiry: false
    enable_logging: true
    log_level: info
    enable_cache: true
    cache_ttl: 3600
```

### Validation

The bundle automatically validates:
- ✅ Each entity has a unique `reset_password_route_name`
- ✅ No duplicate `notified_routes` across entities (warns if found)
- ✅ All route names are valid strings
- ✅ Entity classes exist and implement `HasPasswordPolicyInterface`

If validation fails, a `ConfigurationException` is thrown with a clear error message indicating which entities have conflicts.

## Events

The bundle dispatches custom Symfony events that you can listen to for extending functionality. For complete documentation on events, including detailed examples and integration patterns, see [Events Documentation](EVENTS.md).

**Quick Reference**:
- **`PasswordExpiredEvent`**: Dispatched when a password expiry is detected
- **`PasswordHistoryCreatedEvent`**: Dispatched when a password history entry is created
- **`PasswordChangedEvent`**: Dispatched when a password is changed
- **`PasswordReuseAttemptedEvent`**: Dispatched when a user attempts to reuse an old password

See [Events Documentation](EVENTS.md) for complete details, examples, and best practices.

## Best Practices

1. **Set appropriate expiry days**: Balance security with user experience
2. **Configure notified routes**: Help users understand when password is about to expire
3. **Exclude logout routes**: Prevent redirect loops
4. **Use meaningful route names**: Make configuration self-documenting
5. **Enable redirect on expiry**: Set `redirect_on_expiry: true` to automatically redirect users to password reset page
6. **Validate route names**: Ensure `reset_password_route_name` and all route names in `notified_routes` exist in your application
7. **Enable logging**: Use `enable_logging: true` and configure appropriate `log_level` for debugging and auditing
8. **Enable cache for performance**: Use `enable_cache: true` in production to improve performance. Cache is automatically invalidated on password changes.
9. **Unique routes for multiple entities**: When configuring multiple entities, ensure each has a unique `reset_password_route_name` to avoid conflicts.
10. **Listen to events**: Use custom events to extend functionality (notifications, external logging, etc.)
11. **Test expiry behavior**: Ensure expiry works correctly in your application flow
12. **Use Symfony Flex Recipe**: Let Flex automatically create the configuration file
13. **Test with demos**: Use the included demo projects to understand bundle behavior

## Demo Projects

The bundle includes demo projects for Symfony 6.4, 7.0, and 8.0 that demonstrate:
- Complete CRUD interface for user management
- Password change functionality with validation
- Visual password expiry status indicators
- Password history tracking
- Database setup with migrations and fixtures

See [demo/README.md](../demo/README.md) for more information on running the demos.

