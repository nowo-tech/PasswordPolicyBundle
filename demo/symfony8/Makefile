# Makefile for Symfony 8 Demo (Password Policy Bundle)
# Run from this directory: make up, make install, etc.
# §5.5 BUNDLES_STANDARDS_PROMPT: ensure-up, up, down, restart, build, install, test, test-coverage, update-bundle, shell, logs, verify

.PHONY: help up down build install shell logs test test-coverage database update-bundle ensure-up restart verify cache-clear

help:
	@echo "Symfony 8 Demo - Password Policy Bundle"
	@echo ""
	@echo "  make up             Start containers and install deps"
	@echo "  make test-e2e       Playwright e2e (REQ-DEMO-013)"
	@echo "  make demo-screenshots  Regenerate README screenshots (WebP)"
	@echo "  make down           Stop containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Install dependencies (composer install)"
	@echo "  make database       Create DB, run migrations, load fixtures"
	@echo "  make cache-clear    Clear Symfony cache"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make logs           Show container logs"
	@echo "  make test           Run tests"
	@echo "  make test-coverage   Run tests with coverage"
	@echo "  make update-bundle  Update bundle from path and clear cache"
	@echo "  make restart        Restart containers"
	@echo "  make verify         Start, wait for HTTP 200, then stop"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@echo "Installing dependencies (one-off container: the worker-mode server needs vendor/ before it starts)..."
	@$(COMPOSE) run --rm -T --no-deps --entrypoint composer -e COMPOSER_MEMORY_LIMIT=-1 php install --no-interaction --no-scripts
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "Setting up database (create, migrations, fixtures)..."
	@$(COMPOSE) exec -T php composer database
	@$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "Demo ready. Login: admin@example.com / admin123, demo@example.com / demo123, expired@example.com / expired123."
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		$(COMPOSE) up -d; \
		sleep 5; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction

database: ensure-up
	$(COMPOSE) exec -T php composer database

cache-clear: ensure-up
	$(COMPOSE) exec -T php php bin/console cache:clear

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -T -e APP_ENV=test php composer test

test-coverage: ensure-up
	$(COMPOSE) exec -T -e XDEBUG_MODE=coverage php composer test-coverage

update-bundle: ensure-up
	$(COMPOSE) exec -T php composer update nowo-tech/password-policy-bundle --no-interaction
	$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "Bundle updated from /var/password-policy-bundle."

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | head -1 || echo "8001"); \
	$(COMPOSE) up -d; sleep 8; \
	for i in 1 2 3 4 5 6 7 8 9 10; do \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
		if [ "$$code" = "200" ]; then echo "OK HTTP 200"; $(COMPOSE) down; exit 0; fi; \
		sleep 3; \
	done; \
	$(COMPOSE) down; echo "Verify failed: no HTTP 200"; exit 1


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
SERVICE_PHP := php
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk

# REQ-DEMO-013: Playwright e2e + README screenshots (official Playwright image; WebP via sharp).
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy

.PHONY: test-e2e demo-screenshots

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8003}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8003}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"
