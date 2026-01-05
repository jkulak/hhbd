# Copilot Instructions: HHBD (Zend Framework 1, PHP 7.4)

Purpose: Make an AI agent immediately productive in this codebase by outlining architecture, workflows, and project-specific patterns.

Big picture
- HHBD (Hip-Hop Database) is a Polish Hip-Hop content management system for music catalog featuring artists, albums, songs, labels, user profiles, comments, ratings, and community features.
- Legacy PHP 7.4 app using Zend Framework 1 (maintained via shardj/zf1-future) served by Nginx + PHP-FPM.
- Services (Docker Compose): app (PHP-FPM), nginx (8080), db (MariaDB 10.11), adminer (8082).
- Frontend app lives in [app/](app/); entrypoint is [app/public/index.php](app/public/index.php). Config in [app/application/configs/application.ini](app/application/configs/application.ini) and routes in [app/application/configs/routes.xml](app/application/configs/routes.xml).
- Backoffice: Admin panel exists in `backoffice/` directory (may be missing in some workspaces) with two sub-apps (`admin/` and `xadmin/`). Uses procedural PHP (not ZF1), connects to same database. Access at http://localhost:8081/admin/
- Frontend assets: jQuery 1.4.4, custom CSS/JS, tipsy tooltips. Main files at [app/public/css/s.css](app/public/css/s.css) and [app/public/js/s.js](app/public/js/s.js).

Development workflow
- Start/rebuild services: `docker compose up -d --build` then `docker compose exec app composer install` (dev volume overrides vendor).
- Seed DB (tests/dev):
  - `docker compose exec -T db mysql -uhhbd -phhbd_password hhbd < database/tests/01-schema.sql`
  - `docker compose exec -T db mysql -uhhbd -phhbd_password hhbd < database/tests/02-test-fixtures.sql`
  - Generate images: `docker compose exec app php app/tools/generate-test-images.php`
- Run unit tests: `docker compose exec app bash -lc 'cd app && ./vendor/bin/phpunit -c tests/phpunit.xml'`.
- Run smoke tests: `./tests/smoke-test.sh` (assumes services up; optional base URL arg).
- View logs: `docker compose logs -f` or `docker compose logs app`.
- Code style check: `app/vendor/bin/php-cs-fixer fix --dry-run --diff` (auto-fix: omit `--dry-run`).

Key conventions and patterns
- MVC with ZF1:
  - Controllers in [app/application/controllers/](app/application/controllers/).
  - Models follow **two-tier Api + Container split** under [app/application/models/](app/application/models/):
    - `Model_*_Api` (singleton, extends `Jkl_Model_Api`) for DB queries/cache (Table Gateway pattern).
    - `Model_*_Container` (DTO) for entity data/behaviors. Constructor accepts raw database params and transforms them.
    - Example structure: `models/Album/Api.php` and `models/Album/Container.php`
  - Views are `.phtml` in [app/application/views/](app/application/views/) with partials; layouts in [app/application/layouts/](app/application/layouts/).
- Custom library `Jkl_*` in [app/library/Jkl/](app/library/Jkl/): `Jkl_Db`, `Jkl_Model_Api` (base class), `Jkl_Og` (Open Graph), `Jkl_Tools_*` (String, Date, Url), `Jkl_List`. Prefer these over ad‑hoc utilities.
- SEO routes: regex-based patterns in routes.xml (e.g., `%s-a%d.html` for albums, `%s-p%d.html` artists, `%s-s%d.html` songs). Parse logic extracts ID from URL suffix before `.html`. When adding endpoints, update routes.xml accordingly.
- Localization: Language files in [app/lang/](app/lang/) (e.g., `en.php`, `pl.php`) return PHP arrays of strings.
- Coding standards: PSR-12 enforced via php-cs-fixer ([.php-cs-fixer.dist.php](.php-cs-fixer.dist.php)). Pre-commit hooks auto-installed via composer (runs linting, checks for `var_dump`/`die`/debugging artifacts).

Configuration and env
- `APPLICATION_ENV` controls environment (production/development/testing); read from container env var, defaults to production.
  - Dev: [compose.override.yaml](compose.override.yaml) sets `development` + mounts dev PHP ini.
  - CI: [compose.ci.yaml](compose.ci.yaml) sets `production` for smoke tests (fidelity over verbose errors).
  - Routes/config: [routes.xml](app/application/configs/routes.xml) has `<testing extends="production">` and `<development extends="production">` sections.
- PHP ini files:
  - [conf/php/production.ini](conf/php/production.ini) copied at build time (errors off, opcache on).
  - [conf/php/development.ini](conf/php/development.ini) mounted in dev override (errors on).
  - Application-level settings in [application.ini](app/application/configs/application.ini) can override via `phpSettings.*`.
- DB and flags via env: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `SHOW_ADS`, mail vars. Paths for content under `app.paths.*` in application.ini.
- Content directories (images) are under [content/](content/); not in git, mounted into containers.

Gotchas (do these)
- **No local dev tools**: There are no tools like php, python, or node installed locally. All development is done inside Docker containers or the VS Code Dev Container.
- **CI bind mount issue**: After `docker compose up` in CI, run `docker compose exec -T app composer install` because bind-mounted `./app` hides the image's vendor/. This is already wired in [.github/workflows/smoke-tests.yml](.github/workflows/smoke-tests.yml).
- After any container rebuild in dev, run `composer install` (volume hides container vendor).
- Target PHP 7.4; avoid PHP 8 features. Maintain ZF1 naming/autoloading (PSR-0, `Jkl_` prefix maps to `library/Jkl/`).
- Polish strings and slugs are common; preserve encoding and diacritics.
- **Legacy security issues**: Password hashing uses MD5 with salt (should migrate to bcrypt); jQuery 1.4.4 is outdated.
- **Authentication**: Uses Zend_Auth with database adapter; simple `usr_is_admin` flag for authorization.

Examples to follow
- Data access: `Model_Artist_Api::getInstance()->find($id)` returns a `Model_Artist_Container`.
- List retrieval: `Model_Album_Api::getInstance()->getList()` returns a `Jkl_List` collection.
- Cached queries via `Jkl_Db` (see [app/library/Jkl/](app/library/Jkl/)).
- Rendering partials in views: `$this->partial('artist/_link.phtml', ['artist' => $artist]);`.
- Open Graph meta tags: Use `Jkl_Og` for generating social media metadata.

Testing and CI
- Unit tests live in [app/tests/unit/](app/tests/unit/) (e.g., `Library/Jkl/DbTest.php`, `ViewHelpers/LoggedInTest.php`). Use the existing bootstrap and config at [app/tests/phpunit.xml](app/tests/phpunit.xml) and [app/tests/bootstrap.php](app/tests/bootstrap.php).
- Smoke tests script at [tests/smoke-test.sh](tests/smoke-test.sh) expects deterministically seeded data from [database/tests/02-test-fixtures.sql](database/tests/02-test-fixtures.sql). Run with `./tests/smoke-test.sh [base-url]`; it waits for HTTP 200 before asserting content.
- GitHub Actions workflows:
  - [.github/workflows/smoke-tests.yml](.github/workflows/smoke-tests.yml): uses `compose.ci.yaml` (production env) + seeds DB + generates test images + runs smoke tests. Tails PHP/app logs on failure.
  - [.github/workflows/unit-tests.yml](.github/workflows/unit-tests.yml): runs PHPUnit inside app container.
- **APPLICATION_ENV in CI**: compose.ci.yaml sets `production` for smoke tests. If debugging CI failures, temporarily flip to `testing` (but routes.xml must have `<testing extends="production">`).
- **Always suggest adding tests** for new features or bug fixes (smoke, unit, or other as appropriate).

When implementing features
- Create/extend `Model_*_Api` and `Model_*_Container` for new entities, reuse `Jkl_` tools, and wire controllers → models → views.
- Add/adjust routes in routes.xml for SEO URL shapes; ensure IDs are extracted consistently with existing patterns.
- Keep images/paths aligned with `app.paths.*` in `application.ini`; generate dev placeholders via the test-images tool.

Deployment (GCP)
- Scripts in [deploy/](deploy/) handle Google Cloud deployment to GCP project `hhbd-483111`.
- VM: e2-micro in us-central1 (1GB RAM, 30GB disk, 4GB swap).
- Workflow: `01-setup-gcp.sh` (one-time), `02-setup-server.sh` (on VM), `03-build-push.sh` (build/push images), `04-deploy.sh` (deploy), `05-populate-db.sh` (import DB), `06-upload-content.sh` (upload content).
- Production compose: [deploy/compose.gcp.yaml](deploy/compose.gcp.yaml).
