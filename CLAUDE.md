# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HHBD is a Polish Hip-Hop Database - a content management system for music catalog featuring artists, albums, songs, labels, user profiles, comments, ratings, and community features. Built with **Zend Framework 1** (shardj/zf1-future) and **PHP 8.4**.

## Repository Structure

- **app/** - Main Zend Framework application (frontend)
- **content/** - User-uploaded images (artists, albums, labels) - NOT in version control
- **database/** - SQL dumps for database initialization
- **conf/nginx/**, **conf/php/** - Nginx and PHP configuration files
- **deploy/ovh/** - Production on the shared OVH host: compose file, edge snippet, encrypted secrets, the release's smoke test, the import script (see [deploy/ovh/README.md](deploy/ovh/README.md))
- **scripts/** - The database tools, and the shared OVH host's scripts: install, release, stack test, the secrets gate and the SOPS helper
- **tests/** - Smoke tests, and the end-to-end test of the production stack

The old admin panel (`backoffice/`) left `main` on 2026-01-04 and lives on the branch `backoffice-archive`; nothing runs it.

## Development Environment

### Docker Services

The project runs in Docker Compose with 4 services (app, nginx, db, adminer):

```bash
# Start all services (development mode with compose.override.yaml)
docker compose up -d

# Rebuild containers after code changes
docker compose up -d --build
docker compose exec app composer install  # Required after rebuild in dev mode

# View logs
docker compose logs -f

# Stop services
docker compose down
```

**Service ports:**
- Frontend: http://localhost:8080
- Adminer (DB): http://localhost:8082
- MariaDB: localhost:3306

### Dev Container (Recommended)

VS Code Dev Container provides PHP 8.4, from Debian 13's packages as the images have it, with Xdebug and Composer. Opens automatically in VS Code with Dev Containers extension.

### First-Time Setup

After starting Docker services:

```bash
# The database: the schema from database/migrations/, the smoke-test fixtures on top
make reset-db

# Install dependencies
docker compose exec app composer install
```

For production-like data instead, load a dump and record the baseline with `make migrate-baseline`; see [database/README.md](database/README.md).

### The database

- `make reset-db` drops the local `hhbd` database and builds it again from the migrations and the fixtures, or loads the kept result of that build while those files have not changed (`RESET_DB_FULL=1` always builds). Run it before and after a piece of work. It acts only on the running db container of this checkout's compose project on the local Docker engine, and refuses anything else.
- Every schema or data change is a migration in `database/migrations/`, `NNNN-slug.up.sql` with its `.down.sql`: `make migrate-new NAME=...`, `make migrate`, `make migrate-down`, `make migrate-status`. Keep each one small and compatible with the release that is running. Production gets them with `make ovh-migrate` before the release that needs them ([deploy/ovh/README.md](deploy/ovh/README.md)). Details in [database/README.md](database/README.md).

## Common Commands

### Testing

```bash
# Run unit tests (in dev container or app service)
cd app && ./vendor/bin/phpunit -c tests/phpunit.xml

# Run unit tests with coverage report
cd app && ./vendor/bin/phpunit -c tests/phpunit.xml --coverage-html tests/coverage

# Run smoke tests (integration tests - requires running services)
./tests/smoke-test.sh
./tests/smoke-test.sh http://localhost:8080  # custom URL
```

Unit tests cover library classes (`Jkl_*`), view helpers, and model logic with mocked dependencies. Smoke tests verify key pages load with database data.

### Code Style

```bash
# Check code style (PSR-12) - from repo root
app/vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix code style issues
app/vendor/bin/php-cs-fixer fix

# Check specific files
app/vendor/bin/php-cs-fixer fix --dry-run application/controllers/AlbumController.php
```

Configuration: [.php-cs-fixer.dist.php](.php-cs-fixer.dist.php) - uses PSR-12 standard.

### Pre-commit Hooks

Git hooks are automatically installed via `composer install` (see [app/tools/setup-hooks.php](app/tools/setup-hooks.php)):

- **pre-commit**: Runs PHP-CS-Fixer, PHP linting, checks for debugging artifacts (`var_dump`, `print_r`, `die`, etc.)

Hooks are in [app/hooks/](app/hooks/) and copied to `.git/hooks/` during setup.

## Architecture

### MVC Structure

```
app/
├── application/
│   ├── configs/
│   │   ├── application.ini      # Zend config (db, mail, paths, feature flags)
│   │   └── routes.xml           # Custom routes (e.g., "album-name-a123.html")
│   ├── controllers/             # AlbumController, ArtistController, SongController, etc.
│   ├── models/                  # Two-tier model architecture (see below)
│   ├── views/                   # .phtml templates
│   └── layouts/                 # Main layout template
├── library/Jkl/                 # Custom utilities (see below)
├── public/                      # Entry point (index.php), CSS, JS, static images
└── tests/                       # PHPUnit tests (unit/, bootstrap.php, phpunit.xml)
```

### Two-Tier Model Architecture

Models use a **data access layer (Api) + data transfer object (Container)** pattern:

- **`Model_*_Api`** - Singleton data access layer, extends `Jkl_Model_Api`, handles database queries
  - Example: `Model_Album_Api::getInstance()->find($id)` returns a `Model_Album_Container`
  - Methods: `find()`, `getList()`, custom queries

- **`Model_*_Container`** - Data transfer object, holds entity properties
  - Example: `Model_Album_Container` has properties like `$id`, `$title`, `$artist`, `$releaseDate`
  - Constructor accepts raw database params and transforms them into usable objects

**Example structure:**
```
models/
├── Album/
│   ├── Api.php        # Model_Album_Api - database access
│   └── Container.php  # Model_Album_Container - DTO
├── Artist/
│   ├── Api.php
│   └── Container.php
└── User.php           # Some models are single-file (legacy)
```

When writing new code that follows existing patterns, use the Api/Container split for consistency.

### Custom Library (Jkl_*)

Located in `app/library/Jkl/`, autoloaded via PSR-0 (`Jkl_` prefix):

- **Jkl_Model_Api** - Base class for all `*_Api` models, provides `$_db` access
- **Jkl_Db** - Database utilities
- **Jkl_Og** - Open Graph meta tag generator
- **Jkl_Tools_String** - String utilities (e.g., ASCII conversion for URLs)
- **Jkl_Tools_Date** - Date formatting utilities
- **Jkl_Tools_Url** - URL utilities
- **Jkl_List** - Collection class used by `*_Api::getList()` methods

### Routing

Custom routes defined in [app/application/configs/routes.xml](app/application/configs/routes.xml). Uses SEO-friendly URLs:
- Albums: `album-name-a123.html` (where 123 is album ID)
- Artists: `artist-name-x456.html` (where 456 is artist ID)
- Songs: `song-name-s789.html` (where 789 is song ID)

Parse logic extracts ID from URL suffix before `.html`.

The addresses before these (`/n/peja`, `/a/...`, `/l/...`, `/s/...`, `/wykonawca/...`, `/news/223`) redirect to today's pages through `LegacyController`, by the slug kept in each table's `urlname` (#26). They stay for good, and the old links in the database's texts are left as they are (#24): other sites link the old addresses too.

### Configuration

**application.ini sections:**
- `[production]` - base config
- `[development : production]` - extends production (enabled via `APPLICATION_ENV=development`)

**Environment variables** (set in compose.yaml/compose.override.yaml):
- Database: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`
- Mail: `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`
- Feature flags: `SHOW_ADS` (true/false)

### Backoffice

There is none on `main`. The procedural PHP admin panels (`admin/` and `xadmin/`) that once added artists, albums, covers and photos are archived on the branch `backoffice-archive`. Production has no admin panel; its database is reached over ssh (see [deploy/ovh/README.md](deploy/ovh/README.md)).

The archived panels are abandoned for good (decided 2026-10-09): they are not updated, not revived, and schema or code changes do not have to stay compatible with them. Their code is a historical record only, for what a column once meant. If hhbd needs a backoffice again, it gets written from scratch.

## Development vs Production

In **development mode** (`compose.override.yaml` loaded automatically; `compose.override.example.yaml` is the one to copy):
- PHP errors displayed with full reporting
- Opcache validates file timestamps (picks up changes immediately)
- `app/` directory mounted as volume (overwrites vendor/ - requires `composer install` after rebuild)

In **production mode** (use `-f compose.yaml` only):
- Errors hidden
- Opcache disabled timestamp validation
- Vendor directory baked into container

Production itself runs `deploy/ovh/compose.yaml` on the shared OVH host, behind its Caddy edge and Cloudflare, since 2026-10-08. A release is `gh release create vYYYY.MM.N`, run by a person; [deploy/ovh/README.md](deploy/ovh/README.md) has the rest.

## Code Style Conventions

- **PSR-12** standard enforced via PHP-CS-Fixer
- Class names follow Zend Framework 1 conventions:
  - Controllers: `AlbumController`, `ArtistController`
  - Models: `Model_Album_Api`, `Model_Album_Container`
  - Library: `Jkl_Tools_String`, `Jkl_Db`
- File paths match class names (e.g., `Jkl_Tools_String` → `library/Jkl/Tools/String.php`)

## CI/CD

GitHub Actions workflows (`.github/workflows/`):
- **unit-tests.yml** - PHPUnit tests with coverage
- **smoke-tests.yml** - Integration tests with Docker, in two halves at once on two runners: the site's pages, and the tools that change the database
- **deploy-checks.yml** - The secrets gate, and the production stack behind a stand-in edge
- **deploy.yml** - For a published release, builds the app, nginx and importer images and rolls the first two out on the OVH host; the shared host's template, word for word

The first three run on pull requests (and by hand), never again on the push a merge makes to `main`; a newer push to a pull request cancels the older run. `deploy.yml` runs on a published release, `gh release create vYYYY.MM.N`.

## Important Notes

- **Legacy codebase**: Uses Zend Framework 1 (EOL but maintained by shardj/zf1-future)
- **PHP 8.4**: Debian 13's own packages in every image (`Dockerfile-php`): nothing is compiled, a rebuild takes Debian's security updates, and both Debian 13 and PHP 8.4 are supported to 2028. shardj/zf1-future runs on it. Classes that fill properties they do not declare (the model containers, the controllers' `$params`) carry `#[\AllowDynamicProperties]` (#37)
- **Database**: MariaDB 10.11, credentials in environment variables
- **Content directory**: User uploads stored in `content/` - excluded from git, shared across services via Docker volumes
- **Polish language**: Many UI strings, comments, and routes are in Polish

## AI Agent Guidelines

### When to Access External URLs

**Context**: AI coding agents may have access to tools for fetching external web content. Use these tools judiciously.

**VALID use cases** (when external documentation is genuinely needed):
- Official API documentation for external services (e.g., Google Cloud Platform, Docker Registry)
- Researching unfamiliar technologies or tools not documented in the repository
- External library documentation when repository lacks inline docs

**INVALID use cases** (work with local repository content instead):
- Making changes to deployment scripts, configs, or CI/CD workflows
- Implementing features using technologies already present in the codebase (PHP, Bash, Docker, ZF1)
- Looking up common patterns or well-known tools
- General "checking" of documentation when approach is already known

**Best practices**:
- ALWAYS try repository files and existing patterns first (use `grep`, `view`, file browsing)
- Study similar files to follow established patterns (e.g., look at other deployment scripts)
- Only fetch external docs for genuinely unfamiliar concepts
- If external access is blocked, reassess whether it's actually needed - most work should be self-contained
