#!/usr/bin/env bash
#
# Database migrations: plain SQL, two files each, applied in order, recorded in the database.
#
#   database/migrations/NNNN-slug.up.sql      what the migration does
#   database/migrations/NNNN-slug.down.sql    how to undo it; every migration has one
#
# Applied versions are recorded in the table schema_migrations of the database itself, so a
# migration runs once and status is a query. The first, 0039-postgresql, is the whole schema as
# MariaDB had it after its own 0001-0038 (#232), which left the tree with MariaDB (#237); a
# database is built by `up` from empty, and `up` refuses one with tables but no record.
#
#   migrate.sh status             every migration: applied when, or pending
#   migrate.sh up [<version>]     apply what is pending, up to and including <version>
#   migrate.sh down [<n>|all]     revert the last <n> applied migrations, one by default
#   migrate.sh new <slug>         two empty files with the next version number
#
# Where it runs, MIGRATE_TARGET:
#   local       this checkout's compose project on the local Docker engine (the default), with
#               the same refusals as make reset-db: a remote engine, no running postgres, one
#               from another directory or from production's compose file
#   container   the container MIGRATE_CONTAINER names, on the local engine: for tests, and for
#               a stack under another project name
#   ovh         production's database on the OVH host, over ssh as the admin account, with
#               OVH_HOST from gcloud-ovh-migrate's .env; `down` asks for a typed confirmation there
#
# Each file runs with psql as the image's superuser over the container's socket; nothing secret
# passes through here. A file and its record are one transaction, so a file that fails leaves
# nothing behind. MIGRATIONS_DIR replaces the directory, for tests.
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

usage() { echo "usage: $0 {status | up [<version>] | down [<n>|all] | new <slug>}" >&2; exit 2; }
case "$cmd" in status|up|down|new) ;; *) usage ;; esac

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
# run_file <file> <record SQL>: the file and the SQL that records it, in one transaction
run_file() {
    { printf 'BEGIN;\n'; cat "$1"; printf '\n%s;\nCOMMIT;\n' "$2"; } | db_sql
}
FAILED_NOTE="nothing of it took effect, as it ran in one transaction"

[ "$(query 'SELECT 1' 2>/dev/null)" = 1 ] || refuse "cannot reach the database at $where"

has_table() { [ "$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA_SQL AND table_name = '$1'")" = 1 ]; }
ensure_table() {
    query "CREATE TABLE IF NOT EXISTS $TABLE (
        version varchar(4) NOT NULL PRIMARY KEY,
        name varchar(190) NOT NULL,
        applied_at timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
    )"
}
# applied: "version<TAB>name<TAB>applied_at" per recorded migration, in order; nothing before
# the table exists.
applied() { if has_table "$TABLE"; then query "SELECT version, name, applied_at FROM $TABLE ORDER BY version"; fi; }
is_recorded() { printf '%s\n' "$2" | awk -F'\t' -v v="$1" '$1 == v' | grep -q .; }
app_tables() { query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA_SQL AND table_type = 'BASE TABLE' AND table_name <> '$TABLE'"; }

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
        # A database with tables but no record is a dump loaded by hand, or one the migrations
        # did not make; the first migration would create its tables again.
        tables=$(app_tables)
        [ "$tables" = 0 ] || refuse "$where has $tables tables but no migration is recorded; a database is built by up from empty"
        ensure_table
    fi
    [ -z "$to" ] || [ -n "$(name_of "$to")" ] || refuse "no migration $to in $DIR"
    while IFS=$'\t' read -r version name; do
        [ -n "$version" ] || continue
        if [ -n "$to" ] && [[ "$version" > "$to" ]]; then break; fi
        if is_recorded "$version" "$recs"; then continue; fi
        f="$DIR/$version-$name.up.sql"
        echo "> up $version $name"
        if ! run_file "$f" "INSERT INTO $TABLE (version, name) VALUES ('$version', '$name')"; then
            echo "x migrate: $f failed at $where. It is not recorded and nothing after it ran; $FAILED_NOTE" >&2
            exit 1
        fi
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
        if ! run_file "$f" "DELETE FROM $TABLE WHERE version = '$version'"; then
            echo "x migrate: $f failed at $where. $version stays recorded; $FAILED_NOTE" >&2
            exit 1
        fi
        n=$((n + 1))
    done <<<"$list"
    echo "ok $where: $n down; $(status | tail -1)"
}

case "$cmd" in
    status)   status ;;
    up)       up "$arg" ;;
    down)     down "$arg" ;;
esac
