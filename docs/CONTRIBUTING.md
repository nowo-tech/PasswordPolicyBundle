# Contributing Guide

Thank you for your interest in contributing to Password Policy Bundle! This document provides guidelines for contributing to the project.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to hectorfranco@nowo.com.

## How Can I Contribute?

### Reporting Bugs

If you find a bug, please:

1. **Check that the bug hasn't already been reported** in the [issues](https://github.com/nowo-tech/password-policy-bundle/issues)
2. **Create a new issue** with:
   - A descriptive title
   - Steps to reproduce the problem
   - Expected behavior vs. actual behavior
   - PHP, Symfony, and bundle versions
   - Screenshots if relevant

### Suggesting Enhancements

Enhancement suggestions are welcome:

1. **Check that the enhancement hasn't already been suggested** in the [issues](https://github.com/nowo-tech/password-policy-bundle/issues)
2. **Create a new issue** with:
   - A descriptive title
   - Detailed description of the proposed enhancement
   - Use cases and benefits
   - Possible implementations (if you have them)

### Contributing Code

#### Setting Up the Development Environment

1. **Fork the repository** on GitHub
2. **Clone your fork**:
   ```bash
   git clone https://github.com/your-username/password-policy-bundle.git
   cd password-policy-bundle
   ```
3. **Install dependencies**:
   ```bash
   # With Docker (recommended)
   make install
   
   # Without Docker
   composer install
   ```

4. **Run the demos** (optional, for testing):
   ```bash
   cd demo
   make up-symfony6
   make install-symfony6
   make database-symfony6
   ```
   
   Access the demo at `http://localhost:8001` to see the CRUD interface in action.
   ```

#### Code Standards

The project follows these standards:

- **PSR-12**: PHP code style
- **PHP 8.1+**: Modern PHP features
- **Strict type hints**: `declare(strict_types=1);` in all files
- **PHP-CS-Fixer**: Used to maintain code consistency

**Before committing**:

```bash
# Check code style
make cs-check
# or
composer cs-check

# Fix code style automatically
make cs-fix
# or
composer cs-fix
```

#### Tests

All tests must pass before merging. New features should include tests.

```bash
# Run all tests
make test
# or
composer test

# Run tests with coverage
make test-coverage
# or
composer test-coverage

# View coverage report
open coverage/index.html
```

**Test structure**:
- Tests should be in the `tests/` directory
- Each class should have its corresponding test
- Tests should be descriptive and cover edge cases
- Use mocks when appropriate

#### Pull Request Process

1. **Create a branch** from `develop`:
   ```bash
   git checkout -b feature/my-new-feature
   # or
   git checkout -b fix/my-bug-fix
   ```

2. **Make your changes**:
   - Write clean, well-documented code
   - Add tests for new features
   - Ensure all tests pass
   - Run `make qa` to verify everything

3. **Commit your changes**:
   ```bash
   git add .
   git commit -m "feat: description of feature"
   # or
   git commit -m "fix: description of fix"
   ```
   
   **Commit conventions**:
   - `feat:` New feature
   - `fix:` Bug fix
   - `docs:` Documentation changes
   - `test:` Add or modify tests
   - `refactor:` Code refactoring
   - `style:` Formatting changes (doesn't affect functionality)
   - `chore:` Maintenance tasks

4. **Push to your fork**:
   ```bash
   git push origin feature/my-new-feature
   ```

5. **Create a Pull Request** on GitHub:
   - Clearly describe the changes
   - Mention any related issues
   - Add screenshots if relevant
   - Ensure CI passes

#### Checklist Before PR

- [ ] Code follows PSR-12 standards
- [ ] Ran `make cs-fix` (or `composer cs-fix`)
- [ ] All tests pass (`make test`)
- [ ] Added tests for new functionality
- [ ] Documentation is updated (if necessary)
- [ ] docs/CHANGELOG.md is updated (if necessary)
- [ ] Code is well commented
- [ ] No warnings or errors from PHPStan/Psalm (if used)

## Project Structure

```
password-policy-bundle/
├── src/                    # Bundle source code
│   ├── DependencyInjection/ # Bundle configuration
│   ├── EventListener/      # Event listeners
│   ├── Model/              # Interfaces and models
│   ├── Service/            # Services
│   ├── Validator/          # Validators
│   └── Resources/          # Resources (config)
├── tests/                  # Tests
├── .github/                # GitHub configuration
└── docs/                   # Documentation
```

## Branching Policy

For detailed information about branch naming conventions, workflow, and release process, see [docs/BRANCHING.md](BRANCHING.md).

## Questions

If you have questions about contributing, you can:

- Open an issue on GitHub
- Contact the maintainers at hectorfranco@nowo.com

## Acknowledgments

Thank you for contributing to Password Policy Bundle. Your help makes this project better for everyone.

