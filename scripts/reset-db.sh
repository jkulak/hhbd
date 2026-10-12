#!/usr/bin/env bash
#
# Drop the local hhbd database and build it again the way production's is: the schema from the
# migrations, the test fixtures the smoke test runs on loaded onto the baseline, every later
# migration run over that data, and then the fixtures for the tables those migrations created.
# Run before and after a piece of work, so the database never carries what the last one left in
# it.
#
# The result is kept as a dump inside the db container, under a key made of everything that
# decides it: the migrations, the fixtures, and the scripts that run them. A later reset with
# the same key loads that dump, a second's work, instead of running thirty migrations over the
# fixtures again, and gets the same tables and rows, as a restore of a backup would (database/
# README.md says where MariaDB tells the two apart); a change to any of those files makes
# a new key, and the next reset builds it from scratch. The day is part of the key too, so the
# rows that take their date from the clock are never older than today, as a fresh build's are.
# RESET_DB_FULL=1 always builds it.
#
# It acts on one thing only: the running db container of this checkout's compose project, on
# the local Docker engine. Before a single statement reaches a database it refuses a remote
# engine, a project with no running db, and a db created from another directory or from
# production's compose file (scripts/lib-db.sh). Credentials never pass through here: the
# commands run inside the container and take the root password from its own environment.
#
# Usage: make reset-db      (with the local stack up: docker compose up -d)
#
# DB_ENGINE=postgresql (#232) builds the PostgreSQL database too, on the way to it (#229): the
# MariaDB one as above first, then PostgreSQL's dropped and made again by its migrations, and
# the MariaDB one copied into it by app/tools/mariadb-to-postgres.php, which proves the copy.
# The fixtures stay MariaDB's until MariaDB goes, so every reset tests the copy as well.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=scripts/lib-db.sh
. scripts/lib-db.sh
REFUSE_AS=reset-db

if [ "$DB_ENGINE" = postgresql ]; then
    started=$(date +%s)
    DB_ENGINE=mariadb ./scripts/reset-db.sh
    cid=$(local_db_container) || exit 1
    project=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
    database=$(docker exec "$cid" sh -c 'printf %s "${POSTGRES_DB:?}"')
    echo "> 1/3 dropping and creating $database in $project's postgres"
    # The cluster was made with ICU's pl (compose.postgresql.yaml), and template0 carries it.
    printf 'DROP DATABASE IF EXISTS "%s" WITH (FORCE);\nCREATE DATABASE "%s" TEMPLATE template0;\n' "$database" "$database" | container_sql "$cid"
    echo "> 2/3 the schema, from the migrations"
    MIGRATE_TARGET=container MIGRATE_CONTAINER=$cid ./scripts/migrate.sh up | sed 's/^/  /'
    echo "> 3/3 MariaDB's rows, copied and verified"
    # A line per table goes unprinted unless the copy fails, when every line counts.
    if ! copied=$(docker compose exec -T app php /var/www/html/app/tools/mariadb-to-postgres.php copy 2>&1); then
        printf '%s\n' "$copied" | sed 's/^/  /' >&2
        refuse "the copy into PostgreSQL failed"
    fi
    printf '%s\n' "$copied" | tail -1 | sed 's/^/  /'
    tables=$(printf "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'\n" | container_sql "$cid" db)
    rows=$(printf "SELECT 'SELECT ' || string_agg(format('(SELECT COUNT(*) FROM %%I)', table_name), ' + ') FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'\n" | container_sql "$cid" db | container_sql "$cid" db)
    echo "ok $database in $project's postgres: $tables tables, $rows rows, copied from MariaDB, in $(( $(date +%s) - started ))s"
    exit 0
fi

FIXTURES=database/tests/fixtures.sql
# Rows for tables the migrations add (external ids, provenance, ...), which the baseline-era
# fixtures cannot hold.
LATEST_FIXTURES=database/tests/fixtures-latest.sql
started=$(date +%s)
key=$({ date -u +%F; cat database/migrations/*.sql "$FIXTURES" "$LATEST_FIXTURES" scripts/migrate.sh scripts/lib-db.sh scripts/reset-db.sh; } | shasum -a 256 | cut -c1-16)
snapshot=/tmp/hhbd-reset-$key.sql

cid=$(local_db_container) || exit 1
project=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
database=$(docker exec "$cid" sh -c 'printf %s "${MYSQL_DATABASE:?}"')
migrate() { MIGRATE_TARGET=container MIGRATE_CONTAINER=$cid ./scripts/migrate.sh "$@" | sed 's/^/  /'; }

# The hhbd user's grants are on hhbd.*, not on the database object, so they survive the drop.
# shellcheck disable=SC2016
recreate() { printf 'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s`\n' "$database" "$database" | container_sql "$cid"; }

if [ "${RESET_DB_FULL:-}" != 1 ] && docker exec "$cid" test -s "$snapshot"; then
    echo "> 1/2 dropping and creating $database in $project"
    recreate
    echo "> 2/2 loading the migrations' and fixtures' result kept as $snapshot"
    # shellcheck disable=SC2016
    docker exec "$cid" sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}" < "$1"' -- "$snapshot"
    how="from the kept result"
else
    echo "> 1/5 dropping and creating $database in $project"
    recreate

    echo "> 2/5 the baseline schema, from the migrations"
    migrate up 0001

    echo "> 3/5 loading $FIXTURES"
    container_sql "$cid" db <"$FIXTURES"

    echo "> 4/5 the migrations after the baseline, over that data"
    migrate up

    echo "> 5/5 loading $LATEST_FIXTURES"
    container_sql "$cid" db <"$LATEST_FIXTURES"

    # Kept for the next reset; an older result goes, as its files or its day are not today's.
    # shellcheck disable=SC2016
    docker exec "$cid" sh -c 'rm -f /tmp/hhbd-reset-*.sql && MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb-dump -uroot --default-character-set=utf8mb4 --single-transaction --hex-blob --skip-dump-date "${MYSQL_DATABASE:?}" > "$1"' -- "$snapshot"
    how="built from the migrations"
fi

tables=$(printf 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE" AND table_name <> "schema_migrations"\n' | container_sql "$cid" db)
# Every table's rows in one query, built by a first one: two calls into the container, not one
# per table.
# shellcheck disable=SC2016
rows=$(printf 'SELECT CONCAT("SELECT ", GROUP_CONCAT(CONCAT("(SELECT COUNT(*) FROM `", table_name, "`)") SEPARATOR " + ")) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE" AND table_name <> "schema_migrations"\n' | container_sql "$cid" db | container_sql "$cid" db)
echo "ok $database in $project: $tables tables, $rows rows, at the latest migration, $how, in $(( $(date +%s) - started ))s"
