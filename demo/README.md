# Password Policy Bundle - Demo

This directory contains a demo project for Symfony 8 demonstrating the usage of the Password Policy Bundle.

## Features

- Demo project for Symfony 8
- **Authentication System**: Complete login system with Symfony Security
  - Form-based authentication
  - User session management
  - Login/logout functionality
  - Visual user indicators in the interface
- Complete CRUD interface for user management
- **Password Expiry Notifications**: Visual banners and indicators showing password expiry status
- **Password Expiry Information**: Detailed information about password expiry policy and days remaining
- Well-structured Twig templates using inheritance and partials
- **FrankenPHP** (§2.4): Single service `php` (no nginx). With **`APP_ENV=dev`**, the image **entrypoint** uses **`Caddyfile.dev`** (`php_server` **without** worker, cache-busting headers). The image’s default **`Caddyfile`** can use **`worker`** for production-style runs. HTTP on port 80 in the container; access via `http://localhost:PORT` (see the demo’s `.env`).
- Docker setup aligned with BUNDLES_STANDARDS_PROMPT.md; `docker-compose.yml` has `name: password-policy-bundle-demo-symfony-8` and mounts the bundle root at `/var/password-policy-bundle`.
- Composer path repository `"/var/password-policy-bundle"` so the bundle is used from the repo when running in Docker.
- Makefile in `symfony8` with targets: up, down, build, install, test, test-coverage, update-bundle, ensure-up, restart, shell, logs, verify.
- MySQL database with migrations, initial data, and password history.

## Requirements

- Docker and Docker Compose
- Or PHP 8.4+ and Composer (for local development)
- MySQL 8.0 (included in Docker Compose)

## Quick Start with Docker

**Important**: Before starting the demo, copy `.env.example` to `.env`:
```bash
cd demo/symfony8
cp .env.example .env
# Optionally generate a new APP_SECRET: openssl rand -hex 32
# The .env.example includes: APP_ENV=dev, APP_SECRET (placeholder), APP_DEBUG=1, PORT=8003, DEFAULT_URI
```

### Symfony 8 Demo

```bash
# Navigate to the demo directory
cd demo/symfony8

# Copy .env.example to .env if not already done
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Setup database (create, migrate, load fixtures)
docker-compose exec php composer database

# Access at: http://localhost:8003 (port configured in .env file)
```

Or using the Makefile from the `demo/` directory:

```bash
cd demo
make up-symfony8  # Automatically installs dependencies, sets up database, and runs migrations

# Or verify that the demo is running correctly
make verify DEMO=symfony8
```

**Note**: The `make up-*` commands automatically:
- Install Composer dependencies (bundle from `/var/password-policy-bundle` when in Docker)
- Create database and run migrations
- Set up initial data with password history

## Local Development (without Docker)

1. **Navigate to the demo directory:**
   ```bash
   cd demo/symfony8
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Start the Symfony server:**
   ```bash
   symfony server:start
   ```

## What's Included

The demo includes:

- **DemoController**: A simple controller with a demo page
- **UserController**: Complete CRUD controller for user management
- **Templates**: Well-structured Twig templates using best practices
- **Docker Setup**: Complete Docker configuration with FrankenPHP and MySQL
- **MySQL Database**: Isolated MySQL database (host port 33063 by default)
- **Doctrine Migrations**: Database schema migrations
- **Initial Data**: Pre-configured users with different password expiry states and complete password history
  - `expired@example.com` / `expired123` - Password expired 100 days ago
  - `demo@example.com` / `demo123` - Password changed 85 days ago (expires in 5 days)
  - `admin@example.com` / `admin123` - Password changed today (active)
- **Password Policy Configuration**: Example at `config/packages/nowo_password_policy.yaml`

## Demo Structure

```
demo/
├── symfony8/               # Symfony 8 demo (Port 8003 by default)
│   ├── docker-compose.yml
│   ├── Dockerfile
│   ├── composer.json
│   ├── .env.example
│   ├── config/packages/nowo_password_policy.yaml
│   └── ...
└── Makefile
```

## Database Setup

```bash
cd demo/symfony8
docker-compose exec php composer database
```

## Authentication and Password Expiry Testing

1. Access `/login`
2. Login with `expired@example.com` / `expired123`
3. Navigate home — the password expiry listener will trigger
4. Try other demo users for different expiry states

## Use Cases Demonstration

Access the Use Cases section from the home page or at `/use-cases`.

## Testing

```bash
cd demo
make test DEMO=symfony8
make test-coverage DEMO=symfony8
```

Coverage reports:
- HTML: `demo/symfony8/coverage/index.html`
- Clover XML: `demo/symfony8/coverage.xml`

## Verification

```bash
cd demo
make verify DEMO=symfony8
# or
make verify-all
```
