# Events Documentation

The Password Policy Bundle dispatches custom Symfony events that allow you to extend functionality and integrate with your application's workflow.

## Overview

The bundle provides four custom events that are dispatched at key moments in the password policy lifecycle:

1. **PasswordExpiredEvent** - When a password expiry is detected
2. **PasswordHistoryCreatedEvent** - When a password history entry is created
3. **PasswordChangedEvent** - When a password is changed
4. **PasswordReuseAttemptedEvent** - When a user attempts to reuse an old password

All events are optional - the bundle works perfectly fine without any event listeners. The EventDispatcher is injected optionally, so the bundle won't break if it's not available.

## Available Events

### PasswordExpiredEvent

**Dispatched when**: A password expiry is detected by the `PasswordExpiryListener` when accessing a locked route.

**Event Class**: `Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent`

**Available Methods**:
- `getUser()`: Returns the `HasPasswordPolicyInterface` user whose password expired
- `getRoute()`: Returns the route name that triggered the expiry check
- `willRedirect()`: Returns `true` if redirect is enabled, `false` otherwise

**Use Cases**:
- Send email notifications when password expires
- Log to external systems
- Trigger security alerts
- Update user status in external systems

**Example**:
```php
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class PasswordExpiryNotificationListener
{
    #[AsEventListener]
    public function onPasswordExpired(PasswordExpiredEvent $event): void
    {
        $user = $event->getUser();
        $route = $event->getRoute();
        
        // Send email notification
        $this->emailService->sendPasswordExpiredNotification($user);
        
        // Log to external system
        $this->logger->warning('Password expired', [
            'user_id' => $user->getId(),
            'route' => $route,
        ]);
    }
}
```

### PasswordHistoryCreatedEvent

**Dispatched when**: A new password history entry is created after a password change.

**Event Class**: `Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent`

**Available Methods**:
- `getUser()`: Returns the `HasPasswordPolicyInterface` user whose password was changed
- `getPasswordHistory()`: Returns the `PasswordHistoryInterface` entry that was created
- `getRemovedEntriesCount()`: Returns the number of old password history entries that were removed (due to `passwords_to_remember` limit)

**Use Cases**:
- Audit password changes
- Track password change frequency
- Monitor password history cleanup
- Integration with security systems

**Example**:
```php
use Nowo\PasswordPolicyBundle\Event\PasswordHistoryCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class PasswordChangeAuditListener
{
    #[AsEventListener]
    public function onPasswordHistoryCreated(PasswordHistoryCreatedEvent $event): void
    {
        $user = $event->getUser();
        $history = $event->getPasswordHistory();
        $removedCount = $event->getRemovedEntriesCount();
        
        // Audit log
        $this->auditLogger->info('Password changed', [
            'user_id' => $user->getId(),
            'changed_at' => $history->getCreatedAt(),
            'old_passwords_removed' => $removedCount,
        ]);
    }
}
```

### PasswordChangedEvent

**Dispatched when**: A password is changed and the `passwordChangedAt` timestamp is updated.

**Event Class**: `Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent`

**Available Methods**:
- `getUser()`: Returns the `HasPasswordPolicyInterface` user whose password was changed
- `getChangedAt()`: Returns the `DateTimeInterface` timestamp when the password was changed

**Use Cases**:
- Update user session information
- Invalidate old sessions
- Trigger password change notifications
- Update external systems

**Example**:
```php
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class PasswordChangeListener
{
    #[AsEventListener]
    public function onPasswordChanged(PasswordChangedEvent $event): void
    {
        $user = $event->getUser();
        $changedAt = $event->getChangedAt();
        
        // Invalidate old sessions
        $this->sessionManager->invalidateUserSessions($user, $changedAt);
        
        // Send confirmation email
        $this->emailService->sendPasswordChangeConfirmation($user);
    }
}
```

### PasswordReuseAttemptedEvent

**Dispatched when**: A user attempts to reuse an old password during validation.

**Event Class**: `Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent`

**Available Methods**:
- `getUser()`: Returns the `HasPasswordPolicyInterface` user attempting to reuse password
- `getPasswordHistory()`: Returns the `PasswordHistoryInterface` entry that matches the attempted password

**Use Cases**:
- Security monitoring
- Track password reuse attempts
- Alert administrators
- Log security events

**Example**:
```php
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class PasswordReuseSecurityListener
{
    #[AsEventListener]
    public function onPasswordReuseAttempted(PasswordReuseAttemptedEvent $event): void
    {
        $user = $event->getUser();
        $history = $event->getPasswordHistory();
        
        // Security alert
        $this->securityService->logSecurityEvent('password_reuse_attempt', [
            'user_id' => $user->getId(),
            'password_used_days_ago' => (new \DateTime())->diff($history->getCreatedAt())->days,
        ]);
        
        // Alert administrators if multiple attempts
        if ($this->hasMultipleReuseAttempts($user)) {
            $this->adminNotifier->notifyPasswordReusePattern($user);
        }
    }
}
```

## Listening to Events

### Method 1: Using Attributes (Symfony 6.1+)

The recommended way to listen to events is using the `#[AsEventListener]` attribute:

```php
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class MyEventListener
{
    #[AsEventListener]
    public function onPasswordExpired(PasswordExpiredEvent $event): void
    {
        // Your custom logic here
    }
}
```

### Method 2: Using services.yaml

You can also configure event listeners in your `config/services.yaml`:

```yaml
services:
    App\EventListener\PasswordExpiryListener:
        tags:
            - { name: kernel.event_listener, event: 'Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent', method: onPasswordExpired }
```

### Method 3: Using Event Subscribers

Create an event subscriber class:

```php
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordPolicySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PasswordExpiredEvent::class => 'onPasswordExpired',
        ];
    }
    
    public function onPasswordExpired(PasswordExpiredEvent $event): void
    {
        // Your custom logic here
    }
}
```

## Event Priority

You can control the order in which event listeners are executed by setting priorities:

```php
#[AsEventListener(priority: 10)]
public function onPasswordExpired(PasswordExpiredEvent $event): void
{
    // This listener will run before listeners with lower priority
}
```

Or in `services.yaml`:

```yaml
services:
    App\EventListener\HighPriorityListener:
        tags:
            - { name: kernel.event_listener, event: 'Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent', method: onPasswordExpired, priority: 10 }
```

## Best Practices

1. **Keep listeners lightweight**: Event listeners should execute quickly. For heavy operations (like sending emails), consider using message queues.

2. **Handle exceptions gracefully**: If your listener throws an exception, it might prevent other listeners from executing. Wrap critical logic in try-catch blocks.

3. **Use dependency injection**: Event listeners are services, so you can inject any dependencies you need.

4. **Test your listeners**: Write unit tests for your event listeners to ensure they work correctly.

5. **Don't modify event objects**: Event objects should be treated as immutable. Don't modify the user or other properties directly.

6. **Use appropriate priorities**: Set priorities to ensure your listeners run in the correct order relative to other listeners.

## Integration Examples

### Email Notifications

```php
use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\MailerInterface;

class PasswordExpiryEmailListener
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }
    
    #[AsEventListener]
    public function onPasswordExpired(PasswordExpiredEvent $event): void
    {
        $user = $event->getUser();
        
        $email = (new Email())
            ->to($user->getEmail())
            ->subject('Your password has expired')
            ->text('Please change your password to continue using the service.');
            
        $this->mailer->send($email);
    }
}
```

### External Logging

```php
use Nowo\PasswordPolicyBundle\Event\PasswordChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class ExternalLoggingListener
{
    public function __construct(
        private readonly ExternalLoggingService $externalLogger
    ) {
    }
    
    #[AsEventListener]
    public function onPasswordChanged(PasswordChangedEvent $event): void
    {
        $user = $event->getUser();
        
        $this->externalLogger->log([
            'event' => 'password_changed',
            'user_id' => $user->getId(),
            'timestamp' => $event->getChangedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
```

### Security Monitoring

```php
use Nowo\PasswordPolicyBundle\Event\PasswordReuseAttemptedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class SecurityMonitoringListener
{
    public function __construct(
        private readonly SecurityAlertService $alertService
    ) {
    }
    
    #[AsEventListener]
    public function onPasswordReuseAttempted(PasswordReuseAttemptedEvent $event): void
    {
        $user = $event->getUser();
        $history = $event->getPasswordHistory();
        
        $daysAgo = (new \DateTime())->diff($history->getCreatedAt())->days;
        
        // Alert if trying to reuse a very recent password
        if ($daysAgo < 30) {
            $this->alertService->sendAlert('Recent password reuse attempt', [
                'user' => $user->getId(),
                'days_ago' => $daysAgo,
            ]);
        }
    }
}
```

## Related Documentation

- [Configuration Guide](CONFIGURATION.md) - Learn how to configure the bundle
- [Contributing Guide](CONTRIBUTING.md) - Learn how to contribute to the bundle


