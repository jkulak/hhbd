#!/usr/bin/env bash
#
# The rules the migrations put on the schema, on the migrated local database, as the smoke
# workflow has it up:
#   - every table is InnoDB (#45)
#   - no `added` column, and not hhb_user_lyrics_edit.ule_action_timestamp, changes when its row
#     is updated (#48)
#   - a row inserted into the catalog without a time gets one
#   - no column defaults to a zero date
#   - a page view bumps `viewed` and leaves `added` and `updated` alone, so the catalog's
#     `updated` keeps meaning "last edited"
#   - an external id belongs to one row, and is compared byte for byte (#51)
#   - going down to the baseline brings the old schema back, and up removes it again
#
# It changes rows to prove these and ends with make reset-db, so the database ends as a reset
# leaves it.
#
# Usage: tests/schema-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-schematest.XXXXXX")
trap 'rm -rf "$T"' EXIT
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
col() { # col <where on information_schema.columns>: matching columns, as table.column
    sql "SELECT GROUP_CONCAT(CONCAT(table_name, '.', column_name) ORDER BY table_name SEPARATOR ' ') FROM information_schema.columns WHERE table_schema = DATABASE() AND $1"
}
count() { sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND $1"; }
engines() { # how many tables on each engine, as "engine count" pairs
    sql "SELECT GROUP_CONCAT(e ORDER BY e SEPARATOR ', ') FROM (SELECT CONCAT(engine, ' ', COUNT(*)) e FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' GROUP BY engine) x"
}
# Every migration after the baseline, so going down that many returns to it.
after_baseline=$(( $(find database/migrations -name '[0-9][0-9][0-9][0-9]-*.up.sql' | wc -l) - 1 ))

CATALOG="'albums','artists','songs','labels','cities','news'"
TIMES_WITH_ON_UPDATE="column_name IN ('added', 'ule_action_timestamp') AND extra LIKE '%on update%'"

echo "> make reset-db: the migrations, over the fixtures"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }
ok "make reset-db succeeds ($(tail -1 "$T/out" | sed 's/^ok //'))"

echo "> the schema"
check "every table is InnoDB, the migrations' own too" "InnoDB 47" "$(engines)"
check "no added (or ule_action_timestamp) changes on update" "NULL" "$(col "$TIMES_WITH_ON_UPDATE")"
check "the catalog's added defaults to the current time" "6" "$(count "table_name IN ($CATALOG) AND column_name = 'added' AND column_default = 'current_timestamp()'")"
check "no column defaults to a zero date" "NULL" "$(col "column_default LIKE '%0000-00-00%'")"
check "the catalog's updated has no automatic value" "NULL" "$(col "table_name IN ($CATALOG) AND column_name = 'updated' AND (extra LIKE '%on update%' OR column_default IS NOT NULL AND column_default <> 'NULL')")"

echo "> updating a row leaves its added alone"
for t in artists_photos ratings; do
    id=$(sql "SELECT MIN(id) FROM \`$t\`")
    sql "UPDATE \`$t\` SET added = '2001-01-01 00:00:00' WHERE id = $id"
    case $t in
        artists_photos) sql "UPDATE artists_photos SET description = 'changed by schema-test' WHERE id = $id" ;;
        ratings)        sql "UPDATE ratings SET rate = IF(rate = 5, 4, 5) WHERE id = $id" ;;
    esac
    check "$t: an update keeps added" "2001-01-01 00:00:00" "$(sql "SELECT added FROM \`$t\` WHERE id = $id")"
done

echo "> inserting a catalog row without a time"
sql "INSERT INTO cities (name) VALUES ('schema-test city')"
check "cities: added is the time of the insert" "1" "$(sql "SELECT added IS NOT NULL AND ABS(TIMESTAMPDIFF(SECOND, added, NOW())) <= 60 FROM cities WHERE name = 'schema-test city'")"

echo "> a page view through the site"
view() { # view <type> <table> <id>: what /stat does on a page view, and what it must not touch
    local type=$1 table=$2 id=$3 cols before after
    cols="added"; [ "$table" = news ] || cols="added, updated"
    # Known values, so "unchanged" means something even where the fixtures leave them NULL.
    sql "UPDATE \`$table\` SET added = '2002-02-02 02:02:02'$([ "$table" = news ] || echo ", updated = '2003-03-03 03:03:03'") WHERE id = $id"
    before=$(sql "SELECT CONCAT_WS('|', $cols), viewed FROM \`$table\` WHERE id = $id")
    curl -s -o /dev/null --max-time 10 "$URL/stat?type=$type&id=$id"
    after=$(sql "SELECT CONCAT_WS('|', $cols), viewed FROM \`$table\` WHERE id = $id")
    check "$table $id: a view leaves $cols as they were" "${before%%$'\t'*}" "${after%%$'\t'*}"
    check "$table $id: and counts the view" "$(( ${before##*$'\t'} + 1 ))" "${after##*$'\t'}"
}
view album albums 535
view artist artists 35
view song songs 7329
view label labels 58
view news news 1877

echo "> external ids"
sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('album', 535, 'discogs', 'master', '1234567')"
if sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('album', 536, 'discogs', 'master', '1234567')" >"$T/out" 2>&1; then
    bad "an id already on one row cannot go on another"
else
    ok "an id already on one row cannot go on another"
fi
sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('artist', 35, 'wikidata', 'item', 'q9346013'), ('artist', 36, 'wikidata', 'item', 'Q9346013')"
check "values differing only in case are two ids, whatever the tables' collation" "2" "$(sql "SELECT COUNT(*) FROM external_ids WHERE source = 'wikidata'")"
check "and a lookup finds only the one written that way" "36" "$(sql "SELECT entity_id FROM external_ids WHERE source = 'wikidata' AND kind = 'item' AND value = 'Q9346013'")"
check "the table keeps ids in utf8mb4, compared byte for byte" "utf8mb4_bin" "$(sql "SELECT table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'external_ids'")"
check "an id gets the time it was added" "1" "$(sql "SELECT added IS NOT NULL FROM external_ids WHERE value = '1234567'")"

echo "> down to the baseline brings the old schema back, and up removes it"
rows_before=$(sql "SELECT COUNT(*) FROM songs")
./scripts/migrate.sh down "$after_baseline" >"$T/out" 2>&1 || { bad "down $after_baseline succeeds"; cat "$T/out"; }
check "after down: 44 tables on MyISAM again, hhb_comments and the migrations' own on InnoDB" "InnoDB 2, MyISAM 44" "$(engines)"
check "after down: no row lost in the conversions" "$rows_before" "$(sql "SELECT COUNT(*) FROM songs")"
check "after down: no external_ids table" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'external_ids'")"
check "after down: added changes on update again, in 11 tables plus the lyrics log" "12" "$(count "$TIMES_WITH_ON_UPDATE")"
check "after down: the catalog's added has no default" "0" "$(count "table_name IN ($CATALOG) AND column_name = 'added' AND column_default = 'current_timestamp()'")"
check "after down: album_prices.added defaults to the zero date again" "album_prices.added" "$(col "column_default LIKE '%0000-00-00%'")"
./scripts/migrate.sh up >"$T/out" 2>&1 || { bad "up succeeds"; cat "$T/out"; }
check "after up again: every table InnoDB" "InnoDB 47" "$(engines)"
check "after up again: no row lost" "$rows_before" "$(sql "SELECT COUNT(*) FROM songs")"
check "after up again: no added changes on update" "0" "$(count "$TIMES_WITH_ON_UPDATE")"
check "after up again: no zero-date default" "NULL" "$(col "column_default LIKE '%0000-00-00%'")"

echo "> back to the fixtures"
if make -s reset-db >"$T/out" 2>&1; then ok "make reset-db leaves the database as the fixtures have it"; else bad "make reset-db leaves the database as the fixtures have it"; cat "$T/out"; fi

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
