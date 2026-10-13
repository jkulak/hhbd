#!/usr/bin/env bash
# What the stack needs before `make ovh-stack-test` brings it up: the database alone, migrated
# from scratch and loaded with the test fixtures as make reset-db loads the local one, so the
# pages the edge is asked for have something to render. scripts/ovh-stack-test.sh runs it with
# compose pointed at the stack under test.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
db="${COMPOSE_PROJECT_NAME:?run by scripts/ovh-stack-test.sh}-postgres-1"

docker compose up -d --wait --wait-timeout 180 postgres
MIGRATE_TARGET=container MIGRATE_CONTAINER="$db" ./scripts/migrate.sh up >/dev/null
# shellcheck disable=SC2016 # expanded inside the container, from its own environment
docker exec -i "$db" sh -c 'exec psql -X -q -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' <database/tests/fixtures.sql >/dev/null
