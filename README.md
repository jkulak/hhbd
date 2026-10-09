# HHBD - Hip-Hop Database

Polish Hip-Hop Database application.

## Project Structure

```bash
hhbd-new/
├── app/                  # Frontend Zend Framework application
├── content/              # User-uploaded images (artists, albums, news)
├── database/             # SQL dumps for database initialization
├── conf/                 # Nginx and PHP configuration
├── deploy/ovh/           # Production on the shared OVH host
├── scripts/              # Database tools, and the OVH host's install, release and secrets scripts
├── tests/                # Smoke test, and the production stack's end-to-end test
└── compose.yaml          # Docker services configuration
```

The old admin panel (`backoffice/`) is archived on the branch `backoffice-archive`; nothing runs it, and it will not be revived: a future backoffice gets written from scratch.

Production runs on the shared OVH host, behind its Caddy edge and Cloudflare, since 2026-10-08. A release is `gh release create vYYYY.MM.N`; see [deploy/ovh/README.md](deploy/ovh/README.md).

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
| adminer | 8082 | Database management |
| db | 3306 | MariaDB database |

## Development

### Local development

There is no PHP on the host: everything runs in the stack's containers.

1. Copy `compose.override.example.yaml` to `compose.override.yaml` (git-ignored). In development mode the app runs the `builder` stage, which has composer, and re-reads the code on every change.
2. `docker compose up -d`, then `docker compose exec app composer install` for `app/vendor` with the dev dependencies.
3. `make reset-db` for the database, and `make hooks` for the git hooks.

**Running the unit tests** (in the importer's image, which has GD; no stack needed):

```bash
make test-unit
```

**Checking and fixing the code style** (php-cs-fixer, PSR-12):

```bash
make cs
make cs-fix
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
docker compose exec app ./vendor/bin/phploc application/ library/
```

**Comprehensive metrics with PHPMetrics:**

```bash
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

### Technology Stack

| Component  | Technology                            |
| ---------- | ------------------------------------- |
| Framework  | Zend Framework 1 (shardj/zf1-future)  |
| Language   | PHP 8.4                               |
| Database   | MariaDB 10.11                         |
| Web Server | Nginx + PHP-FPM                       |

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

Tests run on GitHub Actions for every pull request, and not again on the push a merge makes to `main`:

- **Unit Tests**: `.github/workflows/unit-tests.yml` - PHPUnit tests with coverage
- **Smoke Tests**: `.github/workflows/smoke-tests.yml` - Integration tests with Docker
- **Deploy checks**: `.github/workflows/deploy-checks.yml` - the secrets gate, and the production stack behind a stand-in edge

A published release, `gh release create vYYYY.MM.N`, runs `.github/workflows/deploy.yml`, which builds the app, nginx and importer images, rolls the app and nginx out on the OVH host, and leaves the importer's for `make ovh-import`.
