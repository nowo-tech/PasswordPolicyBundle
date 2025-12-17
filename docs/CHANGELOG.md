# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.0.5] - 2025-12-17

### Fixed
- **Password Reuse Detection**: Significantly improved password reuse detection mechanism
  - Completely rewrote `PasswordPolicyService::isPasswordValid()` for better reliability
  - Now uses `password_verify()` as the primary method (most reliable for bcrypt/argon2 hashes)
  - Improved fallback to `UserPasswordHasherInterface` for Symfony-specific hashers
  - Better error handling when cloning entities fails
  - Handles non-cloneable entities by temporarily setting password and restoring it
  - Better compatibility with different password hashing algorithms
  - Resolves issues where password reuse was not being detected correctly
- **Request Route Access**: Fixed compatibility with Symfony 6, 7, and 8
  - Changed `$request->get('_route')` to `$request->attributes->get('_route')` in `PasswordExpiryListener`
  - Uses the correct API for accessing request attributes in modern Symfony versions
  - Maintains full compatibility with Symfony 6, 7, and 8
  - Resolves "Attempted to call an undefined method named 'get'" error
- **Demo Projects Structure**: Fixed controller and form locations
  - Moved `UserController` from root to `src/Controller/` in demo-symfony7 and demo-symfony8
  - Moved form classes (`ChangePasswordType`, `UserType`) to `src/Form/` in demo-symfony7 and demo-symfony8
  - Resolves "Unable to generate a URL for the named route" and "class does not exist" errors
- **Form Constraints Syntax**: Updated constraint syntax for Symfony 7 and 8 compatibility
  - Changed `NotBlank` and `Length` constraints to use named arguments instead of arrays
  - Compatible with Symfony 6, 7, and 8 (all require PHP 8.1+)
  - Resolves "Passing an array of options to configure constraint is no longer supported" errors

### Added
- **Password Extension Detection**: New feature to detect when a new password is an extension of an old password
  - Detects common extension patterns: adding numbers (0-999) or special characters (!, @, #, $, %) to the beginning or end
  - Example: If user had "password" and tries "password123", it will be detected and rejected
  - Configurable per entity via `detect_password_extensions` option
  - Configurable minimum length via `extension_min_length` option (default: 4)
  - Can be enabled globally in YAML configuration or per-field using constraint attributes
  - Separate error message for extensions vs exact matches
  - Logs extension detection attempts with match type information
- **Password History Interface Enhancement**: Added `removePasswordHistory()` method to `HasPasswordPolicyInterface`
  - New method allows proper removal of password history entries from collections
  - Required for `PasswordHistoryService` to maintain history limits correctly
  - All demo entities now implement this method
  - Updated README.md with example implementation
- **Password Policy Configuration Service**: New service for managing entity-specific configurations
  - `PasswordPolicyConfigurationService` stores and retrieves configuration per entity
  - Allows validators to access YAML configuration settings
  - Enables per-entity configuration for extension detection
- **Demo Projects Improvements**: Enhanced demo projects consistency
  - Added `UserRepository` to demo-symfony7 and demo-symfony8
  - All demo projects now have consistent structure and dependencies
  - Fixed missing repository classes that caused autowiring errors

### Changed
- **Doctrine Configuration**: Updated Doctrine configuration for Symfony 8 compatibility
  - Removed deprecated `auto_generate_proxy_classes` option (not needed in ORM 3.0)
  - Removed deprecated `enable_lazy_ghost_objects` option (enabled by default in ORM 3.0)
  - Updated `doctrine/orm` to `^3.0` for Symfony 8 demo
  - Updated `doctrine/doctrine-bundle` to `^3.0` for Symfony 8 demo
  - Updated `doctrine/doctrine-migrations-bundle` configuration for Symfony 8 compatibility
- **PasswordPolicy Constraint**: Enhanced with new options
  - Added `detectExtensions` property to enable extension detection per field
  - Added `extensionMinLength` property to configure minimum length for extension detection
  - Added `extensionMessage` property for custom error message when extension is detected
  - Added new error code `PASSWORD_EXTENSION` for extension violations

## [0.0.4] - 2025-12-17

### Fixed
- **PasswordPolicy Attribute Support**: Fixed PHP 8 attribute support for `PasswordPolicy` constraint
  - Removed `@Annotation` and `@Target` PHPDoc annotations that caused Symfony to use `AnnotationLoader` instead of `AttributeLoader`
  - Now properly uses PHP 8 `#[\Attribute]` attribute for Symfony 6, 7, and 8 compatibility
  - Resolves "Attempting to use non-attribute class as attribute" error
- **Demo Route References**: Fixed incorrect route name references in use cases templates
  - Changed `app_login` to `login` in all use case templates
  - Updated all three demo projects (Symfony 6.4, 7.0, and 8.0)
  - Resolves "Unable to generate a URL for the named route" errors

### Changed
- **Demo Template Styling**: Improved styling for "Additional Resources" section in use cases
  - Added button-style links with hover effects
  - Improved visual consistency and user experience
  - Better responsive layout with flexbox

## [0.0.3] - 2025-12-17

### Added
- **Comprehensive Demo Use Cases System**: Complete demonstration of all bundle features
  - New `UseCasesController` in all demo projects (Symfony 6.4, 7.0, and 8.0)
  - Six detailed use case pages demonstrating:
    - Password Expiry Detection - Real-time expiry status, locked routes, and excluded routes
    - Password History Tracking - Complete history view with timestamps
    - Password Reuse Prevention - Visual demonstration of reuse prevention mechanism
    - Password Validation - Explanation of `@PasswordPolicy` validator constraint
    - Excluded Routes - Understanding route exclusion logic
    - Redirect on Expiry - Automatic redirection configuration
  - Refactored template structure with base template and reusable partials
  - Eliminated code duplication across all use case templates
  - Consistent styling and improved maintainability
  - Integration with `PasswordExpiryServiceInterface` for real-time data
  - Accessible from home page with direct links

### Changed
- **Demo Template Refactoring**: Improved template structure to eliminate code duplication
  - Created `use_cases/base.html.twig` base template for all use case pages
  - Created reusable partials: `_not_authenticated.html.twig` and `_user_status.html.twig`
  - Standardized CSS classes (info-box-success, info-box-danger, etc.)
  - Consistent table styling across all pages
  - Better code organization and maintainability
- **Demo Configuration Updates**: Enhanced configuration files in all demos
  - Added `redirect_on_expiry: false` configuration option (documented)
  - Added `enable_logging: true` and `log_level: info` configuration
  - Added commented cache configuration options for future reference
  - Improved configuration documentation with inline comments

### Fixed
- **CHANGELOG.md**: Fixed duplicate `[Unreleased]` sections and language inconsistencies
  - Consolidated all unreleased changes into single section
  - Translated Spanish content to English for consistency
- **CONFIGURATION.md**: Removed duplicate "Caching" section
  - Eliminated redundant caching documentation
  - Improved document structure

## [Unreleased]

### Added
- **Logging System**: Complete logging implementation for important bundle events
  - Integration with Symfony Logger (LoggerInterface)
  - Configurable logging with levels (debug, info, notice, warning, error)
  - Optional logging (can be disabled with `enable_logging: false`)
  - Logged events:
    - Password expiry detection (with user information and route)
    - Password reuse attempt (with user information and days since use)
    - Successful password change (with information about removed entries)
    - Route generation errors
  - Configuration in `nowo_password_policy.yaml`:
    ```yaml
    nowo_password_policy:
        enable_logging: true
        log_level: info
    ```
- **Symfony Events for Extensibility**: Complete custom events system
  - `PasswordExpiredEvent` - Dispatched when password expiry is detected
  - `PasswordHistoryCreatedEvent` - Dispatched when password history entry is created
  - `PasswordChangedEvent` - Dispatched when password is changed
  - `PasswordReuseAttemptedEvent` - Dispatched when password reuse is attempted
  - Developers can listen to these events to extend functionality
  - Optional EventDispatcher (doesn't break if not available)
  - Usage example:
    ```php
    use Nowo\PasswordPolicyBundle\Event\PasswordExpiredEvent;
    
    #[AsEventListener]
    public function onPasswordExpired(PasswordExpiredEvent $event): void
    {
        // Custom logic when password expires
    }
    ```
- **Test Coverage Analysis**: Comprehensive analysis document created (`docs/COVERAGE_ANALYSIS.md`)
  - Detailed coverage breakdown by class and method
  - Identification of coverage gaps and missing tests
  - Recommendations for achieving 100% coverage
  - Current coverage estimated at ~92-95%
- **Configuration Documentation**: Enhanced configuration definitions with descriptive information
  - Added `->info()` descriptions to all configuration nodes in `Configuration.php`
  - Each configuration option now has clear, helpful descriptions explaining its purpose
  - Improves developer experience when configuring the bundle
  - Descriptions are visible in IDE autocomplete and configuration validation
- **Cache System for Performance**: Implemented caching for password expiry checks
  - Cache expiry status per user to improve performance
  - Automatic cache invalidation when password changes
  - Configurable cache TTL (default: 3600 seconds / 1 hour)
  - Optional cache (disabled by default, enable with `enable_cache: true`)
  - Integration with Symfony Cache Component (`cache.app` service)
  - Smart cache key includes user ID, class, and password change timestamp
  - Configuration:
    ```yaml
    nowo_password_policy:
        enable_cache: true
        cache_ttl: 3600
    ```
- **Multiple Entities Support**: Enhanced validation and documentation for multiple entities
  - Automatic validation of duplicate routes across entities
  - Validates unique `reset_password_route_name` per entity
  - Validates `notified_routes` for conflicts
  - Clear error messages indicating which entities have conflicts
  - Comprehensive documentation with examples for different user types (User, Admin, ApiUser)
  - Best practices guide for configuring multiple entities
  - Validation occurs at container compilation time

### Fixed
- **Critical Bug Fixes**: Fixed several critical issues found during bundle review
  - **NullPointerException Prevention**: Added null check for `$this->entities` in `PasswordExpiryService::isPasswordExpired()` to prevent errors when called before entities are configured
  - **Route Null Handling**: Added null check for route name in `PasswordExpiryListener::onKernelRequest()` to prevent errors with anonymous or unnamed routes
  - **Password History Cleanup Bug**: Fixed `PasswordHistoryService::getHistoryItemsForCleanup()` to correctly return removed items instead of empty array
  - **Password Validation Robustness**: Improved `PasswordPolicyService::isPasswordValid()` with better error handling and fallback to `password_verify()` when cloning fails
  - **Future Date Validation**: Added validation to skip password expiry check if `passwordChangedAt` is in the future (data error handling)
  - **Code Cleanup**: Removed unused `PasswordPolicy.php` empty class file
  - **Import Cleanup**: Removed unused `SessionInterface` import from `PasswordExpiryListener`

### Added
- **Critical Improvements - Phase 1**: Implemented critical functionality improvements
  - **`getResetPasswordRouteName()` Implementation**: Complete implementation of reset password route name retrieval
    - Added `resetPasswordRouteName` property to `PasswordExpiryConfiguration`
    - Implemented `getResetPasswordRouteName()` method in `PasswordExpiryService`
    - Updated interface to support entity class parameter
    - Added comprehensive tests for the new functionality
  - **Optional Redirect on Password Expiry**: Enhanced `PasswordExpiryListener` with optional redirection
    - Added `redirect_on_expiry` configuration option (default: `false`)
    - When enabled, automatically redirects to reset password route when password expires
    - Maintains backward compatibility (only shows flash message by default)
    - Graceful error handling if route doesn't exist
  - **Configuration Validation**: Improved configuration validation
    - Validates that `reset_password_route_name` is not empty
    - Validates that `notified_routes` and `excluded_notified_routes` contain valid strings
    - Clear error messages for invalid configuration
    - Validation occurs at container compilation time
- **PHPDoc Documentation**: Added comprehensive PHPDoc comments in English to all classes, interfaces, traits, and methods
  - Complete class-level documentation describing purpose and functionality
  - Method-level documentation with parameter and return type descriptions
  - Property documentation with type information
  - Improved code maintainability and IDE support
- **Demo Authentication System**: Complete login system implemented in all demo projects
  - Symfony Security configuration with form login
  - User authentication with database-backed user provider
  - Login and logout functionality
  - User session management with visual indicators
  - Login page with demo credentials information
  - Flash messages showing available test users and their passwords
- **Demo Password Expiry Notifications**: Enhanced demo projects with visual password expiry information
  - Prominent banners showing password expiry status (expired, expiring soon, warning)
  - Detailed expiry information in user list with days remaining
  - Informative sections explaining password expiry policy
  - Pre-configured demo users with different expiry states for testing
  - Flash messages informing users about test credentials and how to trigger expiry warnings
- **Demo Database Initialization**: Improved database setup in demos
  - `init.sql` files now include initial data with password history
  - Automatic password history creation for demo users
  - Migrations updated to use `IF NOT EXISTS` for compatibility with init scripts
  - Dynamic date calculations for realistic expiry scenarios
  - Bcrypt password hashes documented with corresponding plain passwords

### Changed
- **PasswordExpiryListener Modernization**: Updated to use `RequestStack` instead of deprecated `SessionInterface`
  - Better compatibility with modern Symfony versions (6, 7, 8)
  - Improved session handling and request management
  - Updated service configuration and dependency injection
- **Demo UI Improvements**: Standardized layout and visual consistency
  - All containers now use consistent width (1200px max-width)
  - Unified styling across all demo templates
  - Improved responsive design with proper box-sizing
  - Better visual hierarchy and information display
  - Fixed banner alignment issues for password expiry messages
  - Improved flash message display with support for structured messages (title + body)
- **Code Quality**: Enhanced code documentation and maintainability
  - All PHP classes now have complete PHPDoc comments
  - Improved type hints and documentation
  - Better IDE autocomplete and static analysis support
- **Test Suite Improvements**: Fixed and updated test cases
  - Corrected `PasswordExpiryServiceTest` to use proper array format for `lockRoutes`
  - Replaced non-existent `testGenerateLockedRoute()` with `testGetLockedRoutes()` and `testIsLockedRoute()`
  - All tests now properly aligned with current implementation

## [0.0.2] - 2025-12-16

### Fixed
- **Test Suite Improvements**: Fixed multiple test issues and added missing test cases
  - Added missing Doctrine dependencies (`doctrine/orm`, `doctrine/collections`) to `composer.json` for tests
  - Added Mockery as dev dependency for mocking in tests
  - Fixed `ValidationException` to properly extend `Exception` class
  - Fixed `PasswordHistoryTrait` return types to match interface (`setPassword()` and `setCreatedAt()` now return `self`)
  - Fixed `PasswordPolicyValidator::validate()` to return `void` as required by Symfony's `ConstraintValidatorInterface`
  - Added test for error handling when reset password route generation fails (`testOnKernelRequestWithInvalidRoute`)
  - Added tests for non-cloneable objects and objects without `setPassword()` method in `PasswordPolicyService`
  - Improved test mocks to properly handle `tokenStorage` access in `PasswordExpiryListener` tests
  - Fixed test type hints and return types for better compatibility
  - Fixed `PasswordPolicyExtensionTest` to use mock entity classes instead of non-existent `App\Entity\User`
  - Fixed `testOnFlushUpdates` to properly mock `getIdentityMap()` method
  - Fixed `testOnKernelRequestExcludedRoute` to correctly handle `isPasswordExpired()` call expectations
  - Improved `PasswordPolicyService` tests to use valid bcrypt hashes for `password_verify()` fallback
  - Fixed tests for `getUserIdentifier()` and `getEmail()` methods using concrete classes instead of mocks for `method_exists()` compatibility
  - **All tests now passing**: 77 tests, 129 assertions, 0 errors, 0 failures

### Added
- **Test Coverage Improvements**: Significantly increased test coverage
  - Added comprehensive tests for private methods using reflection (`getCacheKey()`, `getCurrentUser()`, `prepareEntityClass()`)
  - Added tests for logging methods with different log levels in all listeners and validators
  - Added tests for edge cases in `PasswordPolicyService::isPasswordValid()` (exceptions, fallbacks)
  - Added tests for cache functionality in `PasswordExpiryService` (cache hit, cache miss, cache invalidation)
  - Added tests for `PasswordExpiryListener` with different scenarios (array error messages, event dispatcher, getUserIdentifier, getEmail)
  - Added tests for `PasswordEntityListener` logging functionality
  - Added tests for `PasswordPolicyValidator` logging functionality
  - Added tests for `PasswordPolicyExtension` validation (duplicate routes, duplicate notified routes)
  - Current test coverage: **84.46% lines, 60% methods** (473/560 lines covered)

## [0.0.1] - 2025-12-15

### Added
- Initial release of Password Policy Bundle
- Password history tracking
- Password expiry enforcement
- Configurable password policies per entity
- Doctrine lifecycle events integration
- Customizable expiry notifications and routes
- Validator constraint for password policy validation
- Support for Symfony 6.0, 7.0, and 8.0
- Support for PHP 8.1, 8.2, 8.3, 8.4, and 8.5
- **Symfony Flex Recipe**: Created Flex recipe for automatic installation and configuration
  - Bundle registration is now automatic when using Symfony Flex
  - Default configuration file is automatically created at `config/packages/nowo_password_policy.yaml`
  - Recipe includes manifest.json, configuration file with examples, and post-install message
  - Recipe structure ready for publishing to symfony/recipes-contrib or private recipe repository
- **Demo Projects**: Complete demo projects for Symfony 6.4, 7.0, and 8.0
  - Each demo includes MySQL database with isolated containers (ports 33061, 33062, 33063)
  - Doctrine migrations for database schema (`users` and `password_history` tables)
  - DataFixtures with sample users in different password expiry states:
    - User with password expiring soon (85 days old)
    - User with recently changed password
    - User with expired password (100 days old)
  - Full CRUD interface for testing password policy functionality:
    - List users with expiry status indicators (Active, Expiring Soon, Expired)
    - Create new users with password validation
    - Edit users and change passwords (tests password history validation)
    - View user details with complete password history
    - Delete users
  - Visual indicators for password expiry status with color-coded badges
  - Password history tracking demonstration with timestamps
  - Docker Compose setup with PHP-FPM, Nginx, and MySQL
  - Makefile commands for easy demo management (`make database-<demo>`)
  - Two options for loading initial data: DataFixtures (recommended) or MySQL init scripts
  - Complete form validation and error handling
  - Well-structured Twig templates using inheritance and partials
- **Documentation Structure**: Comprehensive documentation in `docs/` directory
  - `BRANCHING.md` - Git branching strategy
  - `CHANGELOG.md` - Version history
  - `CONTRIBUTING.md` - Contribution guidelines
  - `CONFIGURATION.md` - Detailed configuration guide
- **Development Tools**: Complete development environment setup
  - Docker and Docker Compose configuration
  - Makefile with common development commands
  - PHP CS Fixer configuration
  - PHPUnit configuration with coverage
  - GitHub Actions CI/CD workflow
  - Scripts for database setup, testing, and code quality
- **Test Coverage**: Comprehensive test suite with 100% code coverage
  - Unit tests for all services, validators, and listeners
  - Tests for DependencyInjection (Configuration and Extension)
  - Tests for PasswordPolicyBundle
  - CI/CD validation of 100% coverage requirement

### Changed
- **Migration to Nowo.tech**: Complete migration from `hec-franco/password-policy-bundle` to `nowo-tech/password-policy-bundle`
  - Changed namespace from `HecFranco\PasswordPolicyBundle` to `Nowo\PasswordPolicyBundle`
  - Updated configuration alias from `hec_franco_password_policy` to `nowo_password_policy`
  - Updated all references and documentation
  - Updated composer.json with proper vendor, namespace, and metadata
  - Updated service definitions to use new namespace
  - Updated translation files with new configuration alias
  - Updated to follow ESTANDARES_MINIMOS.md standards
- **Modern Symfony Components**: Updated to use modern Symfony security components
  - Replaced deprecated encoder interfaces with `UserPasswordHasherInterface`
  - Updated PHPDoc comments to reflect correct Symfony security interfaces
  - Improved compatibility with Symfony 6.0, 7.0, and 8.0
- **Demo Templates Refactoring**: Improved Twig template structure in demo projects
  - Created base template (`base.html.twig`) with centralized CSS styles
  - Implemented template inheritance using `{% extends %}` and `{% block %}`
  - Created reusable partial templates (`_password_status.html.twig`, `_user_actions.html.twig`)
  - Eliminated code duplication across all templates
  - Improved maintainability and consistency across all demo templates
  - Applied refactoring to all three demo projects (Symfony 6.4, 7.0, and 8.0)

### Features
- **Password History**: Prevents users from reusing old passwords
- **Password Expiry**: Forces password changes after a specified period
- **Flexible Configuration**: Per-entity configuration for different password policies
- **Doctrine Integration**: Automatic password history tracking via Doctrine events
- **Route-based Expiry**: Configurable routes for expiry notifications and exclusions
