#!/usr/bin/env bash
# What the stack needs before `make ovh-stack-test` brings it up: the database alone, migrated
# from scratch and seeded with the test fixtures as make reset-db seeds the local one, so the
# pages the edge is asked for have something to render. scripts/ovh-stack-test.sh runs it with
# compose pointed at the stack under test.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
db="${COMPOSE_PROJECT_NAME:?run by scripts/ovh-stack-test.sh}-db-1"

docker compose up -d --wait --wait-timeout 180 db
migrate() { MIGRATE_TARGET=container MIGRATE_CONTAINER="$db" ./scripts/migrate.sh "$@" >/dev/null; }
# shellcheck disable=SC2016 # expanded inside the container, from its own environment
seed() { docker exec -i "$db" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE"' <"$1"; }
migrate up 0001
seed database/tests/fixtures.sql
migrate up
# The rows for the tables the migrations add.
seed database/tests/fixtures-latest.sql
