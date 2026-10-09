#!/usr/bin/env bash
#
# Database migrations: plain SQL, two files each, applied in order, recorded in the database.
#
#   database/migrations/NNNN-slug.up.sql      what the migration does
#   database/migrations/NNNN-slug.down.sql    how to undo it; every migration has one
#
# Applied versions are recorded in the table schema_migrations of the database itself, so a
# migration runs once and status is a query. 0001-baseline is the schema as it was when the
# migrations began; a database that already has that schema records it with `baseline`
# instead of running it, and `up` refuses to run on such a database until that is done.
#
#   migrate.sh status             every migration: applied when, or pending
#   migrate.sh up [<version>]     apply what is pending, up to and including <version>
#   migrate.sh down [<n>|all]     revert the last <n> applied migrations, one by default
#   migrate.sh baseline           record 0001 as applied on a database that already has its schema
#   migrate.sh new <slug>         two empty files with the next version number
#
# Where it runs, MIGRATE_TARGET:
#   local       this checkout's compose project on the local Docker engine (the default), with
#               the same refusals as make reset-db: a remote engine, no running db, a db from
#               another directory or from production's compose file
#   container   the container MIGRATE_CONTAINER names, on the local engine: for tests, and for
#               a stack under another project name
#   ovh         production's database on the OVH host, over ssh as the admin account, with
#               OVH_HOST from .env; `down` asks for a typed confirmation there
#
# Each file runs as one mariadb session, as root with the container's own password; nothing
# secret passes through here. MariaDB commits DDL as it goes, so a file that fails halfway has
# done part of its work and is not recorded: undo by hand what ran, fix the file, run again.
# Keep a migration small for that reason. MIGRATIONS_DIR replaces the directory, for tests.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=scripts/lib-db.sh
. scripts/lib-db.sh
REFUSE_AS=migrate

DIR=${MIGRATIONS_DIR:-database/migrations}
TARGET=${MIGRATE_TARGET:-local}
TABLE=schema_migrations
cmd=${1:-}
arg=${2:-}

usage() { echo "usage: $0 {status | up [<version>] | down [<n>|all] | baseline | new <slug>}" >&2; exit 2; }
case "$cmd" in status|up|down|baseline|new) ;; *) usage ;; esac

# --- The migrations on disk ---------------------------------------------------------------

[ -d "$DIR" ] || refuse "no migrations directory $DIR"
for f in "$DIR"/*; do
    [ -e "$f" ] || continue
    case "$(basename "$f")" in
        [0-9][0-9][0-9][0-9]-*.up.sql|[0-9][0-9][0-9][0-9]-*.down.sql) ;;
        *) refuse "$f does not look like a migration (NNNN-slug.up.sql with NNNN-slug.down.sql)" ;;
    esac
done

# ALL: "version<TAB>name" per migration, in order. Every up has a down, every version is one file.
migrations() {
    local f base version name previous=
    for f in "$DIR"/[0-9][0-9][0-9][0-9]-*.up.sql; do
        [ -e "$f" ] || continue
        base=$(basename "$f" .up.sql)
        version=${base%%-*}
        name=${base#*-}
        [[ "$name" =~ ^[a-z0-9]+(-[a-z0-9]+)*$ ]] || refuse "$f: a slug is lowercase letters, digits and dashes"
        [ -f "$DIR/$base.down.sql" ] || refuse "$f has no $base.down.sql; every migration has a down"
        [ "$version" != "$previous" ] || refuse "two migrations carry the version $version in $DIR"
        previous=$version
        printf '%s\t%s\n' "$version" "$name"
    done
}
ALL=$(migrations)

name_of() { printf '%s\n' "$ALL" | awk -F'\t' -v v="$1" '$1 == v { print $2 }'; }

# --- new: needs no database ---------------------------------------------------------------

if [ "$cmd" = new ]; then
    [[ "$arg" =~ ^[a-z0-9]+(-[a-z0-9]+)*$ ]] || refuse "a slug is lowercase letters, digits and dashes, like add-album-isrc"
    last=$(printf '%s\n' "$ALL" | awk -F'\t' 'END { print $1 + 0 }')
    [ "$last" -lt 9999 ] || refuse "version 9999 is taken; the four-digit scheme is full"
    next=$(printf '%04d' $((last + 1)))
    printf -- '-- %s %s: up. One change, small enough to undo with the down file next to it.\n' "$next" "$arg" >"$DIR/$next-$arg.up.sql"
    printf -- '-- %s %s: down. Undo exactly what the up file does.\n' "$next" "$arg" >"$DIR/$next-$arg.down.sql"
    echo "ok $DIR/$next-$arg.up.sql and $next-$arg.down.sql; fill them in, then make migrate"
    exit 0
fi

# --- The database -------------------------------------------------------------------------

use_db_target "$TARGET"

query() { printf '%s\n' "$1" | db_sql; }
# Every file in a session that allows zero dates, which the older migrations need (lib-db.sh).
run_file() { { printf '%s\n' "$LEGACY_DATES_SESSION"; cat "$1"; } | db_sql; }

[ "$(query 'SELECT 1' 2>/dev/null)" = 1 ] || refuse "cannot reach the database at $where"

has_table() { [ "$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$1'")" = 1 ]; }
ensure_table() {
    query "CREATE TABLE IF NOT EXISTS $TABLE (
        version CHAR(4) NOT NULL PRIMARY KEY,
        name VARCHAR(190) NOT NULL,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
}
# applied: "version<TAB>name<TAB>applied_at" per recorded migration, in order; nothing before
# the table exists.
applied() { if has_table "$TABLE"; then query "SELECT version, name, applied_at FROM $TABLE ORDER BY version"; fi; }
is_recorded() { printf '%s\n' "$2" | awk -F'\t' -v v="$1" '$1 == v' | grep -q .; }
app_tables() { query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND table_name <> '$TABLE'"; }

# --- status -------------------------------------------------------------------------------

status() {
    local recs version name rec when state done=0 pending=0 orphans=0
    recs=$(applied)
    echo "migrations in $DIR, database $where"
    printf '  %-8s %-28s %s\n' version name applied
    while IFS=$'\t' read -r version name; do
        [ -n "$version" ] || continue
        rec=$(printf '%s\n' "$recs" | awk -F'\t' -v v="$version" '$1 == v { print $2 "\t" $3 }')
        if [ -n "$rec" ]; then
            when=${rec#*$'\t'}
            state=$when
            [ "${rec%%$'\t'*}" = "$name" ] || state="$when, recorded as ${rec%%$'\t'*}"
            done=$((done + 1))
        else
            state=pending
            pending=$((pending + 1))
        fi
        printf '  %-8s %-28s %s\n' "$version" "$name" "$state"
    done <<<"$ALL"
    while IFS=$'\t' read -r version name when; do
        [ -n "$version" ] || continue
        [ -z "$(name_of "$version")" ] || continue
        printf '  %-8s %-28s %s\n' "$version" "$name" "$when, recorded, but no file in $DIR"
        orphans=$((orphans + 1))
    done <<<"$recs"
    echo "$done applied, $pending pending$([ "$orphans" -eq 0 ] || echo ", $orphans recorded without a file")"
}

# --- up -----------------------------------------------------------------------------------

up() {
    local to=$1 recs tables version name f n=0
    recs=$(applied)
    if [ -z "$recs" ]; then
        # A database with tables but no record is one that predates the migrations: production,
        # or a dump loaded by hand. Running the baseline on it would recreate its tables.
        tables=$(app_tables)
        [ "$tables" = 0 ] || refuse "$where has $tables tables but no migration is recorded; a database that already has the schema takes 'baseline' first"
        ensure_table
    fi
    [ -z "$to" ] || [ -n "$(name_of "$to")" ] || refuse "no migration $to in $DIR"
    while IFS=$'\t' read -r version name; do
        [ -n "$version" ] || continue
        if [ -n "$to" ] && [[ "$version" > "$to" ]]; then break; fi
        if is_recorded "$version" "$recs"; then continue; fi
        f="$DIR/$version-$name.up.sql"
        echo "> up $version $name"
        if ! run_file "$f"; then
            echo "x migrate: $f failed at $where. It is not recorded and nothing after it ran; MariaDB commits DDL as it goes, so check what of it took effect before running again" >&2
            exit 1
        fi
        query "INSERT INTO $TABLE (version, name) VALUES ('$version', '$name')"
        n=$((n + 1))
    done <<<"$ALL"
    echo "ok $where: $n up; $(status | tail -1)"
}

# --- down ---------------------------------------------------------------------------------

down() {
    local count=${1:-1} recs total list version name when f n=0 answer
    recs=$(applied)
    [ -n "$recs" ] || refuse "nothing is recorded as applied at $where"
    total=$(printf '%s\n' "$recs" | grep -c .)
    [ "$count" != all ] || count=$total
    [[ "$count" =~ ^[1-9][0-9]*$ ]] || usage
    [ "$count" -le "$total" ] || refuse "$where has $total applied, not $count"
    # The last <count>, newest first.
    list=$(printf '%s\n' "$recs" | tail -n "$count" | sort -r)
    if [ "$TARGET" = ovh ] && [ "${MIGRATE_ASSUME_YES:-}" != 1 ]; then
        echo "About to revert on $where:"
        printf '%s\n' "$list" | awk -F'\t' '{ print "  " $1 " " $2 }'
        [ -t 0 ] || refuse "reverting on $TARGET needs a terminal to confirm on"
        printf 'Type the version %s to go on: ' "${list%%$'\t'*}"
        read -r answer
        [ "$answer" = "${list%%$'\t'*}" ] || refuse "not confirmed"
    fi
    while IFS=$'\t' read -r version name when; do
        [ -n "$version" ] || continue
        f=$(printf '%s\n' "$DIR/$version"-*.down.sql | head -1)
        [ -f "$f" ] || refuse "no $version-*.down.sql in $DIR for the recorded migration $version $name ($when)"
        echo "> down $version $name"
        if ! run_file "$f"; then
            echo "x migrate: $f failed at $where. $version stays recorded; MariaDB commits DDL as it goes, so check what of it took effect before running again" >&2
            exit 1
        fi
        query "DELETE FROM $TABLE WHERE version = '$version'"
        n=$((n + 1))
    done <<<"$list"
    echo "ok $where: $n down; $(status | tail -1)"
}

# --- baseline -----------------------------------------------------------------------------

# The columns the baseline creates, "table<TAB>column": mysqldump writes one column per line,
# indented two spaces and backquoted; keys and constraints start with a word instead.
baseline_columns() { awk '/^CREATE TABLE `/ { t = $3; gsub(/`/, "", t) } /^  `/ { c = $1; gsub(/`/, "", c); print t "\t" c }' "$1" | sort; }
db_columns() { query "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name <> '$TABLE'" | sort; }
list_columns() { awk -F'\t' '{ printf "%s.%s ", $1, $2 }'; }

baseline() {
    local recs f missing extra
    recs=$(applied)
    [ -z "$recs" ] || refuse "$where already records $(printf '%s\n' "$recs" | grep -c .) migration(s); baseline is for a database with none"
    f=$(printf '%s\n' "$DIR"/0001-*.up.sql | head -1)
    [ -f "$f" ] || refuse "no 0001-*.up.sql in $DIR"
    # Every column the baseline would create must be there, or this is not that schema.
    missing=$(comm -23 <(baseline_columns "$f") <(db_columns))
    [ -z "$missing" ] || refuse "$where lacks columns the baseline creates, so it is not at the baseline (an empty database takes 'up' instead): $(printf '%s\n' "$missing" | list_columns)"
    extra=$(comm -13 <(baseline_columns "$f") <(db_columns))
    [ -z "$extra" ] || echo "note: $where also has columns no migration describes: $(printf '%s\n' "$extra" | list_columns)"
    ensure_table
    query "INSERT INTO $TABLE (version, name) VALUES ('0001', '$(name_of 0001)')"
    echo "ok $where: 0001 $(name_of 0001) recorded as applied without running it, $(baseline_columns "$f" | grep -c .) columns checked"
}

case "$cmd" in
    status)   status ;;
    up)       up "$arg" ;;
    down)     down "$arg" ;;
    baseline) baseline ;;
esac
