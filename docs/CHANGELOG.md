# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.2.3] - 2026-07-16](#123---2026-07-16)
- [[1.2.2] - 2026-07-16](#122---2026-07-16)
- [[1.2.1] - 2026-07-15](#121---2026-07-15)
- [[1.2.0] - 2026-07-15](#120---2026-07-15)
- [[1.1.1] - 2026-07-14](#111---2026-07-14)
- [[1.1.0] - 2026-07-09](#110---2026-07-09)
- [[1.0.0] - 2026-06-23](#100---2026-06-23)
- [[0.0.14] - 2026-06-23](#0014---2026-06-23)
- [[0.0.13] - 2026-04-17](#0013---2026-04-17)
- [[0.0.12] - 2026-04-17](#0012---2026-04-17)
- [[0.0.11] - 2026-04-15](#0011---2026-04-15)
- [[0.0.10] - 2026-04-15](#010---2026-04-15)
- [[0.0.9] - 2026-03-16](#009---2026-03-16)
- [[0.0.8] - 2025-03-11](#008---2025-03-11)
- [[0.0.6] - 2025-03-10](#006---2025-03-10)
- [[0.0.5] - 2025-12-17](#005---2025-12-17)
- [[0.0.4] - 2025-12-17](#004---2025-12-17)
- [[0.0.3] - 2025-12-17](#003---2025-12-17)
- [[0.0.2] - 2025-12-16](#002---2025-12-16)
- [[0.0.1] - 2025-12-15](#001---2025-12-15)

## [0.0.10] - 2026-04-15

### Added

- **Repository metadata**: `.github/CODEOWNERS`, pull request template, `.github/SECURITY.md`, and `sync-releases.yml` workflow to align GitHub releases with tags.
- **Makefile**: `validate-translations` target to validate `src/Resources/translations/*.yaml` when running QA locally.
- **Documentation**: Translation override procedure for domain `PasswordPolicyBundle` in [USAGE.md](USAGE.md); table of contents updates for long docs (`CHANGELOG.md`, `DEMO-FRANKENPHP.md`).

### Changed

- **Demos**: `.env.example` uses commented variables per Symfony-style templates; distinct `PORT` / `DEFAULT_URI` per demo; demo `.gitignore` grouped by category (local env, dependencies, archives). MySQL services no longer publish host ports (access via Docker network only).
- **Demo orchestration**: Root `demo/Makefile` runs `update-bundle-all` before `release-check` test and verify steps.
- **README**: Call-to-action uses the exact phrase `Found this useful?`; tests section lists PHP coverage and marks TS/JS and Python as N/A where applicable.
- **`make setup-hooks`**: Installs `pre-commit` by copying `.githooks/pre-commit` to `.git/hooks/pre-commit` with execute permission.

### Fixed

- **Coverage script**: `.scripts/php-coverage-percent.sh` is executable and parses PHPUnit `Lines:` summary reliably (ANSI codes, spacing).
- **Tests**: `PasswordPolicyBundleTest` relocated to `tests/Unit/`; unit test fixes for PHP 8.1+ `readonly` properties in mocks.

## [0.0.9] - 2026-03-16

### Added
- **Logging System**: Full logging for password policy events with configurable levels (`debug`, `info`, `notice`, `warning`, `error`) and optional enable/disable via configuration.
- **Symfony Events for Extensibility**: New events (`PasswordExpiredEvent`, `PasswordHistoryCreatedEvent`, `PasswordChangedEvent`, `PasswordReuseAttemptedEvent`) dispatched from the bundle so applications can plug custom logic.
- **Cache System for Performance**: Optional cache for password expiry checks using Symfony Cache (`cache.app`), with TTL configuration and automatic invalidation when the password changes.
- **Multiple Entities Support Improvements**: Additional validation for duplicate routes, reset password route names, and notified routes when configuring multiple entities.
- **Configuration Documentation Enhancements**: More detailed `Configuration.php` node descriptions surfaced in `docs/CONFIGURATION.md` and IDE auto‑completion.

### Fixed
- **Null-Safety and Edge Cases**: Additional guards around entities configuration, route names and date handling in expiry and policy services/listeners based on the coverage analysis.
- **Password History Cleanup**: `PasswordHistoryService::getHistoryItemsForCleanup()` now reliably returns the items that should be removed.

### Changed
- **PasswordPolicyService::isPasswordValid()**: Refined clone and fallback paths with focused tests and small internal refactors for better testability (no behavior change).

## [0.0.8] - 2025-03-11

### Fixed
- **Tests – TokenInterface::getUser() return type**: All test mocks and stubs now respect Symfony’s `?UserInterface` return type
  - User mocks in `PasswordExpiryServiceTest` and `PasswordExpiryListenerTest` implement both `HasPasswordPolicyInterface` and `UserInterface` (including `getUserIdentifier()`, `getRoles()`, `eraseCredentials()`)
  - Anonymous user classes in listener tests implement `UserInterface` fully
  - `testGetCurrentUserWithAnonUser` uses `null` instead of string `'anon.'` (invalid for typed `getUser()`)
  - `testGetCurrentUserWithNonHasPasswordPolicyInterface` uses a `UserInterface` mock instead of `stdClass`
- **PHPStan – Mock properties**: Mock properties in unit tests now use **intersection** types in `@var` docblocks (`Interface&\Mockery\MockInterface`) instead of union types, so PHPStan recognizes `shouldReceive()` and constructor argument types correctly (0 errors at level 8).

### Changed
- **CI – Release workflow**: Changelog extraction uses escaped version in awk; release body is built via `body_path`; step summary outputs release URL.

## [0.0.6] - 2025-03-10

### Fixed
- **Demo Symfony 8 – Login**: Resolved route conflict so the login form is shown at `/`
  - Removed `demo_home` override from `routes.yaml`; home is now at `/home`, login at `/`
  - Moved `UserFixtures` from `DataFixtures/` to `src/DataFixtures/` so fixtures load and demo users exist
- **Demo – Composer path repo**: Fixed `composer update` when using the bundle from a path repository
  - Added Git `safe.directory` for `/var/password-policy-bundle` in demo Dockerfiles
  - Relaxed bundle constraint in demo `composer.json` to `^0.0.5 || dev-main || dev-master` so path repo resolves
- **Demo Symfony 8 – Cache**: Avoid corrupted container cache when mounting volumes
  - Entrypoint runs `cache:clear` on startup in `APP_ENV=dev` so the container cache stays in sync
- **Demo Symfony 8 – Doctrine**: Set `server_version: '8.0'` in Doctrine config to remove MySQL &lt; 8 deprecation
- **Demo Symfony 8 – intl**: Added PHP `intl` extension in Dockerfile to satisfy Symfony recommendation

### Added
- **README**: Screenshots for the Symfony 8 demo (login, home, users management) in `docs/images/`
- **Demo Makefiles**: New `make cache-clear` target in symfony6, symfony7, and symfony8 demos
- **PHPStan**: Support for Mockery and Symfony interfaces in tests
  - Added `phpstan/phpstan-mockery` and `phpstan/extension-installer`
  - Added `symfony/password-hasher` and `symfony/security-core` to require-dev for analysis
  - Extensions loaded via extension-installer (no duplicate includes in `phpstan.neon.dist`)
- **Tests**: Null-safety and assertion fixes for PHPStan level 8
  - `PasswordHistoryTraitTest` and `PasswordHistoryServiceTest`: null checks for `getCreatedAt()`
  - Replaced `assertTrue(true)` with `addToAssertionCount(1)` where appropriate
  - DocBlock generics for `ArrayCollection` and `Collection` return types in tests

### Changed
- **Demo Symfony 8**: Doctrine DBAL config now sets `server_version: '8.0'` explicitly
- **PasswordExpiryListener**: Now receives `TokenStorageInterface` via constructor (injected by the extension as `security.token_storage`). Token is no longer read from the expiry service; if you instantiate the listener manually, add the new second argument.
- **PHPStan level 8 – 0 errors**: Full static analysis compliance without exclusions
  - **src**: Typed arrays (`array<string, mixed>`, `array<int, string>`, etc.), `getRootNode()` only (removed BC branch), `getContainerExtension()` return type, `PasswordAuthenticatedUserInterface` checks in `PasswordPolicyService`, null-safety for `getCreatedAt()` and Carbon, `PasswordHistoryTrait` return types (`DateTimeInterface`, non-null string), Doctrine `ClassMetadata`/attributes removed from listener (registration via DI only), `array_key_exists`/changeSet handling in entity listener
  - **Tests**: Mock types as `Interface|MockInterface` for phpstan-mockery; `ClassMetadata` in tests via reflection helper; `PasswordExpiryListenerTest` and demos use injected `tokenStorage` mock; `PasswordHistoryMock` typed `user` and `getUser()`; `makePasswordHistoryMock` fixed Mockery chaining; redundant assertions replaced or removed
  - **Configuration**: Duplicate extension includes removed so extension-installer is the single source

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
  - Moved `UserController` from root to `src/Controller/` in symfony7 and symfony8
  - Moved form classes (`ChangePasswordType`, `UserType`) to `src/Form/` in symfony7 and symfony8
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
  - Added `UserRepository` to symfony7 and symfony8
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

## [1.2.3] - 2026-07-16

### Removed

- **Demo projects**: Removed Symfony 6 and Symfony 7 demos (`demo/symfony6`, `demo/symfony7`). The Symfony 8 demo (`demo/symfony8`) remains as the reference demo.

### Changed

- **Documentation**: [CONFIGURATION.md](CONFIGURATION.md), [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md), [CONTRIBUTING.md](CONTRIBUTING.md), and [demo/README.md](../demo/README.md) updated for the single Symfony 8 demo.

## [1.2.2] - 2026-07-16

### Added

- **CI — REQ-GIT-001**: New `git-hygiene` job in `.github/workflows/ci.yml` runs `check-no-cursor-coauthor` with full history (`fetch-depth: 0`).
- **History cleanup script**: `.scripts/strip-cursor-coauthor-from-history.sh` and `make strip-cursor-coauthor-from-history` to rewrite local branch messages when trailers are already present.
- **CI operator doc**: [GITHUB_CI.md](GITHUB_CI.md) documents REQ-GIT-001 adoption, CI wiring, and force-push recovery.
- **Tests**: Additional coverage for flash-throttle DI errors, invalid route patterns, and expiry flash subject keys / session-unavailable paths.
- **Hardening**: `PasswordExpiryListener` no longer throws when the request session is unavailable; expiry flash is skipped gracefully.
- **Cleanup**: Removed unreachable numeric-extension guard in `PasswordPolicyService` (max extension length is already 3 digits / 0–999).

### Changed

- **`check-no-cursor-coauthor.sh`**: Validates a bundle-local `.git`, refuses parent-monorepo checkouts, uses `--no-replace-objects`, and lists offending commits on failure.
- **Documentation**: [RELEASE.md](RELEASE.md) and [CONTRIBUTING.md](CONTRIBUTING.md) point to the CI hygiene check and cleanup flow; [README.md](../README.md) links [GITHUB_CI.md](GITHUB_CI.md).

## [1.2.1] - 2026-07-15

### Added

- **Code of Conduct**: [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md) at the repository root (Contributor Covenant 2.1).

### Changed

- **Documentation**: Root [README.md](../README.md) and [CONTRIBUTING.md](CONTRIBUTING.md) link to the Code of Conduct; enforcement contact aligned with [.github/SECURITY.md](../.github/SECURITY.md).

## [1.2.0] - 2026-07-15

### Added

- **Configurable expiry flash strategies**: `expiry_listener.flash_strategy` (`always`, `once_per_session`, `interval`, `never`) and `flash_interval_minutes` control how often the password expiry flash is added. Default remains `always` for backward compatibility.
- **Shared flash throttle storage**: `flash_throttle_storage` (`session` or `cache`) with optional Redis/Memcached via `flash_throttle_cache_service`, for FrankenPHP workers and Kubernetes multi-pod. Custom backends via `ExpiryFlashThrottleStorageInterface`.
- **Example configurations**: [docs/examples/expiry-flash-and-cache.yaml](examples/expiry-flash-and-cache.yaml) with session, Redis, Memcached, and custom storage recipes.

### Changed

- **Documentation**: [CONFIGURATION.md](CONFIGURATION.md) documents flash strategies, throttle storage, and FrankenPHP/Kubernetes guidance; [README.md](../README.md) lists new options and links to the examples file.
- **Demos**: Symfony 6/7/8 demo `nowo_password_policy.yaml` and `cache.yaml` include inline comments and optional Redis/Memcached pools for flash throttle testing.
- **Maintainer tooling**: `.githooks/commit-msg` strips accidental Cursor co-author trailers; `make setup-hooks` installs hooks; `make check-no-cursor-coauthor` runs in `release-check` (REQ-GIT-001).

## [1.1.1] - 2026-07-14

### Fixed

- **Extension detection performance**: `PasswordPolicyService::getHistoryByPasswordExtension()` deduplicates candidate base passwords and replaces 0–999 scan loops with bounded prefix/suffix extraction. Same behaviour when `detect_password_extensions` is enabled; runs only on password change validation, not on every HTTP request.

### Changed

- **Documentation — route configuration**: [CONFIGURATION.md](CONFIGURATION.md) adds [Route configuration recommendations](CONFIGURATION.md#route-configuration-recommendations) (literal routes, minimal `notified_routes`, exclusions for login/logout/reset/API). [USAGE.md](USAGE.md) and [README.md](../README.md) cross-link the guidance.
- **Documentation — validation cost**: [CONFIGURATION.md](CONFIGURATION.md#password-history) documents password-history verification and extension-detection cost on password change.

## [1.1.0] - 2026-07-09

### Added

- **GitHub Spec Kit baseline**: `.specify/` scaffolding, Cursor Agent skills (`.cursor/skills/speckit-*`), and `specs/001-baseline/` with full-product `spec.md` and `code-inventory.md` (100% of `src/` mapped).
- **Spec Kit operator manual**: New [SPEC-KIT.md](SPEC-KIT.md) (install, init, structure, Cursor workflow, maintainer checklist).

### Changed

- **Spec-driven development docs**: [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) documents three layers (Spec Kit baseline, product behavior, `REQ-*` traceability), refined user stories, and explicit scope note (history/expiry/reuse — not password complexity).
- **README**: Link to [SPEC-KIT.md](SPEC-KIT.md) in the documentation index.

### Fixed

- **Demo Docker images (Symfony 6 and 7)**: FrankenPHP images now install the PHP `intl` extension (aligned with Symfony 8 demo and Symfony recommendations).

## [1.0.0] - 2026-06-23

First stable release. The public API, configuration schema, and runtime behavior are unchanged from **0.0.14**; this version marks semver stability for production use.

### Fixed

- **Demo `update-deps` target**: Demo Makefiles (`symfony6`, `symfony7`, `symfony8`) now define `COMPOSE` and `SERVICE_PHP` before including the shared `Makefile.demo-update-deps.mk`, fixing `run: not found` when running `make update-deps` from the bundle or demo aggregator.

## [0.0.14] - 2026-06-23

### Added

- **CodeRabbit integration**: `.coderabbit.yaml` and GitHub Actions workflow for automated pull request reviews.
- **Spec-driven development**: New [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) with repository-local product spec and `REQ-*` traceability; linked from [ENGRAM.md](ENGRAM.md) and [README.md](../README.md).
- **Makefile `update-deps` targets**: Bundle and demo Makefiles include shared update-deps recipes (`REQ-MAKE-008`).

### Changed

- **CI matrix**: Symfony test matrix extended to 7.4 and 8.1 with PHP version exclusions aligned to Symfony requirements.
- **Demos**: Symfony 7 demo targets 7.4; Symfony 8 demo targets 8.1; per-demo Makefiles include `update-deps` includes.
- **README**: Symfony compatibility badge updated (6.0+, 7.4+, 8.0+, 8.1+).
- **Repository URLs**: Corrected GitHub links in `composer.json`, [CONTRIBUTING.md](CONTRIBUTING.md), and [UPGRADING.md](UPGRADING.md) (`PasswordPolicyBundle` canonical repo name).

### Fixed

- **PHP 8.4 deprecation (null array offset)**: `PasswordExpiryService` resolves entity configuration via `getEntityConfiguration()` and no longer uses `null` as an array key when there is no authenticated user. Fixes `Deprecated: Using null as an array offset is deprecated` triggered from `PasswordExpiryListener::isLockedRoute()` on anonymous requests.

## [0.0.13] - 2026-04-17

### Fixed

- **Duplicate expiry flashes across dashboard/API bursts**: `PasswordExpiryListener` now checks existing flash entries (`FlashBag::peek`) before adding a new one, so equivalent PasswordPolicy expiry messages are not duplicated when multiple requests hit in quick succession.
- **Dedup strategy hardening**: Combined request-scoped guard plus session flash dedup keeps behavior stable with FrankenPHP worker mode while avoiding repeated user-facing notifications.

## [0.0.12] - 2026-04-17

### Fixed

- **Password expiry flash duplication**: `PasswordExpiryListener` now guarantees that the expiry flash is added at most once per HTTP request, preventing repeated identical notifications during a single request lifecycle.
- **FrankenPHP compatibility**: The duplicate-guard is request-scoped (stored in request attributes), so it does not rely on static/global state and remains safe with persistent workers.

## [0.0.11] - 2026-04-15

### Added

- **Route name patterns**: `notified_routes` and `excluded_notified_routes` accept literal names, globs (`*`, `?`), or delimited PCRE (`~…~`, `#…#`, `/…/`). Optional `reset_password_route_pattern` resolves the reset route from the router (first alphabetical match among registered route names; fallback to `reset_password_route_name`). See [CONFIGURATION.md](CONFIGURATION.md#route-name-patterns).
- **`PasswordExpiryServiceInterface::isRouteExcluded()`**: Exposes whether the current route matches any `excluded_notified_routes` entry (used by the expiry listener).

### Changed

- **Documentation**: [README.md](../README.md) and [CONFIGURATION.md](CONFIGURATION.md) describe route patterns, reset route resolution, and duplicate-route validation when using patterns across multiple entities.

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
