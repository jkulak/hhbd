# HHBD - Hip-Hop Database

Polish Hip-Hop Database application.

## Project Structure

```
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
   - Frontend: http://localhost:8080
   - Backoffice: http://localhost:8081/admin/
   - Adminer (DB): http://localhost:8082

5. Stop services:
   ```bash
   docker compose down
   ```

## Services

| Service | Port | Description |
|---------|------|-------------|
| nginx | 8080 | Frontend web server |
| app | 9000 | PHP-FPM application server |
| backoffice | 8081 | Backoffice admin panel (Apache) |
| adminer | 8082 | Database management |
| db | 3306 | MySQL 8.0 database |
| memcached | 11211 | Cache server |

## Development

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
|---------|-------------|------------|
| Error display | Shown | Hidden |
| Cache lifetime | 1800s | 3600s |
| Opcache timestamps | Validated | Disabled |
| FirePHP | Enabled | Disabled |
