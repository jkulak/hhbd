#!/usr/bin/env bash
#
# scripts/migrate.sh against the running local stack, as the smoke workflow has it up.
#
# What it proves:
#   - make reset-db leaves every migration applied and the fixtures loaded
#   - a migration goes up and down over that data without losing a row, and status follows
#   - a migration that fails is not recorded, and nothing after it runs; on MariaDB what of it
#     ran stays, as the message warns, and on PostgreSQL nothing of it does, as it ran in one
#     transaction with its record
#   - down all empties the database; up 0001, the fixtures and up bring it back as it was
#   - a database with the schema but no record, production before its baseline, is refused by
#     up and taken by baseline, which also reports columns no migration describes; a baseline
#     on an empty database, a second baseline, a bad slug and a migration without a down are
#     refused
#   - the ovh target's ssh path carries SQL both ways, through a stand-in for ssh, and down
#     there needs a confirmation; without OVH_DB_CONTAINER it names production's container
#   - new writes the next version's two files
#
# DB_ENGINE=postgresql runs it on PostgreSQL's track, database/migrations-postgresql, on the
# stack compose.postgresql.yaml makes (#234). The throwaway migrations are written in its DDL.
# Its track starts with one migration and a database built from empty, so there is no
# baseline to record: baseline is refused everywhere, and the rows come back from MariaDB
# with the copy make reset-db runs, rather than from the fixtures.
#
# Throwaway migrations live in copies of the migrations directory, removed on the way out. The
# database ends as make reset-db leaves it.
#
# The smoke test after a reset is the smoke workflow's own step, not this test's.
#
# Usage: tests/migrate-test.sh
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-migratetest.XXXXXX")
trap 'rm -rf "$T"' EXIT
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

# What differs by engine: the migrations, the database's service, production's container of
# it, and the type of a throwaway yes/no column
if is_pg; then
    MIGRATIONS=database/migrations-postgresql SERVICE=postgres SCHEMA='current_schema()' FLAG=smallint
else
    MIGRATIONS=database/migrations SERVICE=db SCHEMA='DATABASE()' FLAG='TINYINT(1)'
fi
PRODUCTION_CONTAINER=hhbd-$SERVICE-1

counts() { # exact count(*) of every table but the migrations' own, one "table<TAB>rows" line each, in one call
    sql "SELECT CONCAT('SELECT ''', table_name, ''', COUNT(*) FROM ', table_name, ';') FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations' ORDER BY table_name" | sql_in
}
has_column() { sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = $SCHEMA AND table_name = '$1' AND column_name = '$2'"; }
has_table() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_name = '$1'"; }
app_tables() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'"; }
load_fixtures() { # load_fixtures [file]: the baseline fixtures, or the given file; MariaDB's
    sql_in <"${1:-database/tests/fixtures.sql}"
}
migrate() { ./scripts/migrate.sh "$@" >"$T/out" 2>&1; }
last() { tail -1 "$T/out"; }
summary() { grep -oE '[0-9]+ applied, [0-9]+ pending' "$T/out" | tail -1; }
has() { grep -o "$1" "$T/out" | head -1; }
# A copy of the migrations, to add throwaway ones to: copy <name> [files...]
copy() { mkdir -p "$T/$1"; cp "$MIGRATIONS"/* "$T/$1/"; }

echo "> make reset-db, then status"
if make -s reset-db >"$T/out" 2>&1; then ok "make reset-db succeeds ($(last | sed 's/^ok //'))"; else bad "make reset-db succeeds"; cat "$T/out"; exit 1; fi
first=$(counts)
# The first migration, the baseline on MariaDB's track, and the tables it creates.
first_file=$(find "$MIGRATIONS" -name '[0-9][0-9][0-9][0-9]-*.up.sql' | sort | head -1)
first_migration=$(basename "$first_file" .up.sql)
baseline_tables=$(grep -c '^CREATE TABLE' "$first_file")
# How many migrations exist, and the version the next one gets.
migration_count=$(find "$MIGRATIONS" -name '[0-9][0-9][0-9][0-9]-*.up.sql' | wc -l | tr -d ' ')
last_version=$(find "$MIGRATIONS" -name '[0-9][0-9][0-9][0-9]-*.up.sql' | sort | tail -1 | xargs basename | cut -c1-4)
next_version=$(printf '%04d' $((10#$last_version + 1)))
migrate status
check "status shows nothing pending after a reset" "0 pending" "$(summary | grep -o '0 pending')"
check "status lists the first migration with the time it was applied" "${first_migration/-/ } applied" \
    "$(awk -v v="${first_migration%%-*}" -v n="${first_migration#*-}" '$1 == v && $2 == n && $3 ~ /^20[0-9][0-9]-/ { print v " " n " applied" }' "$T/out")"

echo "> a throwaway migration, in a copy of the directory"
copy m
printf 'ALTER TABLE albums ADD COLUMN migrate_test %s NOT NULL DEFAULT 0;\n' "$FLAG" >"$T/m/9999-test-column.up.sql"
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
printf 'ALTER TABLE albums ADD COLUMN before_broken %s NOT NULL DEFAULT 0;\n' "$FLAG" >"$T/broken/9998-before.up.sql"
printf 'ALTER TABLE albums DROP COLUMN before_broken;\n' >"$T/broken/9998-before.down.sql"
# Its first statement works, its second cannot.
printf 'ALTER TABLE albums ADD COLUMN inside_broken %s NOT NULL DEFAULT 0;\nALTER TABLE nope_this_table_is_missing ADD COLUMN x INT;\n' "$FLAG" >"$T/broken/9999-broken.up.sql"
printf 'SELECT 1;\n' >"$T/broken/9999-broken.down.sql"
if MIGRATIONS_DIR="$T/broken" ./scripts/migrate.sh up >"$T/out" 2>&1; then bad "a failing migration fails the run"; else ok "a failing migration fails the run"; fi
check "the message names the file" "9999-broken.up.sql" "$(has '9999-broken.up.sql')"
check "the one before it is recorded, the failing one is not" "9998" "$(sql "SELECT $(agg version version) FROM schema_migrations WHERE version IN ('9998', '9999')")"
if is_pg; then
    check "and nothing of the failing one stays, as the message says: it ran in one transaction" \
        "0 nothing of it took effect" "$(has_column albums inside_broken) $(has 'nothing of it took effect')"
else
    # MariaDB commits DDL as it goes: what ran before the error stays, which the message warns
    # of, and is undone by hand.
    check "and what of the failing one ran stays, as the message warns" \
        "1 check what of it took effect" "$(has_column albums inside_broken) $(has 'check what of it took effect')"
    sql "ALTER TABLE albums DROP COLUMN inside_broken"
fi
if MIGRATIONS_DIR="$T/broken" ./scripts/migrate.sh down >"$T/out" 2>&1; then ok "the one before it goes down again"; else bad "the one before it goes down again"; cat "$T/out"; fi
check "leaving no trace" "0 0" "$(has_column albums before_broken) $(sql "SELECT COUNT(*) FROM schema_migrations WHERE version IN ('9998', '9999')")"

echo "> down all, then back from an empty database"
migrate down all
check "down all leaves no table but the migrations' own" "0 tables, 0 applied" "$(app_tables) tables, $(summary | grep -oE '[0-9]+ applied')"
if is_pg; then empty_refusal='built by up from empty'; else empty_refusal='an empty database'; fi
if migrate baseline; then bad "baseline on an empty database is refused"; else check "baseline on an empty database is refused, pointing at up" "$empty_refusal" "$(has "$empty_refusal")"; fi
if is_pg; then
    # The one migration of the track, then MariaDB's rows copied in, as make reset-db has them
    migrate up
    check "up brings the schema back, and nothing is pending" "$baseline_tables tables, 0 pending" "$(app_tables) tables, $(summary | grep -oE '[0-9]+ pending')"
    if docker compose exec -T app php /var/www/html/app/tools/mariadb-to-postgres.php copy >"$T/out" 2>&1; then
        ok "MariaDB's rows are copied in again"
    else
        bad "MariaDB's rows are copied in again"; cat "$T/out"
    fi
else
    migrate up 0001
    check "up 0001 brings the baseline schema back" "$baseline_tables tables" "$(app_tables) tables"
    load_fixtures
    migrate up
    load_fixtures database/tests/fixtures-latest.sql
    check "up applies the rest, and nothing is pending" "0 pending" "$(summary | grep -oE '[0-9]+ pending')"
fi
check "the data is what reset-db loaded" "$first" "$(counts)"

echo "> a database with the schema but no record: production before its baseline"
# The baseline's schema, as production's dump had it: later migrations may drop baseline
# tables (0010 drops city_artist_lookup), so go down to it before forgetting the record.
[ "$migration_count" -eq 1 ] || migrate down $((migration_count - 1))
at_baseline=$(counts)
sql "DROP TABLE schema_migrations"
if is_pg; then
    if migrate up; then bad "up on it is refused"; else check "up on it is refused, and points at up from empty rather than at baseline" "built by up from empty" "$(has 'built by up from empty')"; fi
    check "and changed nothing" "$at_baseline" "$(counts)"
    if migrate baseline; then bad "baseline on it is refused too: PostgreSQL's track has none"; else
        check "baseline on it is refused too: PostgreSQL's track has none" "built by up from empty 0" "$(has 'built by up from empty') $(has_table schema_migrations)"; fi
    check "and changed nothing either" "$at_baseline" "$(counts)"
else
    if migrate up; then bad "up on it is refused"; else check "up on it is refused and points at baseline" "'baseline' first" "$(has "'baseline' first")"; fi
    check "and changed nothing" "$at_baseline" "$(counts)"
    if migrate baseline; then ok "baseline records 0001 without running it ($(last | sed 's/^ok [^:]*: //'))"; else bad "baseline records 0001 without running it"; cat "$T/out"; fi
    migrate status
    check "status shows the baseline applied and only what came after it pending" "1 applied, $((migration_count - 1)) pending" "$(summary)"
    if migrate baseline; then bad "a second baseline is refused"; else check "a second baseline is refused" "already records" "$(has 'already records')"; fi
    # At the baseline the albums hold the zero dates the fixtures have, which a table rebuild under
    # the server's sql_mode refuses to copy (#88); so these changes by hand take the fixtures' session.
    legacy="SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO';"
    sql "$legacy DROP TABLE schema_migrations; ALTER TABLE albums ADD COLUMN not_in_any_migration INT"
    migrate baseline
    check "baseline notes a column no migration describes" "albums.not_in_any_migration" "$(has 'albums.not_in_any_migration')"
    sql "$legacy DROP TABLE schema_migrations; ALTER TABLE albums DROP COLUMN not_in_any_migration, DROP COLUMN legal"
    if migrate baseline; then bad "baseline on a schema missing a column is refused"; else check "baseline on a schema missing a column is refused, naming it" "albums.legal" "$(has 'albums.legal')"; fi
fi

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
cat >"$T/ssh-unreachable" <<'SH'
#!/usr/bin/env bash
# Stands in for an ssh that cannot connect: writes down the command it was given, runs nothing.
printf '%s\n' "$2" >>"$(dirname "$0")/ssh-commands"
exit 255
SH
chmod +x "$T/ssh" "$T/ssh-unreachable"
# Production's container of the engine when OVH_DB_CONTAINER does not name another, and its
# client: what the far side would be asked to run, though nothing here runs it.
if MIGRATE_TARGET=ovh MIGRATE_SSH="$T/ssh-unreachable" OVH_SUDO='' OVH_HOST=stand-in.example OVH_DB_CONTAINER='' \
    MIGRATIONS_DIR="$T/m" ./scripts/migrate.sh status >"$T/out" 2>&1; then bad "without OVH_DB_CONTAINER it reaches for $PRODUCTION_CONTAINER"; else
    if is_pg; then client='exec psql'; else client='MYSQL_PWD='; fi
    check "without OVH_DB_CONTAINER it reaches for $PRODUCTION_CONTAINER, with the engine's client" \
        "$PRODUCTION_CONTAINER on stand-in.example docker exec -i $PRODUCTION_CONTAINER sh -c '$client" \
        "$(has "$PRODUCTION_CONTAINER on stand-in.example") $(grep -o "docker exec -i $PRODUCTION_CONTAINER sh -c '$client" "$T/ssh-commands" | head -1)"
fi
# The database here was just left without a record by the last refusal; reset first.
make -s reset-db >/dev/null 2>&1
db_name=$(docker compose ps -q "$SERVICE" | xargs docker inspect --format '{{.Name}}' | sed 's|^/||')
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

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
