#!/usr/bin/env bash
#
# make reset-db against the running local stack, as the smoke workflow has it up.
#
# What it proves:
#   - a reset undoes changed rows and drops a table that does not belong to the fixtures
#   - two resets in a row give the same exact row count in every table
#   - the smoke test passes right after a reset
#   - it refuses a Docker engine that is not local, a project with no running db, and a db
#     container of the same project name started from another directory, and in each case
#     leaves the database alone
#
# The database ends as a reset leaves it. The other project it starts for the last refusal, one
# busybox container, is removed on the way out, with its directory.
#
# Usage: tests/reset-db-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
OTHER=hhbd-resetdb-elsewhere
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-resetdb.XXXXXX")
pass=0
fail=0

cleanup() {
    [ -f "$T/compose.yaml" ] && docker compose -p "$OTHER" -f "$T/compose.yaml" down -t 0 >/dev/null 2>&1
    rm -rf "$T"
}
trap cleanup EXIT

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
# Exact count(*) of every table, one "table<TAB>rows" line each.
counts() {
    local t
    for t in $(sql "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" | cut -f1); do
        printf '%s\t%s\n' "$t" "$(sql "SELECT COUNT(*) FROM \`$t\`")"
    done
}
has_table() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$1'"; }
reset() { make -s reset-db >"$T/out" 2>&1; }

echo "> a reset, then the database changed by hand"
if reset; then ok "make reset-db succeeds ($(tail -1 "$T/out" | sed 's/^ok //'))"; else bad "make reset-db succeeds"; cat "$T/out"; exit 1; fi
first=$(counts)
sql "DELETE FROM albums ORDER BY id DESC LIMIT 1; CREATE TABLE stray_after_reset (id int)"
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

echo "> the smoke test, right after the reset"
if ./tests/smoke-test.sh "$URL" >"$T/out" 2>&1; then
    ok "the smoke test passes ($(grep -o '[0-9]* passed' "$T/out"))"
else
    bad "the smoke test passes"; tail -20 "$T/out"
fi

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

reset && check "and a last reset leaves the database as the fixtures have it" "$first" "$(counts)"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
