#!/usr/bin/env bash
#
# An admin's edits through make edit (#115), on the running local stack with the fixtures:
#   - a call without BY, without WHY, or with BY naming no admin is refused, and writes nothing
#   - a dry run, the default, prints what an apply would change and writes nothing
#   - merging two albums, merging two artists, deleting an album, an artist and a label, and
#     setting a field each do what they say, leave no row naming what went, and journal one
#     row per row changed
#   - a merged album's page answers 301 with the album kept
#   - undoing each of them gives back the data exactly: a dump taken before the operation and
#     one taken after the undo are the same
#   - an operation is undone once, and an undo is not undone
#   - the merges and deletes know every albumid and labelid column the schema has
#
# It ends with make reset-db, so the database ends as a reset leaves it.
#
# Usage: tests/edit-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-edittest.XXXXXX")
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
# Every row of every table but the journal's own, one INSERT a line, sorted: a table without a
# primary key gives its rows back in another order after an undo, and that is no difference.
dump() {
    # shellcheck disable=SC2016 # expanded in the container
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb-dump -uroot --no-create-info --skip-extended-insert --skip-comments --skip-dump-date --ignore-table="$MYSQL_DATABASE.edit_operations" --ignore-table="$MYSQL_DATABASE.edit_journal" "$MYSQL_DATABASE"' | grep '^INSERT' | sort
}
# edit <name> <DO> [MODE] [VALUE]: make edit as the fixtures' admin; output in $T/<name>, make's
# status echoed (2 for any failure of the tool)
edit() {
    local status=0
    if [ $# -ge 4 ]; then
        DO="$2" MODE="${3:-}" VALUE="$4" BY=Admin WHY="edit-test: $1" make -s edit >"$T/$1" 2>&1 || status=$?
    else
        DO="$2" MODE="${3:-}" BY=Admin WHY="edit-test: $1" make -s edit >"$T/$1" 2>&1 || status=$?
    fi
    echo "$status"
}
last_operation() { sql "SELECT MAX(id) FROM edit_operations"; }
changes() { grep -c '^[-~+] ' "$T/$1" || true; }
journalled() { sql "SELECT COUNT(*) FROM edit_journal WHERE operation_id = $1"; }
# How many rows name album, artist or label $2 anywhere: every column of that name, and the
# tables that name a row by type and id.
naming() {
    local kind=$1 id=$2 columns type_a type_p
    case "$kind" in
        album)  columns="'albumid', 'epfor'"; type_a=album; type_p=a ;;
        artist) columns="'artistid', 'bandid', 'aid'"; type_a=artist; type_p=p ;;
        label)  columns="'labelid'"; type_a=label; type_p=l ;;
    esac
    sql "SELECT $(sql "SELECT GROUP_CONCAT(CONCAT('(SELECT COUNT(*) FROM \`', table_name, '\` WHERE \`', column_name, '\` = $id)') SEPARATOR ' + ') FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name IN ($columns)") + (SELECT COUNT(*) FROM external_ids WHERE entity_type = '$type_a' AND entity_id = $id) + (SELECT COUNT(*) FROM import_provenance WHERE entity_type = '$type_a' AND entity_id = $id) + (SELECT COUNT(*) FROM review_items WHERE entity_type = '$type_a' AND entity_id = $id) + (SELECT COUNT(*) FROM hhb_comments WHERE com_object_type = '$type_p' AND com_object_id = $id)"
}
# apply <name> <DO>: the operation applied, and its journal checked against what it printed
apply() {
    dump >"$T/$1.before"
    check "$1: applied" "0" "$(edit "$1" "$2" apply)"
    operation=$(last_operation)
    check "$1: one journal row per row changed ($(changes "$1") rows)" "$(changes "$1")" "$(journalled "$operation")"
}
# undo <name>: the last operation undone, and the data compared with the dump before it
undo() {
    check "$1: undone" "0" "$(edit "$1-undo" "undo $operation" apply)"
    dump >"$T/$1.after"
    if diff -q "$T/$1.before" "$T/$1.after" >/dev/null; then
        ok "$1: after the undo the data is as it was before, row for row"
    else
        bad "$1: after the undo the data is as it was before, row for row"
        diff "$T/$1.before" "$T/$1.after" | head -10
    fi
}

cleanup() {
    make -s reset-db >/dev/null 2>&1 || echo "x make reset-db failed on the way out" >&2
    rm -rf "$T"
}
trap cleanup EXIT

echo "> make reset-db"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }

echo "> the merges and deletes know every column that names an album or a label"
covered=$(docker compose exec -T app php -r 'class Jkl_Model_Api {} require "/var/www/html/app/application/models/Edit/Api.php"; foreach (array_merge(Model_Edit_Api::ALBUM_COLUMNS, Model_Edit_Api::LABEL_COLUMNS) as $t => $cs) { foreach ($cs as $c) { echo "$t.$c\n"; } } echo "albums.epfor\nalbums.labelid\n";' | sort | paste -sd' ' -)
check "every albumid and labelid column, and albums.epfor" \
    "$(sql "SELECT CONCAT(table_name, '.', column_name) FROM information_schema.columns WHERE table_schema = DATABASE() AND (column_name IN ('albumid', 'labelid') OR (table_name = 'albums' AND column_name = 'epfor')) AND table_name NOT IN ('album_merges')" | sort | paste -sd' ' -)" "$covered"

echo "> calls that do not count"
check "without BY: refused" "2 every edit says who makes it and why" \
    "$(DO="delete-album 2" WHY="x" make -s edit >"$T/out" 2>&1; echo "$? $(grep -o 'every edit says who makes it and why' "$T/out")")"
check "without WHY: refused" "2 every edit says who makes it and why" \
    "$(DO="delete-album 2" BY=Admin make -s edit >"$T/out" 2>&1; echo "$? $(grep -o 'every edit says who makes it and why' "$T/out")")"
check "BY naming a user who is no admin: refused" "2 is no hhbd admin" \
    "$(DO="delete-album 2" BY=TestUser1 WHY="x" make -s edit >"$T/out" 2>&1; echo "$? $(grep -o 'is no hhbd admin' "$T/out")")"
check "and none of them wrote an operation" "0" "$(sql "SELECT COUNT(*) FROM edit_operations")"

echo "> a dry run"
dump >"$T/dry.before"
check "the dry run goes through" "0" "$(edit dry "merge-albums 2 1")"
check "and prints what an apply would change" "1 1" "$(grep -c '^- albums {"id":"2"}' "$T/dry") $(grep -c '^dry run of merge-albums' "$T/dry")"
dump >"$T/dry.after"
check "but changes nothing, and journals nothing" "same 0" \
    "$(diff -q "$T/dry.before" "$T/dry.after" >/dev/null && echo same) $(sql "SELECT COUNT(*) FROM edit_journal")"

echo "> merging two albums"
apply merge-albums "merge-albums 2 1"
check "merge-albums: album 2 is gone, and nothing names it" "0 0" "$(sql "SELECT COUNT(*) FROM albums WHERE id = 2") $(naming album 2)"
check "merge-albums: its tracks, its EP and its redirect are album 1's" "2 1 1" \
    "$(sql "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM album_lookup WHERE albumid = 1 AND songid IN (9, 10)), (SELECT COUNT(*) FROM albums WHERE id = 46 AND epfor = 1), (SELECT COUNT(*) FROM album_merges WHERE id = 2 AND into_id = 1))")"
check "merge-albums: its page answers 301 with album 1's" "301 /pezet-jestem-hip-hopem-a1.html" \
    "$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$URL/x-a2.html" | sed "s|$URL||")"
undo merge-albums
check "an operation is undone once" "2 was undone already" \
    "$(edit undo-twice "undo $operation" apply) $(grep -o 'was undone already' "$T/undo-twice")"
check "and an undo is not undone" "2 An undo is not undone" \
    "$(edit undo-undo "undo $(last_operation)" apply) $(grep -o 'An undo is not undone' "$T/undo-undo")"

echo "> merging two artists"
apply merge-artists "merge-artists 35 2"
check "merge-artists: Mes is gone, nothing names him, his page redirects to Eldo's" "0 0 1" \
    "$(sql "SELECT COUNT(*) FROM artists WHERE id = 35") $(naming artist 35) $(sql "SELECT COUNT(*) FROM artist_merges WHERE id = 35 AND into_id = 2")"
undo merge-artists

echo "> deleting an album, an artist, a label"
apply delete-album "delete-album 535"
check "delete-album: Superextra is gone, and nothing names it" "0 0" "$(sql "SELECT COUNT(*) FROM albums WHERE id = 535") $(naming album 535)"
undo delete-album
apply delete-artist "delete-artist 4"
check "delete-artist: the artist is gone, and nothing names him" "0 0" "$(sql "SELECT COUNT(*) FROM artists WHERE id = 4") $(naming artist 4)"
undo delete-artist
apply delete-label "delete-label 58"
check "delete-label: the label is gone, nothing names it, its albums stay" "0 0 1" \
    "$(sql "SELECT COUNT(*) FROM labels WHERE id = 58") $(naming label 58) $(sql "SELECT COUNT(*) FROM albums WHERE id = 535")"
undo delete-label

echo "> setting a field"
dump >"$T/set.before"
check "set: applied" "0" "$(DO="set albums 1 title" VALUE='Jestem "Hip Hopem"' MODE=apply BY=Admin WHY="edit-test: a title with quotes" make -s edit >"$T/set" 2>&1; echo $?)"
check "set: the value went in as given, with the admin as updatedby" 'Jestem "Hip Hopem" 10' "$(sql "SELECT CONCAT(title, ' ', updatedby) FROM albums WHERE id = 1")"
check "set: undone" "0" "$(edit set-undo "undo $(last_operation)" apply)"
check "set: NULL without VALUE" "0 NULL" "$(DO="set artists 2 realname" MODE=apply BY=Admin WHY="edit-test: null" make -s edit >/dev/null 2>&1; echo "$? $(sql "SELECT IFNULL(realname, 'NULL') FROM artists WHERE id = 2")")"
check "set: undone too" "0" "$(edit set-null-undo "undo $(last_operation)" apply)"
dump >"$T/set.after"
check "set: the data as it was before" "same" "$(diff -q "$T/set.before" "$T/set.after" >/dev/null && echo same)"
check "set refuses the id, and a column the row lacks" "2 2" \
    "$(edit set-id "set albums 1 id" apply x) $(edit set-none "set albums 1 nope" apply x)"

echo "> the journal says who, when, why and the path"
check "every operation has its admin, a time, its reason and the command line" "0" \
    "$(sql "SELECT COUNT(*) FROM edit_operations WHERE user_id <> 10 OR created IS NULL OR why NOT LIKE 'edit-test: %' OR path <> 'cli'")"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
