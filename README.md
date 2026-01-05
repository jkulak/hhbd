# HHBD - Hip-Hop Database

Polish Hip-Hop Database application.

## Project Structure

```bash
hhbd-new/
├── app/                  # Frontend Zend Framework application
├── backoffice/           # Admin panel (admin + xadmin)
├── content/              # User-uploaded images (artists, albums, news)
├── database/             # SQL dumps for database initialization
├── conf/                 # Nginx and other configuration
└── compose.yaml          # Docker services configuration
```

## Quick Start

1. Start all services:

   ```bash
   docker compose up -d
   ```

2. Import the database (first time only):

   ```bash
   docker compose exec -T db mysql -uhhbd -phhbd_password hhbd < database/_backup/2016-06-30-hhbd.sql
   ```

3. Install PHP dependencies (required after first build):

   ```bash
   docker compose exec app composer install
   ```

4. Access the applications:
   - Frontend: <http://localhost:8080>
   - Backoffice: <http://localhost:8081/admin/>
   - Adminer (DB): <http://localhost:8082>

5. Stop services:

   ```bash
   docker compose down
   ```

## Services

| Service | Port | Description |
| --------- | ------ | ------------- |
| nginx | 8080 | Frontend web server |
| app | 9000 | PHP-FPM application server |
| backoffice | 8081 | Backoffice admin panel (Apache) |
| adminer | 8082 | Database management |
| db | 3306 | MariaDB database |

## Development

### Dev Container (Recommended)

The project includes a VS Code Dev Container for a consistent PHP 7.4 development environment.

**Prerequisites:**

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [VS Code](https://code.visualstudio.com/) with [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)

**Getting Started:**

1. Open the project folder in VS Code
2. When prompted, click "Reopen in Container" (or use Command Palette: `Dev Containers: Reopen in Container`)
3. VS Code will build the container and install dependencies automatically

**Included tools:**

- PHP 7.4 with GD, MySQLi, PDO extensions
- Composer 2
- Xdebug 3.1 (pre-configured for VS Code debugging)
- PHP CodeSniffer

**Running tests in dev container:**

```bash
cd app && ./vendor/bin/phpunit -c tests/phpunit.xml
```

**Running code sniffer:**

```bash
cd app && ./vendor/bin/phpcs --standard=PSR12 application/
```

### Code Refactoring with Rector

Rector is included to help modernize and improve the legacy codebase automatically.

**Dry run (preview changes):**

```bash
docker compose exec app ./vendor/bin/rector process --dry-run
```

**Apply changes:**

```bash
docker compose exec app ./vendor/bin/rector process
```

**Process specific directory:**

```bash
docker compose exec app ./vendor/bin/rector process application/models/
```

Rector is configured in [app/rector.php](app/rector.php) with rules for:

- Code quality improvements
- Dead code removal
- Type declarations
- Early returns
- Naming conventions

### Code Statistics and Metrics

Generate code statistics and metrics to understand your codebase.

**Quick statistics with phploc:**

```bash
# Inside the dev container
cd app && ./vendor/bin/phploc application/ library/

# Outside (host), let Docker run it in the app container
docker compose exec app ./vendor/bin/phploc application/ library/
```

**Comprehensive metrics with PHPMetrics:**

```bash
# Inside the dev container
cd app && ./vendor/bin/phpmetrics --report-html=tests/phpmetrics application/ library/

# Outside (host)
docker compose exec app ./vendor/bin/phpmetrics --report-html=tests/phpmetrics application/ library/

# View the report at: app/tests/phpmetrics/index.html
```

PHPMetrics provides:

- Complexity metrics (cyclomatic, maintainability index)
- Object-oriented metrics (coupling, cohesion)
- Visual reports with charts and graphs
- Code violations and code smells detection

### Docker Compose

The `compose.override.yaml` file is automatically loaded by Docker Compose and enables development mode:

- `APPLICATION_ENV=development` - enables Zend Framework development settings
- PHP error display enabled with full error reporting
- Opcache validates file timestamps (picks up code changes immediately)
- Verbose logging

To start in development mode (default):

```bash
docker compose up -d
```

To start in production mode (skip override file):

```bash
docker compose -f compose.yaml up -d
```

To rebuild containers after changes:

```bash
docker compose up -d --build
docker compose exec app composer install
```

**Note:** In development mode, the `app/` directory is mounted as a volume, which overwrites the vendor directory from the container. You must run `composer install` after rebuilding.

To view logs:

```bash
docker compose logs -f
```

### Development vs Production

| Setting | Development | Production |
| --------- | ------------- | ------------ |
| Error display | Shown | Hidden |
| Opcache timestamps | Validated | Disabled |

## Application Overview

HHBD is a content management system for Polish hip-hop music featuring:

- **Music Database**: Artists, albums, songs, and record labels
- **User System**: Registration, login, profiles, comments
- **Community Features**: Lyrics editing, ratings, popularity tracking
- **SEO**: XML sitemaps, Open Graph, friendly URLs (`album-name-a123.html`)
- **Admin Panel**: Backoffice for content management

### Technology Stack

| Component  | Technology                            |
| ---------- | ------------------------------------- |
| Framework  | Zend Framework 1 (shardj/zf1-future)  |
| Language   | PHP 7.4+                              |
| Database   | MariaDB 10.11                         |
| Web Server | Nginx + PHP-FPM                       |
| Admin      | Apache (backoffice)                   |

### Architecture

```text
app/
├── application/
│   ├── configs/         # application.ini, routes.xml
│   ├── controllers/     # MVC controllers (Artist, Album, Song, etc.)
│   ├── models/          # Two-tier: Model_*_Api (data) + Model_*_Container (DTO)
│   ├── views/           # .phtml templates
│   └── layouts/         # Main layout
├── library/Jkl/         # Custom utilities (Db, Og, Tools)
├── public/              # Entry point, CSS, JS, images
└── tests/               # PHPUnit tests
```

## Testing

### Smoke Tests

Smoke tests verify that key pages are accessible and display data from the database.

Run locally (after `docker compose up` and database import):

```bash
./tests/smoke-test.sh
```

Or with a custom URL:

```bash
./tests/smoke-test.sh http://localhost:8080
```

The tests check:

- Homepage loads with content
- Album, Artist, and Label listing pages work
- Detail pages display database data
- Search functionality works

### Unit Tests

Unit tests use PHPUnit to test library classes, models, and view helpers with mocked dependencies.

Run unit tests inside the container:

```bash
docker compose exec app ./vendor/bin/phpunit -c tests/phpunit.xml
```

Run with coverage report:

```bash
docker compose exec app ./vendor/bin/phpunit -c tests/phpunit.xml --coverage-html tests/coverage
```

The tests cover:

- Library classes (`Jkl_Tools_String`, `Jkl_Tools_Date`, `Jkl_Og`, `Jkl_Db`)
- View helpers (`LoggedIn`)
- Model constants and validation logic

### CI/CD

Tests run automatically on GitHub Actions for every push and pull request:

- **Unit Tests**: `.github/workflows/unit-tests.yml` - PHPUnit tests with coverage
- **Smoke Tests**: `.github/workflows/smoke-tests.yml` - Integration tests with Docker
