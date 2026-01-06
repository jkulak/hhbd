# HHBD Project Context

## Project Overview
**HHBD (Hip-Hop Baza Danych)** is a legacy-modernized PHP application serving as a comprehensive database for Polish hip-hop music. It handles artists, albums, songs, labels, and community features.

The project is built on **Zend Framework 1** (specifically the `shardj/zf1-future` fork for PHP 8 compatibility) and runs in a **Dockerized** environment with Nginx, PHP-FPM, and MariaDB.

**Note:** The documentation references a `backoffice` component (Admin Panel), but the directory is currently missing from this workspace.

## Technology Stack
*   **Language:** PHP 7.4+ (Targeting PHP 8.x compatibility)
*   **Framework:** Zend Framework 1 (`shardj/zf1-future`)
*   **Database:** MariaDB 10.11
*   **Frontend:** Legacy jQuery (1.4.4), Custom CSS/JS, `tipsy`
*   **Infrastructure:** Docker Compose (Nginx, PHP-FPM, MariaDB, Adminer)
*   **Testing:** PHPUnit 9.6, Custom Smoke Tests (Bash)

## Architecture
The application follows the standard Model-View-Controller (MVC) pattern of Zend Framework 1.

### Directory Structure
*   **`app/`**: Main application source.
    *   **`application/`**: ZF1 Application code.
        *   `controllers/`: Action controllers (e.g., `AlbumController`, `ArtistController`).
        *   `models/`: Domain logic.
            *   **Pattern:** Uses a 2-tier model system:
                *   `Model_Name_Api`: Data access and logic (Table Gateway pattern).
                *   `Model_Name_Container`: Data Transfer Objects (DTOs) / Entities.
        *   `views/`: `.phtml` templates.
        *   `configs/`: `application.ini` (Main config), routing, navigation.
    *   **`library/Jkl/`**: Custom library code (`Jkl_Db`, `Jkl_Tools`, etc.).
    *   **`public/`**: Web root (CSS, JS, Images, `index.php`).
    *   **`tests/`**: Unit tests directory.
*   **`conf/`**: Configuration for Nginx (`nginx/`) and PHP (`php/`).
*   **`content/`**: User-uploaded media (Artists, Albums, etc.).
*   **`deploy/`**: Deployment scripts.
*   **`tests/`**: Root-level integration/smoke tests.

## Development Workflow

### Prerequisites
*   Docker & Docker Compose

### Building and Running
1.  **Start Services:**
    ```bash
    docker compose up -d
    ```
    *   Frontend: `http://localhost:8080`
    *   Adminer (DB): `http://localhost:8082`

2.  **Install Dependencies:**
    ```bash
    docker compose exec app composer install
    ```

3.  **Database Setup:**
    (If not automatically handled by entrypoint)
    ```bash
    # See README.md for specific dump import instructions
    ```

### Testing
*   **Unit Tests (PHPUnit):**
    ```bash
    docker compose exec app ./vendor/bin/phpunit -c tests/phpunit.xml
    ```
*   **Smoke Tests:**
    ```bash
    ./tests/smoke-test.sh http://localhost:8080
    ```

### Coding Standards & Conventions
*   **Style:** PSR-12 (enforced via `php-cs-fixer` and `phpcs`).
*   **Configuration:**
    *   Uses `application.ini` for application-level config.
    *   Sensitive credentials and environment-specific settings are injected via **Environment Variables** (`DB_HOST`, `DB_USER`, `SHOW_ADS`).
*   **Logging:** Application logs to `/var/log/hhbd-website/` inside the container.

## Key Configuration Files
*   `app/application/configs/application.ini`: Main ZF1 configuration.
*   `compose.yaml`: Docker services definition.
*   `app/composer.json`: PHP dependencies.

## AI Agent Guidelines

### When to Access External Web Resources

**Context**: AI coding agents may have tools to fetch external web content. Use these sparingly and only when necessary.

**VALID use cases**:
- Official API documentation for external services (Google Cloud, Docker Registry, etc.)
- Researching genuinely unfamiliar technologies not documented in repository
- External library documentation when not available locally

**INVALID use cases** (work with repository files instead):
- Making changes to scripts, configs, or workflows already in the repository
- Implementing features using technologies present in the codebase (PHP, Bash, Docker, ZF1)
- Looking up common tools or patterns

**Best practices**:
- Start by exploring repository files using search, grep, and file browsing
- Follow patterns from similar existing files
- Only fetch external docs for truly unfamiliar concepts
- If external access is blocked, work should still be possible using repository context
