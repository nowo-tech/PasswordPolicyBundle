# Password Policy Bundle - Demo

This directory contains three demo projects, one for each supported Symfony version (6.4, 7.0, and 8.0), demonstrating the usage of the Password Policy Bundle.

## Features

- Three separate demo projects for Symfony 6.4, 7.0, and 8.0
- Complete CRUD interface for user management
- Well-structured Twig templates using inheritance and partials
- Docker setup for easy development
- Independent Docker containers for each demo
- MySQL database with migrations and DataFixtures

## Requirements

- Docker and Docker Compose
- Or PHP 8.1+ to 8.5 (8.2+ for Symfony 8.0) and Composer (for local development)
- MySQL 8.0 (included in Docker Compose)

## Quick Start with Docker

Each demo has its own `docker-compose.yml` and can be run independently. You can start any demo you want:

**Important**: Before starting a demo, copy `.env.example` to `.env`:
```bash
cd demo/demo-symfony6
cp .env.example .env
# Optionally generate a new APP_SECRET: openssl rand -hex 32
# The .env.example includes: APP_ENV=dev, APP_SECRET (placeholder), APP_DEBUG=1, PORT=8001
# Note: Symfony 7.0 and 8.0 also include DEFAULT_URI for routing configuration
```

### Symfony 6.4 Demo

```bash
# Navigate to the demo directory
cd demo/demo-symfony6

# Copy .env.example to .env if not already done
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Setup database (create, migrate, load fixtures)
docker-compose exec php composer database

# Access at: http://localhost:8001 (port configured in .env file)
```

Or using the Makefile from the `demo/` directory:

```bash
cd demo
make up-symfony6
make install-symfony6
make database-symfony6

# Or verify that the demo is running correctly
make verify DEMO=symfony6
```

### Symfony 7.0 Demo

```bash
# Navigate to the demo directory
cd demo/demo-symfony7

# Copy .env.example to .env if not already done
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Access at: http://localhost:8001 (port configured in .env file, default: 8001)
```

Or using the Makefile:

```bash
cd demo
make up-symfony7
make install-symfony7

# Or verify that the demo is running correctly
make verify DEMO=symfony7
```

### Symfony 8.0 Demo

```bash
# Navigate to the demo directory
cd demo/demo-symfony8

# Copy .env.example to .env if not already done
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec php composer install

# Access at: http://localhost:8001 (port configured in .env file, default: 8001)
```

Or using the Makefile:

```bash
cd demo
make up-symfony8
make install-symfony8

# Or verify that the demo is running correctly
make verify DEMO=symfony8
```

## Local Development (without Docker)

### Symfony 6.4 Demo

1. **Navigate to the demo directory:**
   ```bash
   cd demo/demo-symfony6
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Start the Symfony server:**
   ```bash
   symfony server:start
   ```

### Symfony 7.0 Demo

1. **Navigate to the demo directory:**
   ```bash
   cd demo/demo-symfony7
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Start the Symfony server:**
   ```bash
   symfony server:start
   ```

### Symfony 8.0 Demo

1. **Navigate to the demo directory:**
   ```bash
   cd demo/demo-symfony8
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

Each demo includes:

- **DemoController**: A simple controller with a demo page
- **UserController**: Complete CRUD controller for user management
- **Templates**: Well-structured Twig templates using best practices
  - Base template (`base.html.twig`) with centralized CSS styles
  - Template inheritance using `{% extends %}` and `{% block %}`
  - Reusable partial templates for common components
  - All templates follow DRY (Don't Repeat Yourself) principles
- **Docker Setup**: Complete Docker configuration with PHP-FPM, Nginx, and MySQL
- **Dockerfile**: Custom PHP-FPM image with Composer pre-installed
- **MySQL Database**: Each demo has its own isolated MySQL database
  - Symfony 6.4: Port 33061
  - Symfony 7.0: Port 33062
  - Symfony 8.0: Port 33063
- **Doctrine Migrations**: Database schema migrations
- **DataFixtures**: Sample users with different password expiry states
  - `demo@example.com` - Password expiring soon (85 days old)
  - `admin@example.com` - Recently changed password
  - `expired@example.com` - Expired password (100 days old)
- **Password Policy Configuration**: Example configuration file showing all available options
  - Located at `config/packages/nowo_password_policy.yaml`
  - Demonstrates password history, expiry, and policy options

## Demo Structure

```
demo/
├── demo-symfony6/          # Symfony 6.4 demo (Port 8001 by default)
│   ├── docker-compose.yml  # Independent docker-compose for this demo
│   ├── Dockerfile          # PHP-FPM image with Composer
│   ├── nginx.conf          # Nginx configuration
│   ├── composer.json       # Dependencies
│   ├── .env.example        # Template for .env file (copy to .env and configure)
│   ├── config/packages/nowo_password_policy.yaml  # Bundle configuration example
│   └── ...
├── demo-symfony7/          # Symfony 7.0 demo (Port 8001 by default)
│   ├── docker-compose.yml  # Independent docker-compose for this demo
│   ├── Dockerfile          # PHP-FPM image with Composer
│   ├── nginx.conf          # Nginx configuration
│   ├── composer.json       # Dependencies
│   ├── .env.example        # Template for .env file (copy to .env and configure)
│   ├── config/packages/nowo_password_policy.yaml  # Bundle configuration example
│   └── ...
├── demo-symfony8/          # Symfony 8.0 demo (Port 8001 by default)
│   ├── docker-compose.yml  # Independent docker-compose for this demo
│   ├── Dockerfile          # PHP-FPM image with Composer
│   ├── nginx.conf          # Nginx configuration
│   ├── composer.json       # Dependencies
│   ├── .env.example        # Template for .env file (copy to .env and configure)
│   ├── config/packages/nowo_password_policy.yaml  # Bundle configuration example
│   └── ...
└── Makefile                # Helper commands for all demos
```

Each demo is completely independent with its own `docker-compose.yml` and `nginx.conf`.

**Note**: Before starting a demo, copy `.env.example` to `.env` in the demo directory:
```bash
cd demo/demo-symfony6
cp .env.example .env
# Edit .env and set your APP_SECRET (or generate one with: openssl rand -hex 32)
# The .env.example file includes standard Symfony variables:
# - APP_ENV=dev
# - APP_SECRET=change_this_secret_key_to_a_random_value (replace with your secret)
# - APP_DEBUG=1
# - PORT=8001 (change if needed for multiple demos)
# - DEFAULT_URI=http://localhost (required for Symfony 7.0 and 8.0 routing configuration)
# - MySQL configuration (MYSQL_ROOT_PASSWORD, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD, MYSQL_PORT)
```

**Important**: Each demo uses a different MySQL port to avoid conflicts:
- Symfony 6.4: Port 33061
- Symfony 7.0: Port 33062
- Symfony 8.0: Port 33063

## Database Setup

Each demo includes a MySQL database with migrations and sample data. There are two ways to load initial data:

### Option 1: DataFixtures (Recommended)

DataFixtures are loaded using Doctrine Fixtures Bundle. This is the recommended approach as it's more flexible and integrates better with Symfony:

```bash
cd demo/demo-symfony6
docker-compose exec php composer database
```

This command will:
1. Create the database if it doesn't exist
2. Run all migrations
3. Load DataFixtures with sample users

### Option 2: MySQL Init Script

Alternatively, you can use the MySQL init script located at `docker/mysql/init.sql`. This script is automatically executed when MySQL container starts for the first time. However, for this demo, we use DataFixtures instead.

To use the init script approach:
1. Edit `docker/mysql/init.sql` with your SQL statements
2. Remove the MySQL volume to force re-initialization: `docker-compose down -v`
3. Start the containers: `docker-compose up -d`

**Note**: The init script approach is less flexible and doesn't integrate with Symfony's password hashing, so DataFixtures are recommended.

## CRUD Interface

The demos include a complete CRUD (Create, Read, Update, Delete) interface for managing users and testing password policies.

### Template Structure

The demo templates are well-organized using Twig best practices:

- **Base Template** (`base.html.twig`): Centralized CSS styles and HTML structure
- **Template Inheritance**: All templates extend the base template using `{% extends %}`
- **Reusable Partials**: Common components like password status badges and user actions are in partial templates
  - `_password_status.html.twig`: Displays password expiry status badges
  - `_user_actions.html.twig`: Displays action buttons (View, Edit, Change Password, Delete)
- **Blocks**: Customizable sections using `{% block %}` for title, styles, and body content

This structure eliminates code duplication and makes the templates easy to maintain and extend.

## How It Works

The bundle automatically:

1. Tracks password history for configured entities
2. Enforces password expiry policies
3. Validates passwords against configured policies
4. Integrates with Doctrine lifecycle events

## Testing

Each demo includes tests that can be run with:

```bash
# From the demo directory
cd demo/demo-symfony6
docker-compose exec php composer test

# Or using the Makefile from demo/
cd demo
make test DEMO=symfony6
```

### Running Tests for All Demos

```bash
cd demo
make test-all
```

### Running Tests with Coverage

```bash
# For a specific demo
cd demo
make test-coverage DEMO=symfony6

# For all demos
make test-coverage-all
```

Coverage reports are generated in:
- HTML: `demo/demo-symfony6/coverage/index.html` (and similar for other demos)
- Clover XML: `demo/demo-symfony6/coverage.xml` (and similar for other demos)

### Test Structure

Each demo includes:

- **Controller Tests**: Verify that the demo controller works correctly
- **Bundle Integration Tests**: Verify that the Password Policy Bundle is properly integrated
- **Code Coverage**: 100% coverage for demo application code (DemoController and Kernel are fully tested)

## Verification

You can verify that all demos are running and responding correctly:

```bash
cd demo

# Verify all demos (starts and checks each one sequentially)
make verify-all

# Or verify a specific demo
make verify DEMO=symfony6
```

The `verify-all` command will:
1. Start each demo sequentially (symfony6, symfony7, symfony8)
2. Check that each demo responds with HTTP 200
3. Show a summary with successful/failed demos
4. Display access URLs for successfully verified demos

**Example output:**
```
🚀 Starting and verifying all demos...

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📦 Processing Symfony 6.4 demo (symfony6)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🔍 Verifying Symfony 6.4 demo...
✅ Symfony 6.4 demo is running and responding at http://localhost:8001 (HTTP 200)
✅ Symfony 6.4 demo verified successfully

[... similar for other demos ...]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Verification Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Successful: 3/3
✅ All demos verified successfully!
```

