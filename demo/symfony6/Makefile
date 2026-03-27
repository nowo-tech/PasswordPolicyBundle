# Makefile for Symfony 6 Demo (Password Policy Bundle)
# Run from this directory: make up, make install, etc.
# §5.5 BUNDLES_STANDARDS_PROMPT: ensure-up, up, down, restart, build, install, test, test-coverage, update-bundle, shell, logs, verify

.PHONY: help up down build install shell logs test test-coverage database update-bundle ensure-up restart verify cache-clear

help:
	@echo "Symfony 6 Demo - Password Policy Bundle"
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
	docker-compose build
	docker-compose up -d
	@echo "Waiting for containers..."
	@sleep 5
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "Setting up database (create, migrations, fixtures)..."
	docker-compose exec -T php composer database
	docker-compose exec -T php php bin/console cache:clear
	@DEFAULT_URI=$$(grep "^DEFAULT_URI=" .env 2>/dev/null | cut -d= -f2- | head -1 || echo "http://localhost:8001"); \
	echo "Demo ready. Login: admin@example.com / admin123, demo@example.com / demo123, expired@example.com / expired123."; \
	echo "Demo started at: $$DEFAULT_URI"

down:
	docker-compose down

restart: ensure-up
	docker-compose restart

build:
	docker-compose build --no-cache

ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting containers..."; \
		docker-compose up -d; \
		sleep 5; \
	fi

install: ensure-up
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction

database: ensure-up
	docker-compose exec -T php composer database

cache-clear: ensure-up
	docker-compose exec -T php php bin/console cache:clear

shell: ensure-up
	docker-compose exec php sh

logs:
	docker-compose logs -f

test: ensure-up
	docker-compose exec -T -e APP_ENV=test php composer test

test-coverage: ensure-up
	docker-compose exec -T php composer test-coverage

update-bundle: ensure-up
	docker-compose exec -T php composer update nowo-tech/password-policy-bundle --no-interaction
	docker-compose exec -T php php bin/console cache:clear
	@echo "Bundle updated from /var/password-policy-bundle."

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | head -1 || echo "8001"); \
	docker-compose up -d; sleep 8; \
	for i in 1 2 3 4 5 6 7 8 9 10; do \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
		if [ "$$code" = "200" ]; then echo "OK HTTP 200"; docker-compose down; exit 0; fi; \
		sleep 3; \
	done; \
	docker-compose down; echo "Verify failed: no HTTP 200"; exit 1
