#!/usr/bin/env bash
#
# scripts/migrate.sh against the running local stack, as the smoke workflow has it up.
#
# What it proves:
#   - make reset-db leaves every migration applied and the fixtures loaded
#   - a migration goes up and down over that data without losing a row, and status follows
#   - a migration that fails is not recorded, and nothing after it runs
#   - down all empties the database; up 0001, the fixtures and up bring it back as it was
#   - a database with the schema but no record, production before its baseline, is refused by
#     up and taken by baseline, which also reports columns no migration describes; a baseline
#     on an empty database, a second baseline, a bad slug and a migration without a down are
#     refused
#   - the ovh target's ssh path carries SQL both ways, through a stand-in for ssh, and down
#     there needs a confirmation
#   - new writes the next version's two files
#   - the smoke test passes at the end
#
# Throwaway migrations live in copies of the migrations directory, removed on the way out. The
# database ends as make reset-db leaves it.
#
# Usage: tests/migrate-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-migratetest.XXXXXX")
trap 'rm -rf "$T"' EXIT
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
counts() { # exact count(*) of every table but the migrations' own, one "table<TAB>rows" line each
    local t
    for t in $(sql "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" | cut -f1 | grep -vx schema_migrations); do
        printf '%s\t%s\n' "$t" "$(sql "SELECT COUNT(*) FROM \`$t\`")"
    done
}
has_column() { sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$1' AND column_name = '$2'"; }
app_tables() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'"; }
load_fixtures() { # load_fixtures [file]: the baseline fixtures, or the given file
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}"' <"${1:-database/tests/fixtures.sql}"
}
migrate() { ./scripts/migrate.sh "$@" >"$T/out" 2>&1; }
last() { tail -1 "$T/out"; }
summary() { grep -oE '[0-9]+ applied, [0-9]+ pending' "$T/out" | tail -1; }
has() { grep -o "$1" "$T/out" | head -1; }
# A copy of the migrations, to add throwaway ones to: copy <name> [files...]
copy() { mkdir -p "$T/$1"; cp database/migrations/* "$T/$1/"; }

echo "> make reset-db, then status"
if make -s reset-db >"$T/out" 2>&1; then ok "make reset-db succeeds ($(last | sed 's/^ok //'))"; else bad "make reset-db succeeds"; cat "$T/out"; exit 1; fi
first=$(counts)
baseline_tables=$(grep -c '^CREATE TABLE' database/migrations/0001-baseline.up.sql)
# How many migrations exist, and the version the next one gets.
migration_count=$(find database/migrations -name '[0-9][0-9][0-9][0-9]-*.up.sql' | wc -l | tr -d ' ')
next_version=$(printf '%04d' $((migration_count + 1)))
migrate status
check "status shows nothing pending after a reset" "0 pending" "$(summary | grep -o '0 pending')"
check "status lists the baseline with the time it was applied" "0001 baseline applied" \
    "$(awk '$1 == "0001" && $2 == "baseline" && $3 ~ /^20[0-9][0-9]-/ { print "0001 baseline applied" }' "$T/out")"

echo "> a throwaway migration, in a copy of the directory"
copy m
printf 'ALTER TABLE albums ADD COLUMN migrate_test TINYINT(1) NOT NULL DEFAULT 0;\n' >"$T/m/9999-test-column.up.sql"
printf 'ALTER TABLE albums DROP COLUMN migrate_test;\n' >"$T/m/9999-test-column.down.sql"
m() { MIGRATIONS_DIR="$T/m" ./scripts/migrate.sh "$@" >"$T/out" 2>&1; }
m status
check "status shows it pending" "1 pending" "$(summary | grep -oE '[0-9]+ pending')"
m up
check "up applies it: the column is there" "1" "$(has_column albums migrate_test)"
check "and every row is still there" "$first" "$(counts)"
check "and status shows nothing pending" "0 pending" "$(summary | grep -oE '[0-9]+ pending')"
m down
check "down removes the column" "0" "$(has_column albums migrate_test)"
check "with every row still there" "$first" "$(counts)"
check "and status shows it pending again" "1 pending" "$(summary | grep -oE '[0-9]+ pending')"

echo "> a migration that fails halfway through a run"
copy broken
printf 'ALTER TABLE albums ADD COLUMN before_broken TINYINT(1) NOT NULL DEFAULT 0;\n' >"$T/broken/9998-before.up.sql"
printf 'ALTER TABLE albums DROP COLUMN before_broken;\n' >"$T/broken/9998-before.down.sql"
printf 'ALTER TABLE nope_this_table_is_missing ADD COLUMN x INT;\n' >"$T/broken/9999-broken.up.sql"
printf 'SELECT 1;\n' >"$T/broken/9999-broken.down.sql"
if MIGRATIONS_DIR="$T/broken" ./scripts/migrate.sh up >"$T/out" 2>&1; then bad "a failing migration fails the run"; else ok "a failing migration fails the run"; fi
check "the message names the file" "9999-broken.up.sql" "$(has '9999-broken.up.sql')"
check "the one before it is recorded, the failing one is not" "9998" "$(sql "SELECT GROUP_CONCAT(version) FROM schema_migrations WHERE version IN ('9998', '9999')")"
if MIGRATIONS_DIR="$T/broken" ./scripts/migrate.sh down >"$T/out" 2>&1; then ok "the one before it goes down again"; else bad "the one before it goes down again"; cat "$T/out"; fi
check "leaving no trace" "0 0" "$(has_column albums before_broken) $(sql "SELECT COUNT(*) FROM schema_migrations WHERE version IN ('9998', '9999')")"

echo "> down all, then back from an empty database"
migrate down all
check "down all leaves no table but the migrations' own" "0 tables, 0 applied" "$(app_tables) tables, $(summary | grep -oE '[0-9]+ applied')"
if migrate baseline; then bad "baseline on an empty database is refused"; else check "baseline on an empty database is refused, pointing at up" "an empty database" "$(has 'an empty database')"; fi
migrate up 0001
check "up 0001 brings the baseline schema back" "$baseline_tables tables" "$(app_tables) tables"
load_fixtures
migrate up
load_fixtures database/tests/fixtures-latest.sql
check "up applies the rest, and nothing is pending" "0 pending" "$(summary | grep -oE '[0-9]+ pending')"
check "the data is what reset-db loaded" "$first" "$(counts)"

echo "> a database with the schema but no record: production before its baseline"
# The baseline's schema, as production's dump had it: later migrations may drop baseline
# tables (0010 drops city_artist_lookup), so go down to it before forgetting the record.
migrate down $((migration_count - 1))
at_baseline=$(counts)
sql "DROP TABLE schema_migrations"
if migrate up; then bad "up on it is refused"; else check "up on it is refused and points at baseline" "'baseline' first" "$(has "'baseline' first")"; fi
check "and changed nothing" "$at_baseline" "$(counts)"
if migrate baseline; then ok "baseline records 0001 without running it ($(last | sed 's/^ok [^:]*: //'))"; else bad "baseline records 0001 without running it"; cat "$T/out"; fi
migrate status
check "status shows the baseline applied and only what came after it pending" "1 applied, $((migration_count - 1)) pending" "$(summary)"
if migrate baseline; then bad "a second baseline is refused"; else check "a second baseline is refused" "already records" "$(has 'already records')"; fi
sql "DROP TABLE schema_migrations; ALTER TABLE albums ADD COLUMN not_in_any_migration INT"
migrate baseline
check "baseline notes a column no migration describes" "albums.not_in_any_migration" "$(has 'albums.not_in_any_migration')"
sql "DROP TABLE schema_migrations; ALTER TABLE albums DROP COLUMN not_in_any_migration, DROP COLUMN legal"
if migrate baseline; then bad "baseline on a schema missing a column is refused"; else check "baseline on a schema missing a column is refused, naming it" "albums.legal" "$(has 'albums.legal')"; fi

echo "> what the files must look like"
copy new
if MIGRATIONS_DIR="$T/new" ./scripts/migrate.sh new 'Bad Slug' >"$T/out" 2>&1; then bad "a bad slug is refused"; else ok "a bad slug is refused"; fi
MIGRATIONS_DIR="$T/new" ./scripts/migrate.sh new add-thing >"$T/out" 2>&1
check "new writes the next version's two files" "$next_version-add-thing.down.sql $next_version-add-thing.up.sql" "$(cd "$T/new" && printf '%s\n' "$next_version"-* | tr '\n' ' ' | sed 's/ $//')"
copy nodown
printf 'SELECT 1;\n' >"$T/nodown/0002-no-down.up.sql"
if MIGRATIONS_DIR="$T/nodown" ./scripts/migrate.sh status >"$T/out" 2>&1; then bad "a migration without a down is refused"; else check "a migration without a down is refused" "has no 0002-no-down.down.sql" "$(has 'has no 0002-no-down.down.sql')"; fi

echo "> the ovh target, through a stand-in for ssh"
cat >"$T/ssh" <<'SH'
#!/usr/bin/env bash
# Stands in for ssh: drops the user@host and runs the command here, as the far side would.
shift
exec sh -c "$1"
SH
chmod +x "$T/ssh"
# The database here was just left without a record by the last refusal; reset first.
make -s reset-db >/dev/null 2>&1
db_name=$(docker compose ps -q db | xargs docker inspect --format '{{.Name}}' | sed 's|^/||')
ovh() {
    MIGRATE_TARGET=ovh MIGRATE_SSH="$T/ssh" OVH_SUDO='' OVH_HOST=stand-in.example OVH_DB_CONTAINER="$db_name" \
        MIGRATIONS_DIR="$T/m" MIGRATE_ASSUME_YES="${MIGRATE_ASSUME_YES:-}" ./scripts/migrate.sh "$@" >"$T/out" 2>&1
}
ovh status
check "status over the ssh path reads the database" "1 pending" "$(summary | grep -oE '[0-9]+ pending')"
check "and says where it looked" "$db_name on stand-in.example" "$(has "$db_name on stand-in.example")"
ovh up
check "up over the ssh path applies a migration" "1" "$(has_column albums migrate_test)"
if ovh down </dev/null; then bad "down there without a terminal is refused"; else check "down there without a terminal is refused" "needs a terminal" "$(has 'needs a terminal')"; fi
check "and did not run" "1" "$(has_column albums migrate_test)"
MIGRATE_ASSUME_YES=1 ovh down
check "down there with the confirmation waived reverts" "0" "$(has_column albums migrate_test)"

echo "> the end state"
make -s reset-db >"$T/out" 2>&1
migrate status
check "a last reset-db leaves nothing pending" "0 pending" "$(summary | grep -oE '[0-9]+ pending')"
check "and the fixtures as they were" "$first" "$(counts)"
if ./tests/smoke-test.sh "$URL" >"$T/out" 2>&1; then ok "the smoke test passes ($(grep -o '[0-9]* passed' "$T/out"))"; else bad "the smoke test passes"; tail -20 "$T/out"; fi

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
