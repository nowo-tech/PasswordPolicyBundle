# Makefile for Password Policy Bundle
# Simplifies Docker commands for development.
# All dev targets use the root docker-compose.yml (single file).

COMPOSE_FILE := docker-compose.yml
COMPOSE := docker-compose -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down build shell install test test-coverage coverage-php-percent cs-check cs-fix rector rector-dry phpstan qa release-check release-check-demos composer-sync clean update validate validate-translations assets setup-hooks check-no-cursor-coauthor

# Default target
help:
	@echo "Password Policy Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  build         Rebuild Docker image (no cache)"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies (starts container if needed)"
	@echo "  test          Run PHPUnit tests"
	@echo "  test-coverage Run tests with code coverage"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  release-check Pre-release: co-author audit, cs-fix, cs-check, rector-dry, phpstan, test-coverage, demo healthchecks"
	@echo "  composer-sync Validate composer.json and align composer.lock (no install)"
	@echo "  clean         Remove vendor and cache"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  validate      Run composer validate --strict"
	@echo "  validate-translations Validate translation YAML files"
	@echo "  assets        No-op (no frontend assets in this bundle)"
	@echo "  setup-hooks   Install git pre-commit hooks"
	@echo ""
	@echo "Demos: use make -C demo or make -C demo/<demo-name>"
	@echo ""

# Rebuild Docker image (no cache)
build:
	$(COMPOSE) build --no-cache

# Build and start container
up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 2
	@echo "Installing dependencies..."
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction
	@echo "✅ Container ready!"

# Stop container
down:
	$(COMPOSE) down

# Open shell in container
shell: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) sh

# Ensure container is running (start if not). Used by install, test, cs-check, cs-fix, qa, rector, phpstan.
ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
	fi

# Install dependencies
install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install

# Run tests (no -T so PHPUnit shows colors in console)
test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

# Run tests with coverage (no -T so coverage is shown in console with colors)
test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

# Check code style
cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

# Fix code style
cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

# Rector
rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

# Rector dry-run
rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

# PHPStan
phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

# Run all QA
qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

# Pre-release checks (demos: release-verify if demo/Makefile has it)
release-check: ensure-up check-no-cursor-coauthor composer-sync cs-fix cs-check rector-dry phpstan test-coverage release-check-demos

check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

release-check-demos:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-check 2>/dev/null || true; else true; fi

# Validate composer and sync lock (no install)
composer-sync: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --lock --no-install

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache

# Update composer.lock
update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

# Validate composer.json
validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict

# Validate translation files when the bundle provides translations.
validate-translations: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) php -r "require 'vendor/autoload.php'; foreach (glob('src/Resources/translations/*.yaml') as \$file) { Symfony\\Component\\Yaml\\Yaml::parseFile(\$file); } echo 'Translation files are valid.' . PHP_EOL;"

# No-op for bundles without frontend assets
assets:
	@echo "No frontend assets in this bundle."

# Setup git hooks (pre-commit checks + strip Cursor co-author trailers)
setup-hooks:
	@mkdir -p .git/hooks
	@if [ -f .githooks/pre-commit ]; then \
		cp -f .githooks/pre-commit .git/hooks/pre-commit; \
		chmod +x .git/hooks/pre-commit; \
		echo "✅ pre-commit hook installed at .git/hooks/pre-commit."; \
	else \
		echo "⚠️  .githooks/pre-commit not found. Skipping pre-commit hook."; \
	fi
	@if [ -f .githooks/commit-msg ]; then \
		cp -f .githooks/commit-msg .git/hooks/commit-msg; \
		chmod +x .git/hooks/commit-msg; \
		echo "✅ commit-msg hook installed at .git/hooks/commit-msg."; \
	else \
		echo "⚠️  .githooks/commit-msg not found. Skipping commit-msg hook."; \
	fi


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
