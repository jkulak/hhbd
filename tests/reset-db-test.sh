#!/usr/bin/env bash
#
# make reset-db against the running local stack, as the smoke workflow has it up.
#
# What it proves:
#   - a reset undoes changed rows and drops a table that does not belong to the fixtures
#   - two resets in a row give the same exact row count in every table
#   - a reset builds the database from the migrations once and keeps the result, the next one
#     loads it, and the two give the same tables, columns, indexes and rows, checksum for
#     checksum; RESET_DB_FULL=1 builds it again, and a build drops a result it no longer matches
#   - it refuses a Docker engine that is not local, a project with no running db, and a db
#     container of the same project name started from another directory, and in each case
#     leaves the database alone
#   - and of the project's own database container, it refuses one that is stopped, one created
#     from another directory and one created with production's compose file
#
# DB_ENGINE=postgresql runs it on PostgreSQL, on the stack compose.postgresql.yaml makes (#234),
# where a reset is MariaDB's and then PostgreSQL's database built by its migration and MariaDB's
# rows copied in. Nothing is kept there: instead of the kept result against a build, two builds
# are compared, and each must say the copy was proved. The refusals that come before MariaDB's
# reset hold there too; those of the project's own container are the postgres service's.
#
# The database ends as a reset leaves it. The other project it starts for a refusal, one busybox
# container, is removed on the way out, with its directory, and so is the stand-in it puts in
# place of the project's own database container, which is started again.
#
# The smoke test after a reset is the smoke workflow's own step, not this test's.
#
# Usage: tests/reset-db-test.sh
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

if is_pg; then SERVICE=postgres SCHEMA='current_schema()'; else SERVICE=db SCHEMA='DATABASE()'; fi
OTHER=hhbd-resetdb-elsewhere
PROJECT=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
STAND_IN=$PROJECT-resetdb-stand-in
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-resetdb.XXXXXX")
stopped=""
pass=0
fail=0

cleanup() {
    [ -f "$T/compose.yaml" ] && docker compose -p "$OTHER" -f "$T/compose.yaml" down -t 0 >/dev/null 2>&1
    docker rm -f "$STAND_IN" >/dev/null 2>&1 || true
    [ -z "$stopped" ] || docker compose up -d --no-deps --no-recreate --wait "$SERVICE" >/dev/null 2>&1 || true
    rm -rf "$T"
}
trap cleanup EXIT

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

# for_each_table <statement, {t} for the table's name>: that statement for every table, in one
# call rather than one per table.
for_each_table() {
    sql "SELECT REPLACE('$(printf '%s' "$1" | sed "s/'/''/g")', '{t}', table_name) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_type = 'BASE TABLE' ORDER BY table_name" | sql_in
}
# Exact count(*) of every table, one "table<TAB>rows" line each.
counts() { for_each_table "SELECT '{t}', COUNT(*) FROM {t};"; }
# Each table's definition and checksum: what a database built from the migrations and one loaded
# from the kept result must agree on, down to the auto-increment counters. PostgreSQL's is put
# together from its catalogue: the columns, the indexes, the identities' next values, and each
# table's rows hashed in one order, but for the time each migration was applied, which every
# build records afresh.
state() {
    if is_pg; then
        sql "SELECT table_name, column_name, data_type, column_default, is_nullable, is_identity, collation_name FROM information_schema.columns WHERE table_schema = current_schema() ORDER BY table_name, ordinal_position"
        sql "SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() ORDER BY indexname"
        sql "SELECT sequencename, last_value FROM pg_sequences WHERE schemaname = current_schema() ORDER BY sequencename"
        sql "SELECT version, name FROM schema_migrations ORDER BY version"
        for_each_table "SELECT '{t}', md5(COALESCE(string_agg(CAST(r AS text), chr(10) ORDER BY CAST(r AS text)), '')) FROM {t} r;" | grep -v '^schema_migrations'
    else
        # shellcheck disable=SC2016
        for_each_table 'SHOW CREATE TABLE `{t}`; CHECKSUM TABLE `{t}` EXTENDED;'
    fi
}
kept() { docker compose exec -T db sh -c 'ls /tmp/hhbd-reset-*.sql 2>/dev/null' | tr -d '\r'; }
has_table() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_name = '$1'"; }
app_tables() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'"; }
# The change by hand a reset must undo: the last album deleted, a table no migration makes
change() { sql "DELETE FROM albums WHERE id = (SELECT MAX(id) FROM albums); CREATE TABLE stray_after_reset (id int)"; }
reset() { make -s reset-db >"$T/out" 2>&1; }

echo "> a reset, then the database changed by hand"
if reset; then ok "make reset-db succeeds ($(tail -1 "$T/out" | sed 's/^ok //'))"; else bad "make reset-db succeeds"; cat "$T/out"; exit 1; fi
first=$(counts)
change
check "the change shows before the next reset" "1" "$(has_table stray_after_reset)"

echo "> two resets in a row"
second=""; third=""
reset && second=$(counts)
reset && third=$(counts)
check "the first reset after the change brings every table back to the same count" "$first" "$second"
check "a second reset in a row gives the same count in every table" "$second" "$third"
check "a table that is not in the fixtures is gone" "0" "$(has_table stray_after_reset)"
check "the fixtures' albums are there" \
    "$(printf '%s\n' "$first" | awk -F'\t' '$1 == "albums" { print $2 }')" "$(sql "SELECT COUNT(*) FROM albums")"

if is_pg; then
    echo "> built from the migration and copied from MariaDB, the same each time"
    if reset; then ok "make reset-db succeeds"; else bad "make reset-db succeeds"; cat "$T/out"; exit 1; fi
    check "it copies MariaDB's rows into every table and proves the copy before it commits" \
        "copied and verified $(app_tables) tables" "$(grep -oE 'copied and verified [0-9]+ tables' "$T/out")"
    check "every one of them the same on both sides" "every one the same on both sides" "$(grep -o 'every one the same on both sides' "$T/out")"
    built=$(state)
    change
    reset || cat "$T/out"
    check "the next reset builds and copies it again" "copied from MariaDB" "$(grep -o 'copied from MariaDB' "$T/out")"
    check "which gives every table the definition, the identities and the checksum the last one gave it" "$built" "$(state)"
else
    echo "> built from the migrations, then loaded from the kept result"
    # A result left by older files, which the next build must not leave behind.
    docker compose exec -T db sh -c 'echo "-- stale" > /tmp/hhbd-reset-0000000000000000.sql'
    if RESET_DB_FULL=1 reset; then ok "RESET_DB_FULL=1 make reset-db succeeds"; else bad "RESET_DB_FULL=1 make reset-db succeeds"; cat "$T/out"; exit 1; fi
    check "RESET_DB_FULL=1 builds it from the migrations" "built from the migrations" "$(grep -o 'built from the migrations' "$T/out")"
    check "and keeps that one result, the older one gone" "1 kept, 0 stale" "$(kept | grep -c .) kept, $(kept | grep -c 0000000000000000) stale"
    built=$(state)
    change
    reset || cat "$T/out"
    check "the next reset loads the kept result" "from the kept result" "$(grep -o 'from the kept result' "$T/out")"
    check "which gives every table the definition and the checksum the build gave it" "$built" "$(state)"
fi
check "and the row count" "$first" "$(counts)"
check "a table that is not in the fixtures is gone again" "0" "$(has_table stray_after_reset)"

echo "> what it refuses, leaving a marker table where it was"
sql "CREATE TABLE marker_untouched (id int)"

if DOCKER_HOST=ssh://nobody@example.invalid reset; then bad "a Docker engine that is not local is refused"; else
    check "a Docker engine that is not local is refused" "1 local socket" "$(has_table marker_untouched) $(grep -o 'local socket' "$T/out")"; fi

if COMPOSE_PROJECT_NAME=hhbd-resetdb-none reset; then bad "a project with no running db is refused"; else
    check "a project with no running db is refused" "1 no running db" "$(has_table marker_untouched) $(grep -o 'no running db' "$T/out")"; fi

# The same project name, its db started from another directory: what a second checkout using
# this one's project name would reach.
printf 'services:\n  db:\n    image: busybox:1.37.0\n    command: sleep 300\n' >"$T/compose.yaml"
docker compose -p "$OTHER" -f "$T/compose.yaml" up -d >/dev/null 2>&1
if COMPOSE_PROJECT_NAME=$OTHER reset; then bad "a db started from another directory is refused"; else
    check "a db started from another directory is refused" "1 not from this checkout" "$(has_table marker_untouched) $(grep -o 'not from this checkout' "$T/out")"; fi

echo "> what it refuses of this project's own $SERVICE container"
# The container stopped, and then a stand-in in its place: a busybox carrying every label compose
# gave the container, which is how compose knows its own, but for the directory and the compose
# files, as if it had been created from another checkout or with production's compose file. On
# PostgreSQL, MariaDB's reset goes through first each time; PostgreSQL's is refused.
own=$(docker compose ps -q "$SERVICE")
stand_in() { # stand_in <the directory it was created from> <the compose files it was created with>
    local labels=() label
    # shellcheck disable=SC2016 # a Go template, for docker inspect
    while IFS= read -r label; do
        case "$label" in
            com.docker.compose.project.working_dir=* | com.docker.compose.project.config_files=* | '') ;;
            *) labels+=(--label "$label") ;;
        esac
    done <<<"$(docker inspect --format '{{range $k, $v := .Config.Labels}}{{$k}}={{$v}}{{"\n"}}{{end}}' "$own")"
    docker rm -f "$STAND_IN" >/dev/null 2>&1 || true
    docker run -d --name "$STAND_IN" "${labels[@]}" --label com.docker.compose.project.working_dir="$1" \
        --label com.docker.compose.project.config_files="$2" busybox:1.37.0 sleep 300 >/dev/null
}
stopped=1
docker compose stop "$SERVICE" >/dev/null 2>&1
if reset; then bad "a stopped $SERVICE container is refused"; else
    check "a stopped $SERVICE container is refused" "no running $SERVICE container" "$(grep -o "no running $SERVICE container" "$T/out")"; fi
stand_in "$T" "$T/compose.yaml"
if reset; then bad "one created from another directory is refused"; else
    check "one created from another directory is refused" "created from $T, not from this checkout" "$(grep -o "created from $T, not from this checkout" "$T/out")"; fi
stand_in "$PWD" "$PWD/compose.yaml,$PWD/deploy/ovh/compose.yaml"
if reset; then bad "one created with production's compose file is refused"; else
    check "one created with production's compose file is refused" "runs production's compose file" "$(grep -o "runs production's compose file" "$T/out")"; fi
docker rm -f "$STAND_IN" >/dev/null
docker compose up -d --no-deps --no-recreate --wait "$SERVICE" >/dev/null 2>&1
stopped=""
check "and the database, started again, still has the marker" "1" "$(has_table marker_untouched)"

reset && check "and a last reset leaves the database as the fixtures have it" "$first" "$(counts)"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
