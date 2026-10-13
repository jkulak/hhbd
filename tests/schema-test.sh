#!/usr/bin/env bash
#
# The rules the schema holds, and the data as the migrations left it, on the local database as
# make reset-db builds it, as the smoke workflow has it up:
#   - every table 0039 creates is there, and no trigger changes a row behind the application
#   - the catalog's `added` defaults to the time of the insert, `updated` has no automatic
#     value, and an update leaves `added` alone (#48)
#   - a page view bumps `viewed` and leaves `added` and `updated` alone, so the catalog's
#     `updated` keeps meaning "last edited"
#   - an external id belongs to one row, and is compared byte for byte (#51)
#   - provenance belongs to a run that exists, a run's report is JSON, and make import-runs
#     lists the runs (#52)
#   - the import is users row 1100, which nobody can log in as, and the documented query lists
#     what it added (#63)
#   - artist cities live in one table, the one the pages read, one row per pair (#64)
#   - every link table has a unique key, and the duplicates it once held are archived (#57)
#   - an artist with members is a band, type 'b', as the pages decide it (#65)
#   - a role name is unique, the role-less row 0 stays, and a credit without a role took one
#     only where the artist's other credits agreed (#66)
#   - every album has a release type (#53); an album's credits have a role and a position
#     (#58); a track's disc has its own column (#59)
#   - an album without a label has labelid NULL (#55)
#   - a release date is whole, with its precision, and the database refuses zero parts (#54)
#   - a cover file is described once per album, variant and hash (#60); an artist's photo
#     file appears once per artist (#61)
#   - the database is UTF8 and sorts in Polish (ICU's pl): Ż is not Z, case does not count in
#     a name, Polish order, and a four-byte character survives an insert and a page view (#71,
#     #232); a byte-compared table's text is COLLATE "C", and the JSON columns are json, which
#     refuses what is not JSON
#   - the search's unaccent(lower()) finds a name without its Polish letters (#232)
#   - the lookups the pages make by artist, album, label and views have indexes (#69)
#   - partial dates keep their year or month as a precision (#88)
#   - Polish letters once stored mangled are repaired (#27), and the image files production
#     lacks for good are no longer named (#47)
#   - two artists may share a name with different qualifiers, but a name without one is one
#     artist's (#102)
#   - the migrations go down to an empty database and up again to every table
#
# It changes rows to prove these and ends with make reset-db, so the database ends as a reset
# leaves it.
#
# Usage: tests/schema-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

URL=${1:-http://localhost:8080}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-schematest.XXXXXX")
trap 'rm -rf "$T"' EXIT
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }
# refuses <what> <pattern> <statement>: the statement fails, with an error that matches the
# pattern (grep -E), the key or the value it is refused for. A statement refused for anything
# else, a typo or a column that is not there, does not pass for the rule.
refuses() {
    if sql "$3" >"$T/out" 2>&1; then
        bad "$1"
    elif grep -qE -- "$2" "$T/out"; then
        ok "$1"
    else
        bad "$1, for want of /$2/"; cat "$T/out"
    fi
}

SCHEMA='current_schema()'
col() { # col <where on information_schema.columns>: matching columns, as table.column
    sql "SELECT $(agg "CONCAT(table_name, '.', column_name)" table_name ' ') FROM information_schema.columns WHERE table_schema = $SCHEMA AND $1"
}
count() { sql "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = $SCHEMA AND $1"; }
# ifnull <expression> <text>: the value, or the text where it is NULL; COALESCE wants both of
# one type
ifnull() { printf "COALESCE(CAST(%s AS text), '%s')" "$1" "$2"; }
# quoted <expression>: the value as an SQL literal, NULL unquoted
quoted() { printf 'quote_nullable(%s)' "$1"; }
# Each table's text columns by collation: "C" where they compare bytes, "default" for the
# database's Polish order, "C+default" for a table with both.
TEXT_COLLATIONS="SELECT table_name, string_agg(DISTINCT COALESCE(collation_name, 'default'), '+' ORDER BY COALESCE(collation_name, 'default')) AS c FROM information_schema.columns WHERE table_schema = current_schema() AND data_type IN ('text', 'character varying') GROUP BY table_name"
compares() { # compares <table>: how its text compares, C for bytes
    sql "SELECT c FROM ($TEXT_COLLATIONS) x WHERE table_name = '$1'"
}
BYTES=C

CATALOG="'albums','artists','songs','labels','cities','news'"
NOW_DEFAULT=CURRENT_TIMESTAMP

echo "> make reset-db: the migrations, then the fixtures"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }
ok "make reset-db succeeds ($(tail -1 "$T/out" | sed 's/^ok //'))"

echo "> the schema"
check "every table 0039 creates is there, and no other but the runner's" \
    "$(sed -n 's/^CREATE TABLE \([a-z_]*\) (.*/\1/p' database/migrations/0039-postgresql.up.sql | LC_ALL=C sort | paste -sd' ' -)" \
    "$(sql "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'" | LC_ALL=C sort | paste -sd' ' -)"
# No ON UPDATE behind the application: a trigger would be one, and there is none.
check "no trigger changes a row on update, added included" "0" "$(sql "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = current_schema()")"
check "the catalog's added defaults to the current time" "6" "$(count "table_name IN ($CATALOG) AND column_name = 'added' AND column_default = '$NOW_DEFAULT'")"
check "the catalog's updated has no automatic value" "NULL" "$(col "table_name IN ($CATALOG) AND column_name = 'updated' AND column_default IS NOT NULL")"

echo "> updating a row leaves its added alone"
for t in artists_photos ratings; do
    id=$(sql "SELECT MIN(id) FROM $t")
    sql "UPDATE $t SET added = '2001-01-01 00:00:00' WHERE id = $id"
    case $t in
        artists_photos) sql "UPDATE artists_photos SET description = 'changed by schema-test' WHERE id = $id" ;;
        ratings)        sql "UPDATE ratings SET rate = CASE WHEN rate = 5 THEN 4 ELSE 5 END WHERE id = $id" ;;
    esac
    check "$t: an update keeps added" "2001-01-01 00:00:00" "$(sql "SELECT added FROM $t WHERE id = $id")"
done

echo "> inserting a catalog row without a time"
sql "INSERT INTO cities (name) VALUES ('schema-test city')"
check "cities: added is the time of the insert" "1" "$(sql "SELECT CASE WHEN added BETWEEN NOW() - INTERVAL '1' MINUTE AND NOW() + INTERVAL '1' MINUTE THEN 1 ELSE 0 END FROM cities WHERE name = 'schema-test city'")"

echo "> a page view through the site (#68)"
BROWSER="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36"
# The site's day is Warsaw's; the database's CURRENT_DATE is UTC's, a day behind after 22:00.
TODAY=$(TZ=Europe/Warsaw date +%F)
viewed() { sql "SELECT CONCAT_WS(' ', (SELECT viewed FROM $2 WHERE id = $3), COALESCE((SELECT views FROM page_views WHERE entity_type = '$1' AND entity_id = $3 AND day = '$TODAY'), 0))"; }
view() { # view <type> <table> <id> <path>: a browser opens the page; what it counts, and what it must not touch
    local type=$1 table=$2 id=$3 path=$4 cols before after counted jar
    cols="added"; [ "$table" = news ] || cols="added, updated"
    jar="$T/jar-$type"
    # Known values, so "unchanged" means something even where the fixtures leave them NULL.
    sql "UPDATE $table SET added = '2002-02-02 02:02:02'$([ "$table" = news ] || echo ", updated = '2003-03-03 03:03:03'") WHERE id = $id"
    before=$(sql "SELECT CONCAT_WS('|', $cols) FROM $table WHERE id = $id")
    counted=$(viewed "$type" "$table" "$id")
    curl -s -o /dev/null --max-time 10 -A "$BROWSER" -c "$jar" -b "$jar" "$URL$path"
    after=$(sql "SELECT CONCAT_WS('|', $cols) FROM $table WHERE id = $id")
    check "$table $id: a view leaves $cols as they were" "$before" "$after"
    check "$table $id: and counts it, in the total and in the day's" "$(echo "$counted" | awk '{ print $1 + 1, $2 + 1 }')" "$(viewed "$type" "$table" "$id")"
    counted=$(viewed "$type" "$table" "$id")
    curl -s -o /dev/null --max-time 10 -A "$BROWSER" -c "$jar" -b "$jar" "$URL$path"
    curl -s -o /dev/null --max-time 10 -A "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)" "$URL$path"
    curl -s -o /dev/null --max-time 10 -I -A "$BROWSER" "$URL$path"
    check "$table $id: the same visit again, a crawler and a HEAD count nothing" "$counted" "$(viewed "$type" "$table" "$id")"
}
view album albums 535 /wdowa-superextra-a535.html
view artist artists 35 /mes-p35.html
view song songs 7329 /pogoda-s7329.html
view label labels 58 /alkopoligamia-l58.html
view news news 1877 /onar-jak-na-pierwszej-plycie-wideo-n1877.html
check "nothing answers at /stat, which let anyone add views" "404" "$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$URL/stat?type=album&id=535")"

echo "> the premieres of this day in earlier years (#197)"
# Today, Warsaw's, twenty years back: Superextra to the day, Podmiejski Gwar to the month alone;
# both get their dates back after, which later checks read.
dates=$(sql "SELECT $(agg "CONCAT('UPDATE albums SET year = ', $(quoted year), ', release_date_precision = ', $(quoted release_date_precision), ' WHERE id = ', id)" id '; ') FROM albums WHERE id IN (535, 50)")
sql "UPDATE albums SET year = '$(( ${TODAY:0:4} - 20 ))-${TODAY:5}', release_date_precision = 'day' WHERE id = 535; UPDATE albums SET year = '$(( ${TODAY:0:4} - 5 ))-${TODAY:5}', release_date_precision = 'month' WHERE id = 50"
on_this_day=$(curl -s --max-time 10 "$URL/" | sed -n '/id="on-this-day"/,/<\/div>/p' | tr -s ' \n' ' ')
check "the home page shows an album released on this day twenty years ago" "1" "$(grep -c '20 lat temu:</span> <a href="/wdowa-superextra-a535.html"> Wdowa - Superextra</a> (2006)' <<<"$on_this_day" || true)"
check "but not one whose day is not known" "0" "$(grep -c 'Podmiejski Gwar' <<<"$on_this_day" || true)"
sql "$dates"

echo "> external ids"
sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('album', 535, 'discogs', 'master', '1234567')"
refuses "an id already on one row cannot go on another" "PRIMARY|external_ids_pkey" \
    "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('album', 536, 'discogs', 'master', '1234567')"
sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('artist', 35, 'wikidata', 'item', 'q9346013'), ('artist', 36, 'wikidata', 'item', 'Q9346013')"
check "values differing only in case are two ids, whatever the tables' collation" "2" "$(sql "SELECT COUNT(*) FROM external_ids WHERE source = 'wikidata'")"
check "and a lookup finds only the one written that way" "36" "$(sql "SELECT entity_id FROM external_ids WHERE source = 'wikidata' AND kind = 'item' AND value = 'Q9346013'")"
check "the table compares ids byte for byte ($BYTES)" "$BYTES" "$(compares external_ids)"
check "an id gets the time it was added" "1" "$(sql "SELECT CASE WHEN added IS NOT NULL THEN 1 ELSE 0 END FROM external_ids WHERE source = 'discogs' AND kind = 'master' AND value = '1234567'")"

echo "> import runs and provenance"
check "make import-runs lists the run the fixtures hold" "fixtures.ndjson" "$(./scripts/import-runs.sh | tail -1 | awk '{ print $6 }')"
sql "INSERT INTO import_runs (batch, batch_sha256, mode) VALUES ('schema-test.ndjson', REPEAT('a', 64), 'apply')"
run=$(sql "SELECT MAX(id) FROM import_runs")
sql "INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, licence, fetched, run_id) VALUES ('album', 535, 'title', 'discogs', '1234567', 'CC0', '2026-10-09 12:00:00', $run), ('album', 535, 'cover', 'coverartarchive', 'https://coverartarchive.org/release/x/front', NULL, '2026-10-09 12:00:00', $run)"
refuses "provenance cannot point at a run that does not exist" "fk_import_provenance_run" \
    "INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, fetched, run_id) VALUES ('album', 535, 'year', 'discogs', '1', '2026-10-09 12:00:00', $((run + 1000)))"
refuses "a run's report has to be JSON" "import_runs.report|type json" "UPDATE import_runs SET report = '{not json' WHERE id = $run"
sql "UPDATE import_runs SET finished = started + INTERVAL '83' SECOND, created_count = 2, unchanged_count = 5, report = '{\"totals\": {\"created\": 2}}' WHERE id = $run"
check "make import-runs lists the run with its time, totals and provenance rows" \
    "$run 00:01:23 apply schema-test.ndjson aaaaaaaaaaaa 2 0 5 0 0 2" \
    "$(./scripts/import-runs.sh 1 | tail -1 | awk '{ $2 = ""; $3 = ""; print }' | tr -s ' ' | sed 's/^ //')"
check "both tables compare bytes, like external_ids" "import_provenance $BYTES import_runs $BYTES" "import_provenance $(compares import_provenance) import_runs $(compares import_runs)"
json_columns=$(col "data_type = 'json'")
check "every JSON column refuses what is not JSON" \
    "edit_journal.row_after edit_journal.row_before edit_operations.args import_runs.report migration_archive.row_data review_items.detail review_items.undo_data" \
    "$(tr ' ' '\n' <<<"$json_columns" | LC_ALL=C sort | paste -sd' ' -)"

echo "> the import in addedby"
check "users has the import as 1100, with no password" "import, no password" "$(sql "SELECT CONCAT(login, CASE WHEN pass IS NULL THEN ', no password' ELSE ', a password' END) FROM users WHERE ID = 1100")"
sql "UPDATE artists SET addedby = 1100 WHERE id = 35"
sql "UPDATE labels SET addedby = 1100 WHERE id = 58"
# Albums 779 and 780, the fixtures' unpublished one and self-release, are the import's too (#168).
check "the README's query lists what the import added" "artist 35 label 58 album 779 album 780" "$(sql "SELECT 'album' AS type, id, title AS name, added FROM albums WHERE addedby = 1100 UNION ALL SELECT 'artist', id, name, added FROM artists WHERE addedby = 1100 UNION ALL SELECT 'label', id, name, added FROM labels WHERE addedby = 1100 ORDER BY added, type, id" | awk -F'\t' '{ printf "%s%s %s", sep, $1, $2; sep = " " }')"

echo "> one artist-city table"
pairs() { sql "SELECT $(agg "CONCAT(cityid, ':', artistid, ':', status)" "cityid, artistid, status" ' ') FROM $1"; }
check "the old table is gone" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $SCHEMA AND table_name = 'city_artist_lookup'")"
check "its pairs are in the table the pages read, once each, without the orphan" "1:1:999 2:35:999 3:46:0" "$(pairs artist_city_lookup)"
check "the archive holds every old row and every pair put in" "artist_city_lookup inserted 2, city_artist_lookup deleted 5" "$(sql "SELECT $(agg "CONCAT(table_name, ' ', action, ' ', n)" table_name ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0010' GROUP BY table_name, action) a")"
refuses "a pair cannot be stored twice" "u_artist_city_lookup" "INSERT INTO artist_city_lookup (cityid, artistid) VALUES (2, 35)"

echo "> unique link tables"
UNIQUE_TABLES="album_artist_lookup altnames_lookup artist_city_lookup artist_lookup band_lookup city_label_lookup collection feature_lookup music_lookup ratings remix_lookup scratch_lookup wishlist"
uniques() {
    sql "SELECT $(agg tablename tablename ' ') FROM (SELECT DISTINCT tablename FROM pg_indexes WHERE schemaname = current_schema() AND indexname LIKE 'u\\_%' AND indexdef LIKE 'CREATE UNIQUE INDEX %' AND (tablename LIKE '%\\_lookup' OR tablename IN ('collection', 'wishlist', 'ratings'))) x"
}
dupes() { # the duplicated rows of the fixtures, as table:count
    sql "SELECT CONCAT_WS(' ',
        CONCAT('album_artist:', (SELECT $(agg status status) FROM album_artist_lookup WHERE albumid = 535 AND artistid = 8)),
        CONCAT('band:', (SELECT COUNT(*) FROM band_lookup WHERE artistid = 2 AND bandid = 22)),
        CONCAT('altnames:', (SELECT COUNT(*) FROM altnames_lookup WHERE artistid = 4 AND altname = 'Tede')),
        CONCAT('artist:', (SELECT COUNT(*) FROM artist_lookup WHERE songid = 7329 AND artistid = 8)),
        CONCAT('collection:', (SELECT $(agg id id) FROM collection WHERE albumid = 535 AND userid = 1)),
        CONCAT('ratings:', (SELECT $(agg "CONCAT(id, '@', added)" id) FROM ratings WHERE albumid = 535 AND userid = 1)))"
}
check "every link table has its unique key" "$UNIQUE_TABLES" "$(uniques)"
check "one row per key is left, the published copy where they differed, the oldest where rows have ids" \
    "album_artist:999 band:1 altnames:1 artist:1 collection:1 ratings:1@$(sql "SELECT added FROM ratings WHERE ID = 1")" "$(dupes)"
check "the archive holds every copy and every row put back" \
    "album_artist_lookup deleted 3, album_artist_lookup inserted 1, altnames_lookup deleted 2, altnames_lookup inserted 1, artist_lookup deleted 2, artist_lookup inserted 1, band_lookup deleted 2, band_lookup inserted 1, collection deleted 1, ratings deleted 1" \
    "$(sql "SELECT $(agg "CONCAT(table_name, ' ', action, ' ', n)" "table_name, action" ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0011' GROUP BY table_name, action) a")"
refuses "a link cannot be stored twice" "u_band_lookup" "INSERT INTO band_lookup (artistid, bandid) VALUES (2, 22)"

echo "> bands"
types() { sql "SELECT $(agg "CONCAT(id, ':', type)" id ' ') FROM artists WHERE id BETWEEN 60 AND 63"; }
BANDS_WITH_OTHER_TYPES="SELECT COUNT(*) FROM artists a WHERE a.type <> 'b' AND a.id IN (SELECT b.bandid FROM band_lookup b JOIN artists m ON m.id = b.artistid)"
check "every artist with members is typed 'b'" "0" "$(sql "$BANDS_WITH_OTHER_TYPES")"
check "the two that were not are now; a 'b' without members and an artist whose member is missing keep theirs" "60:b 61:b 62:b 63:x" "$(types)"
check "the archive holds both old types" "60:x 61:m" "$(sql "SELECT $(agg "CONCAT($(json_get row_data id), ':', $(json_get row_data type))" id ' ') FROM migration_archive WHERE version = '0012'")"

echo "> roles"
credits() { sql "SELECT $(agg "CONCAT(songid, ':', artistid, ':', feattype)" songid ' ') FROM feature_lookup WHERE (songid, artistid) IN ((6, 36), (7, 41), (10, 42))"; }
FEATTYPES_KEY="SELECT COUNT(*) FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'feattypes' AND indexname = 'u_feattypes'"
roles() { sql "SELECT CONCAT((SELECT COUNT(*) FROM feattypes WHERE id = 0 AND feattype IS NULL), ' row 0, ', (SELECT COUNT(*) FROM feattypes WHERE feattype = 'testest'), ' testest, ', ($FEATTYPES_KEY), ' key')"; }
check "row 0 stays, the test role is gone, and role names have a unique key" "1 row 0, 0 testest, 1 key" "$(roles)"
check "a credit without a role takes the artist's only other role; the others stay without one" "6:36:1 7:41:0 10:42:0" "$(credits)"
check "the archive holds the deleted role and the changed credit" "feattypes deleted 1, feature_lookup changed 1" "$(sql "SELECT $(agg "CONCAT(table_name, ' ', action, ' ', n)" table_name ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0013' GROUP BY table_name, action) a")"
refuses "a role name cannot be stored twice, whatever its case" "u_feattypes" "INSERT INTO feattypes (feattype) VALUES ('SCRATCH')"

echo "> release types"
check "the flagged albums are a single and an EP, the rest albums" "2:single 46:ep album:$(sql "SELECT COUNT(*) - 2 FROM albums")" "$(sql "SELECT CONCAT((SELECT $(agg "CONCAT(id, ':', release_type)" id ' ') FROM albums WHERE release_type <> 'album'), ' album:', (SELECT COUNT(*) FROM albums WHERE release_type = 'album'))")"
check "nothing is digital until someone says so" "0" "$(sql "SELECT COUNT(*) FROM albums WHERE media_digital <> 0 OR catalog_digital IS NOT NULL")"

echo "> album credits"
check "an album credited to two artists has them in positions 1 and 2, both main" "1:main:1 2:main:2" "$(sql "SELECT $(agg "CONCAT(artistid, ':', role, ':', position)" position ' ') FROM album_artist_lookup WHERE albumid = 1")"

echo "> discs"
tracks() { sql "SELECT $(agg "CONCAT(albumid, ':', songid, ':', $1)" "albumid, songid" ' ') FROM album_lookup WHERE albumid IN (3, 4, 9999)"; }
check "no track number is 0 or encodes a disc" "0" "$(sql "SELECT COUNT(*) FROM album_lookup WHERE track = 0 OR track >= 100")"
check "the two-disc album's tracks are on discs 1 and 2, the track at 0 is last, the missing album's are gone" "3:11:1-1 3:12:2-1 4:13:1-1 4:14:1-2 4:30:1-3" "$(tracks "CONCAT(disc, '-', track)")"
check "the archive holds every row of the albums it touched, as they were" "3:2 4:3 9999:2" "$(sql "SELECT $(agg "CONCAT(a, ':', n)" a ' ') FROM (SELECT $(json_get row_data albumid) a, COUNT(*) n FROM migration_archive WHERE version = '0016' GROUP BY a) x")"

echo "> no label"
check "the placeholder label is gone and its album has no label" "0 NULL" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM labels WHERE id = 27), ' ', $(ifnull "(SELECT labelid FROM albums WHERE id = 48)" NULL))")"

echo "> release dates"
dates() { sql "SELECT $(agg "CONCAT(id, ':', $(ifnull year NULL), ':', $1)" id ' ') FROM albums WHERE id IN (49, 50, 778, 923)"; }
# The date type has no zero date to hold, and refuses one, below.
check "zero parts became a precision, 0000-00-00 no date, the guessed announcement announced, the placeholder gone" \
    "49:2016-12-01:month:1 50:2013-01-01:year:0 778:NULL:day:0" "$(dates "CONCAT(release_date_precision, ':', announced)")"
refuses "the database refuses a date with zero parts" "2020-05-00" "UPDATE albums SET year = '2020-05-00' WHERE id = 50"

echo "> album covers"
sql "INSERT INTO album_covers (albumid, variant, path, width, height, sha256, mime, source) VALUES (535, '300', 'a/x.jpg', 300, 300, REPEAT('b', 64), 'image/jpeg', 'legacy')"
refuses "the same file cannot describe one album's variant twice" "u_album_covers" \
    "INSERT INTO album_covers (albumid, variant, path, width, height, sha256, mime, source) VALUES (535, '300', 'a/y.jpg', 300, 300, REPEAT('b', 64), 'image/jpeg', 'legacy')"

echo "> artist photos"
INT=integer
check "artistid is an int, and a photo has room for its size, hash, licence and credit" "$INT 8" "$(sql "SELECT CONCAT((SELECT data_type FROM information_schema.columns WHERE table_schema = $SCHEMA AND table_name = 'artists_photos' AND column_name = 'artistid'), ' ', (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = $SCHEMA AND table_name = 'artists_photos' AND column_name IN ('width', 'height', 'sha256', 'mime', 'licence', 'licence_url', 'credit', 'modified')))")"
sql "UPDATE artists_photos SET sha256 = REPEAT('c', 64) WHERE id = 1"
refuses "the same file cannot be one artist's photo twice" "u_artists_photos" \
    "INSERT INTO artists_photos (artistid, filename, description, source, sourceurl, sha256) SELECT artistid, 'copy.jpg', '', '', '', sha256 FROM artists_photos WHERE id = 1"

echo "> UTF8, sorting in Polish by ICU's pl"
check "the database is UTF8 and sorts in Polish, by ICU's pl-PL" "UTF8 i pl-PL" "$(sql "SELECT CONCAT_WS(' ', pg_encoding_to_char(encoding), datlocprovider, datlocale) FROM pg_database WHERE datname = current_database()")"
check "every table's text sorts in Polish, but the byte-compared ones'; none has both" "C 9, default 27" "$(sql "SELECT $(agg "CONCAT(c, ' ', n)" c ', ') FROM (SELECT c, COUNT(*) n FROM ($TEXT_COLLATIONS) x GROUP BY c) y")"
check "text sorts in Polish: ą after a, Ć after C, Ł after L" "az ąb Cma Ćma Lona Łona" "$(sql "SELECT $(agg n n ' ') FROM (VALUES ('Łona'), ('Cma'), ('ąb'), ('Lona'), ('Ćma'), ('az')) v(n)")"
if sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('Zabson', 'zabson-2', 'm', 999, '', '')" >"$T/out" 2>&1; then
    ok "Zabson is a name of its own next to Żabson"
else
    bad "Zabson is a name of its own next to Żabson"; cat "$T/out"
fi
refuses "żabson is Żabson in another case, and refused" "u_artists_name" "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('żabson', 'zabson-3', 'm', 999, '', '')"
refuses "and ZABSON is Zabson" "u_artists_name" "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('ZABSON', 'zabson-4', 'm', 999, '', '')"
sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('Łoś Testowy', 'los-testowy', 'm', 999, '', ''), ('Lux Testowy', 'lux-testowy', 'm', 999, '', ''), ('Mazur Testowy', 'mazur-testowy', 'm', 999, '', ''), ('Zenek Testowy', 'zenek-testowy', 'm', 999, '', '')"
check "names sort in Polish order: L before Ł before M, Z before Ż" "Lux Testowy|Łoś Testowy|Mazur Testowy|Zabson|Zenek Testowy|Żabson" "$(sql "SELECT $(agg name name '|') FROM artists WHERE name IN ('Lux Testowy', 'Łoś Testowy', 'Mazur Testowy', 'Zabson', 'Zenek Testowy', 'Żabson')")"
# The search's LIKE (Jkl_Db::searchLike), with the unaccent 0039 installs; the smoke test
# searches through the site.
check "the search finds Łoś Testowy from LOS TEST, without case or Polish letters" "Łoś Testowy" "$(sql "SELECT name FROM artists WHERE unaccent(lower(name)) LIKE unaccent(lower('%LOS TEST%'))")"
if sql "INSERT INTO labels (name, urlname, logo) VALUES ('Łódź Testowa', 'lodz-testowa', ''), ('Lodz Testowa', 'lodz-testowa-2', '')" >"$T/out" 2>&1; then
    ok "a label's name is its own by its Polish letters: Łódź Testowa and Lodz Testowa"
else
    bad "a label's name is its own by its Polish letters: Łódź Testowa and Lodz Testowa"; cat "$T/out"
fi
refuses "but not by case: ŁÓDŹ TESTOWA is Łódź Testowa" "'name'|u_labels_name" "INSERT INTO labels (name, urlname, logo) VALUES ('ŁÓDŹ TESTOWA', 'lodz-testowa-3', '')"
sql "INSERT INTO artists (id, name, urlname, type, status, trivia, website) VALUES (9001, 'Mikrofon 🎤', 'mikrofon', 'm', 999, '', '')"
check "a four-byte character comes back from the database" "Mikrofon 🎤" "$(sql "SELECT name FROM artists WHERE id = 9001")"
# The page whole, then the test: piped into grep -q, which leaves at the first match, curl still
# writing failed with 23, and pipefail made that a failed check now and then on Linux.
page=$(curl -s -L --max-time 10 -w '\n%{http_code}' "$URL/mikrofon-p9001.html" || true)
if [[ "$page" == *🎤* ]]; then
    ok "and from the artist's page"
else
    bad "and from the artist's page (HTTP ${page##*$'\n'})"
fi
sql "DELETE FROM artists WHERE id = 9001 OR name IN ('Zabson', 'Lux Testowy', 'Łoś Testowy', 'Mazur Testowy', 'Zenek Testowy')"
sql "DELETE FROM labels WHERE name IN ('Łódź Testowa', 'Lodz Testowa')"

echo "> indexes the pages' joins use"
JOIN_INDEXES="'i_album_artist_lookup_artistid', 'i_artist_lookup_artistid', 'i_feature_lookup_artistid', 'i_music_lookup_artistid', 'i_scratch_lookup_artistid', 'i_remix_lookup_artistid', 'i_album_lookup_albumid', 'i_albums_labelid', 'i_songs_viewed'"
# Each index as table.columns, a line each
indexed=$(sql "SELECT CONCAT(tablename, '.', replace(regexp_replace(indexdef, '.* USING btree \((.*)\)$', '\1'), ', ', ',')) FROM pg_indexes WHERE schemaname = current_schema() AND indexname IN ($JOIN_INDEXES)")
check "lookups by artist, by album, by label and by views have their indexes (#69)" \
    "album_artist_lookup.artistid album_lookup.albumid,disc,track albums.labelid artist_lookup.artistid feature_lookup.artistid music_lookup.artistid remix_lookup.artistid scratch_lookup.artistid songs.viewed" \
    "$(LC_ALL=C sort <<<"$indexed" | paste -sd' ' -)"

echo "> partial dates (#88)"
check "a band's start and end: a year, a month, not known" "22:1998-01-01:year:- 23:1998-03-01:month:2003-01-01:year" \
    "$(sql "SELECT $(agg "CONCAT_WS(':', id, since, since_precision, $(ifnull till -), CASE WHEN till IS NULL THEN NULL ELSE till_precision END)" id ' ') FROM artists WHERE id IN (22, 23)")"
check "a member's join and departure" "1998-01-01 year 2003-12-01 month" \
    "$(sql "SELECT CONCAT_WS(' ', insince, insince_precision, awaysince, awaysince_precision) FROM band_lookup WHERE artistid = 35 AND bandid = 23")"
check "a user's unknown times, and a news item's, are NULL" "8:NULL 9:NULL:NULL 2:NULL" \
    "$(sql "SELECT CONCAT('8:', $(ifnull usr_added NULL), ' 9:', $(ifnull "(SELECT usr_updated FROM hhb_users WHERE usr_id = 9)" NULL), ':', $(ifnull "(SELECT usr_last_login FROM hhb_users WHERE usr_id = 9)" NULL), ' 2:', $(ifnull "(SELECT expires FROM news WHERE ID = 2)" NULL)) FROM hhb_users WHERE usr_id = 8")"
if sql "INSERT INTO hhb_users (usr_email, usr_password, usr_display_name, usr_added) VALUES ('nowy@example.com', MD5('x'), 'Nowy', NOW())" >"$T/out" 2>&1; then
    ok "a registration, which writes only its added time, goes in"
else
    bad "a registration, which writes only its added time, goes in"; cat "$T/out"
fi
sql "DELETE FROM hhb_users WHERE usr_email = 'nowy@example.com'"

echo "> Polish letters stored mangled (#27)"
check "a name mangled twice over, and a title in ISO-8859-2 read as latin-1, come back" "JŹW|Właśnie tak się bawi..." \
    "$(sql "SELECT CONCAT((SELECT altname FROM altnames_lookup WHERE artistid = 35), '|', (SELECT title FROM songs WHERE id = 6190))")"

echo "> image files production lacks for good"
check "an album's lost cover and an artist's lost photo are no longer named (#47)" "576: 0 1" \
    "$(sql "SELECT CONCAT((SELECT CONCAT(id, ':', cover) FROM albums WHERE id = 576), ' ', (SELECT COUNT(*) FROM artists_photos WHERE id = 120), ' ', (SELECT COUNT(*) FROM migration_archive WHERE version = '0029' AND table_name = 'artists_photos'))")"

echo "> artists who share a name"
# A key on lower() of both: neither the name nor its qualifier counts case (0039).
check "an artist's name is unique with its qualifier, not alone" "lower((name)::text), lower((disambiguation)::text)" \
    "$(sql "SELECT regexp_replace(indexdef, '.* USING btree \((.*)\)$', '\1') FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'artists' AND indexname = 'u_artists_name' AND indexdef LIKE 'CREATE UNIQUE INDEX %'")"
check "two Solars stand side by side, each with its qualifier" "64:Solar:SBM Label 65:Solar:raper z Poznania" \
    "$(sql "SELECT $(agg "CONCAT(id, ':', name, ':', disambiguation)" id ' ') FROM artists WHERE id IN (64, 65)")"
if sql "INSERT INTO artists (id, name, urlname, type, status, trivia, website) VALUES (9002, 'Solar', 'solar', 'm', 999, '', '')" >"$T/out" 2>&1; then
    ok "a third Solar without a qualifier can join them"
else
    bad "a third Solar without a qualifier can join them"; cat "$T/out"
fi
refuses "but not a fourth: a name without a qualifier is still one artist's" "u_artists_name" \
    "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('solar', 'solar-2', 'm', 999, '', '')"
refuses "and a qualifier names one artist of a name" "u_artists_name" \
    "INSERT INTO artists (name, disambiguation, urlname, type, status, trivia, website) VALUES ('Solar', 'sbm label', 'solar-3', 'm', 999, '', '')"
sql "DELETE FROM artists WHERE id = 9002"

echo "> the migrations down to an empty database, and up again"
# Tables, indexes, and the unaccent the search needs
built() { sql "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'), (SELECT COUNT(*) FROM pg_indexes WHERE schemaname = current_schema()), (SELECT COUNT(*) FROM pg_extension WHERE extname = 'unaccent'))"; }
before=$(built)
./scripts/migrate.sh down all >"$T/out" 2>&1 || { bad "down all succeeds"; cat "$T/out"; }
check "after down: nothing but the runner's table and its key, no unaccent" "1 1 0" "$(built)"
./scripts/migrate.sh up >"$T/out" 2>&1 || { bad "up succeeds"; cat "$T/out"; }
check "after up again: every table, index and unaccent back" "$before" "$(built)"

echo "> back to the fixtures"
if make -s reset-db >"$T/out" 2>&1; then ok "make reset-db leaves the database as the fixtures have it"; else bad "make reset-db leaves the database as the fixtures have it"; cat "$T/out"; fi
echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
