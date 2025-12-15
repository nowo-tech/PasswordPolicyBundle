# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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
