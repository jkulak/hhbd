#!/usr/bin/env bash
#
# Drop the local hhbd database and build it again the way production's is: the schema from the
# migrations, the test fixtures the smoke test runs on loaded onto the baseline, and every
# later migration run over that data. Run before and after a piece of work, so the database
# never carries what the last one left in it.
#
# It acts on one thing only: the running db container of this checkout's compose project, on
# the local Docker engine. Before a single statement reaches a database it refuses a remote
# engine, a project with no running db, and a db created from another directory or from
# production's compose file (scripts/lib-db.sh). Credentials never pass through here: the
# commands run inside the container and take the root password from its own environment.
#
# Usage: make reset-db      (with the local stack up: docker compose up -d)
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=scripts/lib-db.sh
. scripts/lib-db.sh
REFUSE_AS=reset-db

FIXTURES=database/tests/fixtures.sql
started=$(date +%s)

cid=$(local_db_container) || exit 1
project=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
database=$(docker exec "$cid" sh -c 'printf %s "${MYSQL_DATABASE:?}"')
migrate() { MIGRATE_TARGET=container MIGRATE_CONTAINER=$cid ./scripts/migrate.sh "$@" | sed 's/^/  /'; }

echo "> 1/4 dropping and creating $database in $project"
# The hhbd user's grants are on hhbd.*, not on the database object, so they survive the drop.
# shellcheck disable=SC2016
printf 'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s`\n' "$database" "$database" | container_sql "$cid"

echo "> 2/4 the baseline schema, from the migrations"
migrate up 0001

echo "> 3/4 loading $FIXTURES"
container_sql "$cid" db <"$FIXTURES"

echo "> 4/4 the migrations after the baseline, over that data"
migrate up

tables=$(printf 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = "BASE TABLE" AND table_name <> "schema_migrations"\n' | container_sql "$cid" db)
rows=0
for t in $(printf 'SHOW FULL TABLES WHERE Table_type = "BASE TABLE"\n' | container_sql "$cid" db | cut -f1 | grep -vx schema_migrations); do
    # shellcheck disable=SC2016
    rows=$((rows + $(printf 'SELECT COUNT(*) FROM `%s`\n' "$t" | container_sql "$cid" db)))
done
echo "ok $database in $project: $tables tables, $rows rows, at the latest migration, in $(( $(date +%s) - started ))s"
