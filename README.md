# Password Policy Bundle

[![CI](https://github.com/nowo-tech/password-policy-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/password-policy-bundle/actions/workflows/ci.yml) [![Latest Stable Version](https://poser.pugx.org/nowo-tech/password-policy-bundle/v)](https://packagist.org/packages/nowo-tech/password-policy-bundle) [![License](https://poser.pugx.org/nowo-tech/password-policy-bundle/license)](https://packagist.org/packages/nowo-tech/password-policy-bundle) [![PHP Version Require](https://poser.pugx.org/nowo-tech/password-policy-bundle/require/php)](https://packagist.org/packages/nowo-tech/password-policy-bundle)

Symfony bundle for password policy enforcements including password history, expiry, and validation.

## Features

- ✅ **Password History Tracking** - Prevents users from reusing old passwords
- ✅ **Password Expiry Enforcement** - Forces password changes after a specified period
- ✅ **Configurable Password Policies** - Per-entity configuration for different policies
- ✅ **Doctrine Lifecycle Events Integration** - Automatic password history tracking
- ✅ **Customizable Expiry Notifications** - Configurable routes and flash messages
- ✅ **Validator Constraint** - Symfony validator for password policy validation
- ✅ **Logging System** - Comprehensive logging for password policy events (configurable levels)
- ✅ **Custom Events** - Symfony events for extensibility (PasswordExpiredEvent, PasswordChangedEvent, etc.)
- ✅ **Performance Cache** - Optional caching for password expiry checks with automatic invalidation
- ✅ **Multiple Entities Support** - Configure different password policies for different user types with validation
- ✅ **Flexible Configuration** - Works out of the box with sensible defaults
- ✅ **Modern Symfony Support** - Compatible with Symfony 6, 7, and 8
- ✅ **Complete Documentation** - Comprehensive PHPDoc comments in English
- ✅ **Demo Projects** - Full-featured demos with visual expiry indicators

## Installation

```bash
composer require nowo-tech/password-policy-bundle
```

Then, register the bundle in your `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\PasswordPolicyBundle\PasswordPolicyBundle::class => ['all' => true],
];
```

## Requirements

- PHP >= 8.1, < 8.6
- Symfony >= 6.0 || >= 7.0 || >= 8.0
- Doctrine ORM
- nesbot/carbon >= 3.9

**Optional Dependencies**:
- Symfony Cache Component (`symfony/cache`) - Required only if `enable_cache: true` is used

## Configuration

### Step 1: Implement Required Interfaces

1. Implement `Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface` in the entities that you want to support password policies.

2. Implement `Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface` in a new entity that will hold the password history records.

### Step 2: Add Validation Constraint

Add `@PasswordPolicy()` validation rules to your `$plainPassword` field:

```php
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;

class User implements HasPasswordPolicyInterface
{
    /**
     * @PasswordPolicy()
     */
    private ?string $plainPassword = null;
    
    // ... rest of your entity
}
```

### Step 3: Configure Bundle

The bundle works out of the box with default settings. **No configuration file is required** - the bundle uses sensible defaults defined in `Configuration.php`.

**Important**: The configuration file (`nowo_password_policy.yaml`) is **optional**. You only need to create it if you want to customize the default behavior.

#### Symfony Flex Recipe (Automatic - Recommended)

**If the bundle is installed via Symfony Flex** (from Packagist), the configuration file will be created **automatically** during `composer require`:
- `config/packages/nowo_password_policy.yaml` (configuration with example comments)

**You don't need to do anything else** - the file is created automatically with helpful comments.

**Note**: Flex Recipes only work when the bundle is published in the official Symfony Flex repository (Packagist). If you're using a private bundle or installing from a Git repository, Flex Recipes won't work and you'll need to create the configuration file manually.

#### Manual Configuration

If you're installing manually or want to customize the configuration, create `config/packages/nowo_password_policy.yaml`:

Configure how Password policy will behave on every entity:

```yaml
nowo_password_policy:
    entities:
        # The entity class implementing HasPasswordPolicyInterface
        App\Entity\User:
            # The route where the user will be notified when password is expired
            notified_routes: 
                - user_profile
                - user_settings
            # These routes will be excluded from the expiry check
            excluded_notified_routes: 
                - user_logout
            # Which is the password property in the entity (defaults to 'password')
            password_field: password
            
            # Password history property in the entity (defaults to 'passwordHistory')
            password_history_field: passwordHistory
            
            # How many password changes to track (defaults to 3)
            passwords_to_remember: 5
            
            # Force expiry of the password in that many days (defaults to 90)
            expiry_days: 60
            
            # Route name for password reset (required)
            reset_password_route_name: user_reset_password
    expiry_listener:
        # You can change the expiry listener priority
        priority: 0
        # If true, automatically redirects to reset_password_route_name when password expires
        redirect_on_expiry: false
        error_msg:
            text:
                title: 'Your password expired.'
                message: 'You need to change it'
            type: 'error'
    # Enable logging for password policy events
    enable_logging: true
    # Logging level: debug, info, notice, warning, error
    log_level: info
```

## How It Works

### Password History

The bundle uses Doctrine lifecycle events to create password history and set last password change on the target entities. When a password is changed:

1. The old password is stored in the password history
2. The `passwordChangedAt` timestamp is updated
3. Only the configured number of previous passwords are kept

### Password Expiry

Expiry works by checking the last password change on every request made to the app, excluding those configured in the application:

1. On each request, the bundle checks if the password has expired
2. If expired, the user is redirected to the configured `lock_route`
3. Flash messages are displayed according to the configuration
4. The user cannot access other routes until the password is changed

**Important**: The library uses Doctrine lifecycle events (`onFlush`) to create password history and set last password change. You must be aware that any entity changes after the recalculation will not be persisted to the database.

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `password_field` | `string` | `'password'` | The password property name in the entity |
| `password_history_field` | `string` | `'passwordHistory'` | The password history property name in the entity |
| `passwords_to_remember` | `int` | `3` | How many previous passwords to track |
| `expiry_days` | `int` | `90` | Number of days before password expires |
| `reset_password_route_name` | `string` | **required** | Route name for password reset |
| `notified_routes` | `array` | `[]` | Routes where users will be notified of expiry |
| `excluded_notified_routes` | `array` | `[]` | Routes excluded from expiry check |
| `expiry_listener.priority` | `int` | `0` | Priority of the expiry listener |
| `expiry_listener.lock_route` | `string` | - | Route to redirect when password is expired |
| `expiry_listener.error_msg.text.title` | `string` | - | Error message title |
| `expiry_listener.error_msg.text.message` | `string` | - | Error message body |
| `expiry_listener.error_msg.type` | `string` | `'error'` | Flash message type |

## Usage Examples

### Basic Entity Implementation

```php
use Doctrine\ORM\Mapping as ORM;
use Nowo\PasswordPolicyBundle\Model\HasPasswordPolicyInterface;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;
use Nowo\PasswordPolicyBundle\Validator\PasswordPolicy;

#[ORM\Entity]
class User implements HasPasswordPolicyInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\Column]
    private string $password;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $passwordChangedAt = null;
    
    #[ORM\OneToMany(targetEntity: UserPasswordHistory::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $passwordHistory;
    
    /**
     * @PasswordPolicy()
     */
    private ?string $plainPassword = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
    
    public function getPasswordChangedAt(): ?\DateTime
    {
        return $this->passwordChangedAt;
    }
    
    public function setPasswordChangedAt(\DateTime $dateTime): self
    {
        $this->passwordChangedAt = $dateTime;
        return $this;
    }
    
    public function getPasswordHistory(): Collection
    {
        return $this->passwordHistory;
    }
    
    public function addPasswordHistory(PasswordHistoryInterface $passwordHistory): static
    {
        if (!$this->passwordHistory->contains($passwordHistory)) {
            $this->passwordHistory->add($passwordHistory);
        }
        return $this;
    }
}
```

### Password History Entity

```php
use Doctrine\ORM\Mapping as ORM;
use Nowo\PasswordPolicyBundle\Model\PasswordHistoryInterface;

#[ORM\Entity]
class UserPasswordHistory implements PasswordHistoryInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'passwordHistory')]
    private User $user;
    
    #[ORM\Column]
    private string $password;
    
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $salt = null;
    
    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
    
    public function getSalt(): ?string
    {
        return $this->salt;
    }
    
    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;
        return $this;
    }
    
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
    
    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
```

## Development

### Using Docker (Recommended)

```bash
# Start the container
make up

# Install dependencies
make install

# Run tests (inside container)
make test

# Run tests with coverage (inside container)
make test-coverage

# Run all QA checks (inside container)
make qa
```

**Note**: All commands execute inside the Docker container. The Makefile automatically handles container management and command execution.

## Demo Projects

The bundle includes complete demo projects for Symfony 6.4, 7.0, and 8.0. Each demo includes:

- **MySQL Database**: Isolated database per demo with migrations and initial data
- **Authentication System**: Complete login system with Symfony Security
  - Form-based authentication
  - User session management
  - Login/logout functionality
  - Visual user indicators in the interface
- **CRUD Interface**: Full user management interface to test password policies
- **Password Expiry Notifications**: Visual banners and indicators showing password expiry status
  - Prominent alerts for expired passwords
  - Warnings for passwords expiring soon
  - Flash messages with test credentials information
- **Initial Data**: Pre-configured users with different password expiry states:
  - `expired@example.com` / `expired123` - Password expired 100 days ago (triggers expiry listener)
  - `demo@example.com` / `demo123` - Password expiring soon (85 days old, expires in 5 days)
  - `admin@example.com` / `admin123` - Recently changed password (active)
- **Password History**: Complete password history tracking with timestamps
- **Docker Setup**: Complete Docker Compose configuration

### Running the Demos

```bash
cd demo
make up-symfony6      # Start Symfony 6.4 demo (includes automatic setup)
make up-symfony7      # Start Symfony 7.0 demo
make up-symfony8      # Start Symfony 8.0 demo
```

The `make up-*` commands automatically:
- Install Composer dependencies
- Copy updated bundle files to vendor directory
- Create database and run migrations
- Set up initial data with password history

Access the demos at:
- Symfony 6.4: `http://localhost:8001`
- Symfony 7.0: `http://localhost:8002`
- Symfony 8.0: `http://localhost:8003`

### Testing Password Expiry

1. **Login**: Access `/` (root) and authenticate with `expired@example.com` / `expired123`
2. **Navigate**: After login, you'll be redirected to `/home` - the password expiry listener will trigger
3. **See Warning**: A flash message will appear indicating the password has expired
4. **Test Other Users**: Try logging in with other demo users to see different expiry states
5. **CRUD Access**: The user management CRUD (`/user/*`) requires authentication - you'll be redirected to login if not authenticated

Use the CRUD interface to:
- View password expiry status with visual indicators
- See password expiry warnings and notifications
- Create users with passwords
- Change passwords (tests password history validation)
- View complete password history with timestamps
- Test password expiry enforcement with authentication

For more information, see [demo/README.md](demo/README.md).

## Testing

The bundle includes comprehensive test coverage. All tests are located in the `tests/` directory.

**Important**: Tests must be run inside the Docker container. Use the Makefile commands:

```bash
# Start the Docker container (if not already running)
make up

# Run all tests (inside container)
make test

# Run tests with coverage report (inside container)
make test-coverage

# View coverage report (after test-coverage)
# The coverage report is generated in the coverage/ directory
# Open coverage/index.html in your browser
```

**Note**: The `make test` and `make test-coverage` commands automatically execute the tests inside the PHP container created by `docker-compose.yml`. Do not run `composer test` or `composer test-coverage` directly on your host machine - always use the Makefile commands.

## Code Quality

The bundle uses PHP-CS-Fixer to enforce code style (PSR-12).

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## CI/CD

The bundle uses GitHub Actions for continuous integration:

- **Tests**: Runs on PHP 8.1, 8.2, 8.3, 8.4, and 8.5 with Symfony 6.4, 7.0, and 8.0
- **Code Style**: Automatically fixes code style on push
- **Coverage**: Validates code coverage requirements

See `.github/workflows/ci.yml` for details.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)

## Contributing

Please see [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for details on how to contribute to this project.

## Changelog

Please see [docs/CHANGELOG.md](docs/CHANGELOG.md) for version history.

## Documentation

- **[Configuration Guide](docs/CONFIGURATION.md)** - Detailed configuration options and examples
- **[Upgrade Guide](docs/UPGRADE.md)** - Step-by-step instructions for upgrading between versions
- **[Events Documentation](docs/EVENTS.md)** - Complete guide to custom events and event listeners
- **[Contributing Guide](docs/CONTRIBUTING.md)** - How to contribute to the project
- **[Changelog](docs/CHANGELOG.md)** - Version history and changes
