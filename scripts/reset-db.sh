#!/usr/bin/env bash
#
# Drop the local hhbd database and build it again the way production's is: the schema from the
# migrations, then the test fixtures the smoke test runs on (database/tests/fixtures.sql). Run
# before and after a piece of work, so the database never carries what the last one left in it.
# It takes a second or two, so nothing of it is kept between resets.
#
# It acts on one thing only: the running postgres container of this checkout's compose project,
# on the local Docker engine. Before a single statement reaches a database it refuses a remote
# engine, a project with no running postgres, and one created from another directory or from
# production's compose file (scripts/lib-db.sh). Credentials never pass through here: psql runs
# inside the container, over its socket.
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
database=$(docker exec "$cid" sh -c 'printf %s "${POSTGRES_DB:?}"')

echo "> 1/3 dropping and creating $database in $project"
# WITH (FORCE) ends the app's connections to it. The cluster was made with ICU's pl
# (compose.yaml), and template0 carries it.
printf 'DROP DATABASE IF EXISTS "%s" WITH (FORCE);\nCREATE DATABASE "%s" TEMPLATE template0;\n' "$database" "$database" | container_sql "$cid"

echo "> 2/3 the schema, from the migrations"
MIGRATE_TARGET=container MIGRATE_CONTAINER=$cid ./scripts/migrate.sh up | sed 's/^/  /'

echo "> 3/3 loading $FIXTURES"
# The identities' setval() print their values; nothing else does
container_sql "$cid" db <"$FIXTURES" >/dev/null

tables=$(printf "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'\n" | container_sql "$cid" db)
# Every table's rows in one query, built by a first one: two calls into the container, not one
# per table.
rows=$(printf "SELECT 'SELECT ' || string_agg(format('(SELECT COUNT(*) FROM %%I)', table_name), ' + ') FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'\n" | container_sql "$cid" db | container_sql "$cid" db)
echo "ok $database in $project: $tables tables, $rows rows, at the latest migration, in $(( $(date +%s) - started ))s"
