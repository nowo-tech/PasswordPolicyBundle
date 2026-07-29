# Makefile for Symfony 8 Demo (Password Policy Bundle)
# Run from this directory: make up, make install, etc.
# §5.5 BUNDLES_STANDARDS_PROMPT: ensure-up, up, down, restart, build, install, test, test-coverage, update-bundle, shell, logs, verify

.PHONY: help up down build install shell logs test test-coverage database update-bundle ensure-up restart verify cache-clear

help:
	@echo "Symfony 8 Demo - Password Policy Bundle"
	@echo ""
	@echo "  make up             Start containers and install deps"
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
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
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
	$(COMPOSE) exec -T php composer test-coverage

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
