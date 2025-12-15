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
        lock_route: user_change_password
        error_msg:
            text:
                title: 'Your password expired.'
                message: 'You need to change it'
            type: 'error'
```

## Configuration Options

### Entity Configuration

Each entity that implements `HasPasswordPolicyInterface` must be configured under `entities`:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `password_field` | `string` | `'password'` | The password property name in the entity |
| `password_history_field` | `string` | `'passwordHistory'` | The password history property name in the entity |
| `passwords_to_remember` | `int` | `3` | How many previous passwords to track |
| `expiry_days` | `int` | `90` | Number of days before password expires |
| `reset_password_route_name` | `string` | **required** | Route name for password reset |
| `notified_routes` | `array` | `[]` | Routes where users will be notified of expiry |
| `excluded_notified_routes` | `array` | `[]` | Routes excluded from expiry check |

### Expiry Listener Configuration

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `priority` | `int` | `0` | Priority of the expiry listener |
| `lock_route` | `string` | - | Route to redirect when password is expired |
| `error_msg.text.title` | `string` | - | Error message title |
| `error_msg.text.message` | `string` | - | Error message body |
| `error_msg.type` | `string` | `'error'` | Flash message type |

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
3. Redirects to `lock_route` if expired
4. Shows flash message with configured text

**Important**: The bundle uses Doctrine `onFlush` event. Any entity changes after password history recalculation will not be persisted.

## Examples

### Basic Configuration

```yaml
nowo_password_policy:
    entities:
        App\Entity\User:
            reset_password_route_name: user_reset_password
    expiry_listener:
        lock_route: user_change_password
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
        lock_route: user_change_password
        error_msg:
            text:
                title: 'Password Expired'
                message: 'Your password has expired. Please change it to continue.'
            type: 'warning'
```

## Best Practices

1. **Set appropriate expiry days**: Balance security with user experience
2. **Configure notified routes**: Help users understand when password is about to expire
3. **Exclude logout routes**: Prevent redirect loops
4. **Use meaningful route names**: Make configuration self-documenting
5. **Test expiry behavior**: Ensure expiry works correctly in your application flow
6. **Use Symfony Flex Recipe**: Let Flex automatically create the configuration file
7. **Test with demos**: Use the included demo projects to understand bundle behavior

## Demo Projects

The bundle includes demo projects for Symfony 6.4, 7.0, and 8.0 that demonstrate:
- Complete CRUD interface for user management
- Password change functionality with validation
- Visual password expiry status indicators
- Password history tracking
- Database setup with migrations and fixtures

See [demo/README.md](../demo/README.md) for more information on running the demos.

