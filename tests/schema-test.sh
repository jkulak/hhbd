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
#   - provenance belongs to a run that exists, a run's report is JSON, and make import-runs
#     lists the runs (#52)
#   - the import is users row 1100, which nobody can log in as, and the documented query lists
#     what it added (#63)
#   - artist cities live in one table, the one the pages read, one row per pair (#64); the
#     old table's rows wait in migration_archive and come back with the down
#   - every link table has a unique key, and its duplicates are archived and come back with
#     the down (#57)
#   - an artist with members is a band, type 'b', as the pages decide it (#65)
#   - a role name is unique, the role-less row 0 stays, and a credit without a role takes one
#     only where the artist's other credits agree (#66)
#   - every album has a release type: a flagged one with one to three tracks is a single, any
#     other flagged one an EP (#53)
#   - an album's credits have a role and a position, in artist id order where there were
#     several (#58)
#   - a track's disc has its own column: no track number is 0 or encodes a disc, and the
#     tracks of an album that does not exist are gone (#59)
#   - an album without a label has labelid NULL; the placeholder label 27 is gone (#55)
#   - a release date is whole, with its precision, and the database refuses zero parts; an
#     unconfirmed announcement is announced, and the 2017 placeholders are gone (#54)
#   - a cover file is described once per album, variant and hash (#60)
#   - an artist's photo file appears once per artist; artistid is an int (#61)
#   - the catalogue is utf8mb4 with the Polish collation: Ż is not Z, case does not count,
#     Polish order, and a four-byte character survives an insert and a page view (#71)
#   - the lookups the pages make by artist, album, label and views have indexes (#69)
#   - the image files production lacks for good are no longer named, and the down names them
#     again (#47)
#   - no date, datetime or timestamp column holds a zero part, the server runs with
#     NO_ZERO_IN_DATE and NO_ZERO_DATE, the CHECKs refuse one even in a session that allows it,
#     partial dates keep their year or month as a precision, and the downs put every zero date
#     back (#88)
#   - two artists may share a name with different qualifiers, but a name without one is one
#     artist's; the down folds the qualifier into the name, refuses before changing anything
#     when that name is taken, and the up splits it again, unless the archive went too (#102)
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
tables() { sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"; }
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
check "every table is InnoDB, the migrations' own too" "InnoDB $(tables)" "$(engines)"
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
check "an id gets the time it was added" "1" "$(sql "SELECT added IS NOT NULL FROM external_ids WHERE source = 'discogs' AND kind = 'master' AND value = '1234567'")"

echo "> import runs and provenance"
check "make import-runs lists the run the fixtures hold" "fixtures.ndjson" "$(./scripts/import-runs.sh | tail -1 | awk '{ print $6 }')"
sql "INSERT INTO import_runs (batch, batch_sha256, mode) VALUES ('schema-test.ndjson', REPEAT('a', 64), 'apply')"
run=$(sql "SELECT MAX(id) FROM import_runs")
sql "INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, licence, fetched, run_id) VALUES ('album', 535, 'title', 'discogs', '1234567', 'CC0', '2026-10-09 12:00:00', $run), ('album', 535, 'cover', 'coverartarchive', 'https://coverartarchive.org/release/x/front', NULL, '2026-10-09 12:00:00', $run)"
if sql "INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, fetched, run_id) VALUES ('album', 535, 'year', 'discogs', '1', '2026-10-09 12:00:00', $((run + 1000)))" >"$T/out" 2>&1; then
    bad "provenance cannot point at a run that does not exist"
else
    ok "provenance cannot point at a run that does not exist"
fi
if sql "UPDATE import_runs SET report = '{not json' WHERE id = $run" >"$T/out" 2>&1; then
    bad "a run's report has to be JSON"
else
    ok "a run's report has to be JSON"
fi
sql "UPDATE import_runs SET finished = started + INTERVAL 83 SECOND, created_count = 2, unchanged_count = 5, report = '{\"totals\": {\"created\": 2}}' WHERE id = $run"
check "make import-runs lists the run with its time, totals and provenance rows" \
    "$run 00:01:23 apply schema-test.ndjson aaaaaaaaaaaa 2 0 5 0 0 2" \
    "$(./scripts/import-runs.sh 1 | tail -1 | awk '{ $2 = ""; $3 = ""; print }' | tr -s ' ' | sed 's/^ //')"
check "both tables compare bytes, like external_ids" "import_provenance utf8mb4_bin import_runs utf8mb4_bin" "$(sql "SELECT GROUP_CONCAT(CONCAT(table_name, ' ', table_collation) ORDER BY table_name SEPARATOR ' ') FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('import_runs', 'import_provenance')")"

echo "> the import in addedby"
check "users has the import as 1100, with no password" "import, no password" "$(sql "SELECT CONCAT(login, IF(pass IS NULL, ', no password', ', a password')) FROM users WHERE ID = 1100")"
sql "UPDATE artists SET addedby = 1100 WHERE id = 35"
sql "UPDATE labels SET addedby = 1100 WHERE id = 58"
check "the README's query lists what the import added" "artist 35 label 58" "$(sql "SELECT 'album' AS type, id, title AS name, added FROM albums WHERE addedby = 1100 UNION ALL SELECT 'artist', id, name, added FROM artists WHERE addedby = 1100 UNION ALL SELECT 'label', id, name, added FROM labels WHERE addedby = 1100 ORDER BY added, type, id" | awk -F'\t' '{ printf "%s%s %s", sep, $1, $2; sep = " " }')"

echo "> one artist-city table"
pairs() { sql "SELECT GROUP_CONCAT(CONCAT(cityid, ':', artistid, ':', status) ORDER BY cityid, artistid, status SEPARATOR ' ') FROM \`$1\`"; }
check "the old table is gone" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'city_artist_lookup'")"
check "its pairs are in the table the pages read, once each, without the orphan" "1:1:999 2:35:999 3:46:0" "$(pairs artist_city_lookup)"
check "the archive holds every old row and every pair put in" "artist_city_lookup inserted 2, city_artist_lookup deleted 5" "$(sql "SELECT GROUP_CONCAT(CONCAT(table_name, ' ', action, ' ', n) ORDER BY table_name SEPARATOR ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0010' GROUP BY table_name, action) a")"
if sql "INSERT INTO artist_city_lookup (cityid, artistid) VALUES (2, 35)" >"$T/out" 2>&1; then
    bad "a pair cannot be stored twice"
else
    ok "a pair cannot be stored twice"
fi

echo "> unique link tables"
UNIQUE_TABLES="album_artist_lookup altnames_lookup artist_city_lookup artist_lookup band_lookup city_label_lookup collection feature_lookup music_lookup ratings remix_lookup scratch_lookup wishlist"
uniques() { sql "SELECT GROUP_CONCAT(DISTINCT table_name ORDER BY table_name SEPARATOR ' ') FROM information_schema.statistics WHERE table_schema = DATABASE() AND non_unique = 0 AND index_name LIKE 'u\\_%' AND (table_name LIKE '%\\\\_lookup' OR table_name IN ('collection', 'wishlist', 'ratings'))"; }
dupes() { # the duplicated rows of the fixtures, as table:count
    sql "SELECT CONCAT_WS(' ',
        CONCAT('album_artist:', (SELECT GROUP_CONCAT(status ORDER BY status) FROM album_artist_lookup WHERE albumid = 535 AND artistid = 8)),
        CONCAT('band:', (SELECT COUNT(*) FROM band_lookup WHERE artistid = 2 AND bandid = 22)),
        CONCAT('altnames:', (SELECT COUNT(*) FROM altnames_lookup WHERE artistid = 4 AND altname = 'Tede')),
        CONCAT('artist:', (SELECT COUNT(*) FROM artist_lookup WHERE songid = 7329 AND artistid = 8)),
        CONCAT('collection:', (SELECT GROUP_CONCAT(ID ORDER BY ID) FROM collection WHERE albumid = 535 AND userid = 1)),
        CONCAT('ratings:', (SELECT GROUP_CONCAT(CONCAT(ID, '@', added) ORDER BY ID) FROM ratings WHERE albumid = 535 AND userid = 1)))"
}
check "every link table has its unique key" "$UNIQUE_TABLES" "$(uniques)"
check "one row per key is left, the published copy where they differed, the oldest where rows have ids" \
    "album_artist:999 band:1 altnames:1 artist:1 collection:1 ratings:1@$(sql "SELECT added FROM ratings WHERE ID = 1")" "$(dupes)"
check "the archive holds every copy and every row put back" \
    "album_artist_lookup deleted 3, album_artist_lookup inserted 1, altnames_lookup deleted 2, altnames_lookup inserted 1, artist_lookup deleted 2, artist_lookup inserted 1, band_lookup deleted 2, band_lookup inserted 1, collection deleted 1, ratings deleted 1" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(table_name, ' ', action, ' ', n) ORDER BY table_name, action SEPARATOR ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0011' GROUP BY table_name, action) a")"
if sql "INSERT INTO band_lookup (artistid, bandid) VALUES (2, 22)" >"$T/out" 2>&1; then
    bad "a link cannot be stored twice"
else
    ok "a link cannot be stored twice"
fi

echo "> bands"
types() { sql "SELECT GROUP_CONCAT(CONCAT(id, ':', type) ORDER BY id SEPARATOR ' ') FROM artists WHERE id BETWEEN 60 AND 63"; }
BANDS_WITH_OTHER_TYPES="SELECT COUNT(*) FROM artists a WHERE a.type <> 'b' AND a.id IN (SELECT b.bandid FROM band_lookup b JOIN artists m ON m.id = b.artistid)"
check "every artist with members is typed 'b'" "0" "$(sql "$BANDS_WITH_OTHER_TYPES")"
check "the two that were not are now; a 'b' without members and an artist whose member is missing keep theirs" "60:b 61:b 62:b 63:x" "$(types)"
check "the archive holds both old types" "60:x 61:m" "$(sql "SELECT GROUP_CONCAT(CONCAT(JSON_VALUE(row_data, '$.id'), ':', JSON_VALUE(row_data, '$.type')) ORDER BY id SEPARATOR ' ') FROM migration_archive WHERE version = '0012'")"

echo "> roles"
credits() { sql "SELECT GROUP_CONCAT(CONCAT(songid, ':', artistid, ':', feattype) ORDER BY songid SEPARATOR ' ') FROM feature_lookup WHERE (songid, artistid) IN ((6, 36), (7, 41), (10, 42))"; }
roles() { sql "SELECT CONCAT((SELECT COUNT(*) FROM feattypes WHERE id = 0 AND feattype IS NULL), ' row 0, ', (SELECT COUNT(*) FROM feattypes WHERE feattype = 'testest'), ' testest, ', (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'feattypes' AND index_name = 'u_feattypes'), ' key')"; }
check "row 0 stays, the test role is gone, and role names have a unique key" "1 row 0, 0 testest, 1 key" "$(roles)"
check "a credit without a role takes the artist's only other role; the others stay without one" "6:36:1 7:41:0 10:42:0" "$(credits)"
check "the archive holds the deleted role and the changed credit" "feattypes deleted 1, feature_lookup changed 1" "$(sql "SELECT GROUP_CONCAT(CONCAT(table_name, ' ', action, ' ', n) ORDER BY table_name SEPARATOR ', ') FROM (SELECT table_name, action, COUNT(*) n FROM migration_archive WHERE version = '0013' GROUP BY table_name, action) a")"
if sql "INSERT INTO feattypes (feattype) VALUES ('SCRATCH')" >"$T/out" 2>&1; then
    bad "a role name cannot be stored twice, whatever its case"
else
    ok "a role name cannot be stored twice, whatever its case"
fi

echo "> release types"
check "the flagged albums are a single and an EP, the rest albums" "2:single 46:ep album:$(sql "SELECT COUNT(*) - 2 FROM albums")" "$(sql "SELECT CONCAT((SELECT GROUP_CONCAT(CONCAT(id, ':', release_type) ORDER BY id SEPARATOR ' ') FROM albums WHERE release_type <> 'album'), ' album:', (SELECT COUNT(*) FROM albums WHERE release_type = 'album'))")"
check "nothing is digital until someone says so" "0" "$(sql "SELECT COUNT(*) FROM albums WHERE media_digital <> 0 OR catalog_digital IS NOT NULL")"

echo "> album credits"
check "an album credited to two artists has them in positions 1 and 2, both main" "1:main:1 2:main:2" "$(sql "SELECT GROUP_CONCAT(CONCAT(artistid, ':', role, ':', position) ORDER BY position SEPARATOR ' ') FROM album_artist_lookup WHERE albumid = 1")"

echo "> discs"
tracks() { sql "SELECT GROUP_CONCAT(CONCAT(albumid, ':', songid, ':', $1) ORDER BY albumid, songid SEPARATOR ' ') FROM album_lookup WHERE albumid IN (3, 4, 9999)"; }
check "no track number is 0 or encodes a disc" "0" "$(sql "SELECT COUNT(*) FROM album_lookup WHERE track = 0 OR track >= 100")"
check "the two-disc album's tracks are on discs 1 and 2, the track at 0 is last, the missing album's are gone" "3:11:1-1 3:12:2-1 4:13:1-1 4:14:1-2 4:30:1-3" "$(tracks "CONCAT(disc, '-', track)")"
check "the archive holds every row of the albums it touched, as they were" "3:2 4:3 9999:2" "$(sql "SELECT GROUP_CONCAT(CONCAT(a, ':', n) ORDER BY a SEPARATOR ' ') FROM (SELECT JSON_VALUE(row_data, '$.albumid') a, COUNT(*) n FROM migration_archive WHERE version = '0016' GROUP BY a) x")"

echo "> no label"
check "the placeholder label is gone and its album has no label" "0 NULL" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM labels WHERE id = 27), ' ', IFNULL((SELECT labelid FROM albums WHERE id = 48), 'NULL'))")"

echo "> release dates"
dates() { sql "SELECT GROUP_CONCAT(CONCAT(id, ':', IFNULL(year, 'NULL'), ':', $1) ORDER BY id SEPARATOR ' ') FROM albums WHERE id IN (49, 50, 778, 923)"; }
check "no release date has zero parts" "0" "$(sql "SELECT COUNT(*) FROM albums WHERE MONTH(year) = 0 OR DAY(year) = 0")"
check "zero parts became a precision, 0000-00-00 no date, the guessed announcement announced, the placeholder gone" \
    "49:2016-12-01:month:1 50:2013-01-01:year:0 778:NULL:day:0" "$(dates "CONCAT(release_date_precision, ':', announced)")"
if sql "UPDATE albums SET year = '2020-05-00' WHERE id = 50" >"$T/out" 2>&1; then
    bad "the database refuses a date with zero parts"
else
    ok "the database refuses a date with zero parts"
fi

echo "> album covers"
sql "INSERT INTO album_covers (albumid, variant, path, width, height, sha256, mime, source) VALUES (535, '300', 'a/x.jpg', 300, 300, REPEAT('b', 64), 'image/jpeg', 'legacy')"
if sql "INSERT INTO album_covers (albumid, variant, path, width, height, sha256, mime, source) VALUES (535, '300', 'a/y.jpg', 300, 300, REPEAT('b', 64), 'image/jpeg', 'legacy')" >"$T/out" 2>&1; then
    bad "the same file cannot describe one album's variant twice"
else
    ok "the same file cannot describe one album's variant twice"
fi

echo "> artist photos"
check "artistid is an int, and a photo has room for its size, hash, licence and credit" "int 8" "$(sql "SELECT CONCAT((SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists_photos' AND column_name = 'artistid'), ' ', (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists_photos' AND column_name IN ('width', 'height', 'sha256', 'mime', 'licence', 'licence_url', 'credit', 'modified')))")"
sql "UPDATE artists_photos SET sha256 = REPEAT('c', 64) WHERE id = 1"
if sql "INSERT INTO artists_photos (artistid, filename, description, source, sourceurl, sha256) SELECT artistid, 'copy.jpg', '', '', '', sha256 FROM artists_photos WHERE id = 1" >"$T/out" 2>&1; then
    bad "the same file cannot be one artist's photo twice"
else
    ok "the same file cannot be one artist's photo twice"
fi

echo "> utf8mb4 with the Polish collation"
check "every table is utf8mb4_polish_ci, but the byte-compared ones and the runner's own" "utf8mb4_bin 7, utf8mb4_general_ci 1, utf8mb4_polish_ci 44" "$(sql "SELECT GROUP_CONCAT(CONCAT(c, ' ', n) ORDER BY c SEPARATOR ', ') FROM (SELECT table_collation c, COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' GROUP BY table_collation) x")"
check "no column is left in utf8mb3" "0" "$(count "character_set_name = 'utf8mb3'")"
if sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('Zabson', 'zabson-2', 'm', 999, '', '')" >"$T/out" 2>&1; then
    ok "Zabson is a name of its own next to Żabson"
else
    bad "Zabson is a name of its own next to Żabson"; cat "$T/out"
fi
if sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('żabson', 'zabson-3', 'm', 999, '', '')" >"$T/out" 2>&1; then
    bad "żabson is Żabson in another case, and refused"
else
    ok "żabson is Żabson in another case, and refused"
fi
sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('Łoś Testowy', 'los-testowy', 'm', 999, '', ''), ('Lux Testowy', 'lux-testowy', 'm', 999, '', ''), ('Mazur Testowy', 'mazur-testowy', 'm', 999, '', ''), ('Zenek Testowy', 'zenek-testowy', 'm', 999, '', '')"
check "names sort in Polish order: L before Ł before M, Z before Ż" "Lux Testowy|Łoś Testowy|Mazur Testowy|Zabson|Zenek Testowy|Żabson" "$(sql "SELECT GROUP_CONCAT(name ORDER BY name SEPARATOR '|') FROM artists WHERE name IN ('Lux Testowy', 'Łoś Testowy', 'Mazur Testowy', 'Zabson', 'Zenek Testowy', 'Żabson')")"
sql "INSERT INTO artists (id, name, urlname, type, status, trivia, website) VALUES (9001, 'Mikrofon 🎤', 'mikrofon', 'm', 999, '', '')"
check "a four-byte character comes back from the database" "Mikrofon 🎤" "$(sql "SELECT name FROM artists WHERE id = 9001")"
if curl -s -L --max-time 10 "$URL/mikrofon-p9001.html" | grep -q '🎤'; then
    ok "and from the artist's page"
else
    bad "and from the artist's page"
fi
# Gone before the down: in utf8mb3_general_ci Zabson and Żabson would be one name, and the
# microphone could not be stored, so the down would rightly refuse.
sql "DELETE FROM artists WHERE id = 9001 OR name IN ('Zabson', 'Lux Testowy', 'Łoś Testowy', 'Mazur Testowy', 'Zenek Testowy')"

echo "> indexes the pages' joins use"
check "lookups by artist, by album, by label and by views have their indexes (#69)" \
    "albums.labelid album_artist_lookup.artistid album_lookup.albumid,disc,track artist_lookup.artistid feature_lookup.artistid music_lookup.artistid remix_lookup.artistid scratch_lookup.artistid songs.viewed" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(table_name, '.', cols) ORDER BY table_name SEPARATOR ' ') FROM (SELECT table_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name IN ('i_album_artist_lookup_artistid', 'i_artist_lookup_artistid', 'i_feature_lookup_artistid', 'i_music_lookup_artistid', 'i_scratch_lookup_artistid', 'i_remix_lookup_artistid', 'i_album_lookup_albumid', 'i_albums_labelid', 'i_songs_viewed') GROUP BY table_name, index_name) x")"

echo "> no zero dates"
# Every date, datetime and timestamp column, and how many of its values have a zero part.
zero_dates() {
    sql "SELECT CONCAT_WS(' ', $(sql "SELECT GROUP_CONCAT(CONCAT('(SELECT COUNT(*) FROM \`', table_name, '\` WHERE MONTH(\`', column_name, '\`) = 0 OR DAY(\`', column_name, '\`) = 0)') SEPARATOR ', ') FROM information_schema.columns WHERE table_schema = DATABASE() AND data_type IN ('date', 'datetime', 'timestamp')"))" | tr ' ' '\n' | awk '{ s += $1 } END { print s }'
}
check "no date, datetime or timestamp column holds a zero part" "0" "$(zero_dates)"
check "the server runs with NO_ZERO_IN_DATE and NO_ZERO_DATE" "1 1" "$(sql "SELECT CONCAT_WS(' ', FIND_IN_SET('NO_ZERO_IN_DATE', @@GLOBAL.sql_mode) > 0, FIND_IN_SET('NO_ZERO_DATE', @@GLOBAL.sql_mode) > 0)")"
check "a band's start and end: a year, a month, not known" "22:1998-01-01:year:- 23:1998-03-01:month:2003-01-01:year" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT_WS(':', id, since, since_precision, IFNULL(till, '-'), IF(till IS NULL, NULL, till_precision)) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (22, 23)")"
check "a member's join and departure" "1998-01-01 year 2003-12-01 month" \
    "$(sql "SELECT CONCAT_WS(' ', insince, insince_precision, awaysince, awaysince_precision) FROM band_lookup WHERE artistid = 35 AND bandid = 23")"
check "a user's unknown times, and a news item's, are NULL" "8:NULL 9:NULL:NULL 2:NULL" \
    "$(sql "SELECT CONCAT('8:', IFNULL(usr_added, 'NULL'), ' 9:', IFNULL((SELECT usr_updated FROM hhb_users WHERE usr_id = 9), 'NULL'), ':', IFNULL((SELECT usr_last_login FROM hhb_users WHERE usr_id = 9), 'NULL'), ' 2:', IFNULL((SELECT expires FROM news WHERE ID = 2), 'NULL')) FROM hhb_users WHERE usr_id = 8")"
if sql "INSERT INTO hhb_users (usr_email, usr_password, usr_display_name, usr_added) VALUES ('nowy@example.com', MD5('x'), 'Nowy', NOW())" >"$T/out" 2>&1; then
    ok "a registration, which writes only its added time, goes in"
else
    bad "a registration, which writes only its added time, goes in"; cat "$T/out"
fi
sql "DELETE FROM hhb_users WHERE usr_email = 'nowy@example.com'"
if sql "UPDATE artists SET since = '2001-00-00' WHERE id = 22" >"$T/out" 2>&1; then
    bad "the server refuses a zero part"
else
    ok "the server refuses a zero part"
fi
if sql "SET SESSION sql_mode = ''; UPDATE band_lookup SET awaysince = '2004-00-00' WHERE artistid = 35 AND bandid = 23" >"$T/out" 2>&1; then
    bad "and so does the CHECK, in a session that allows zero dates"
else
    ok "and so does the CHECK, in a session that allows zero dates"
fi

echo "> image files production lacks for good"
check "an album's lost cover and an artist's lost photo are no longer named (#47)" "576: 0 1" \
    "$(sql "SELECT CONCAT((SELECT CONCAT(id, ':', cover) FROM albums WHERE id = 576), ' ', (SELECT COUNT(*) FROM artists_photos WHERE id = 120), ' ', (SELECT COUNT(*) FROM migration_archive WHERE version = '0029' AND table_name = 'artists_photos'))")"

echo "> artists who share a name"
check "an artist's name is unique with its qualifier, not alone" "name,disambiguation" \
    "$(sql "SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'artists' AND index_name = 'u_artists_name' AND non_unique = 0")"
check "two Solars stand side by side, each with its qualifier" "64:Solar:SBM Label 65:Solar:raper z Poznania" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name, ':', disambiguation) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)")"
if sql "INSERT INTO artists (id, name, urlname, type, status, trivia, website) VALUES (9002, 'Solar', 'solar', 'm', 999, '', '')" >"$T/out" 2>&1; then
    ok "a third Solar without a qualifier can join them"
else
    bad "a third Solar without a qualifier can join them"; cat "$T/out"
fi
if sql "INSERT INTO artists (name, urlname, type, status, trivia, website) VALUES ('solar', 'solar-2', 'm', 999, '', '')" >"$T/out" 2>&1; then
    bad "but not a fourth: a name without a qualifier is still one artist's"
else
    ok "but not a fourth: a name without a qualifier is still one artist's"
fi
if sql "INSERT INTO artists (name, disambiguation, urlname, type, status, trivia, website) VALUES ('Solar', 'sbm label', 'solar-3', 'm', 999, '', '')" >"$T/out" 2>&1; then
    bad "and a qualifier names one artist of a name"
else
    ok "and a qualifier names one artist of a name"
fi
sql "DELETE FROM artists WHERE id = 9002"
# The down folds each qualifier into its name; one a plain name already holds stops it before
# it changes anything.
sql "INSERT INTO artists (id, name, urlname, type, status, trivia, website) VALUES (9003, 'Solar (SBM Label)', 'solar-sbm-label-2', 'm', 999, '', '')"
# How many migrations after 0021 are applied: going down that many reaches 0022's down last.
after_0021() { ./scripts/migrate.sh status 2>/dev/null | awk '$1 ~ /^[0-9]{4}$/ && $1 > "0021" && $3 != "pending"' | wc -l | tr -d ' '; }
since_0021=$(after_0021)
if ./scripts/migrate.sh down "$since_0021" >"$T/out" 2>&1; then
    bad "a down that would make two artists one name is refused"
else
    check "a down that would make two artists one name is refused, naming it" "1" "$(grep -c "Solar (SBM Label)" "$T/out")"
fi
check "and leaves the names and the qualifier column as they were" "64:Solar:SBM Label 65:Solar:raper z Poznania $((since_0021 - 1)) pending" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name, ':', disambiguation) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)") $(./scripts/migrate.sh status 2>/dev/null | grep -oE '[0-9]+ pending')"
sql "DELETE FROM artists WHERE id = 9003"
./scripts/migrate.sh down "$(after_0021)" >"$T/out" 2>&1 || { bad "the down succeeds once the name is free"; cat "$T/out"; }
check "down: each qualifier folded into its name, under the old key on the name alone" "64:Solar (SBM Label) 65:Solar (raper z Poznania) name" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)") $(sql "SELECT GROUP_CONCAT(column_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'artists' AND index_name = 'name' AND non_unique = 0")"
./scripts/migrate.sh up >"$T/out" 2>&1 || { bad "up succeeds"; cat "$T/out"; }
check "up: the names and qualifiers apart again, the archive empty" "64:Solar:SBM Label 65:Solar:raper z Poznania 0" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name, ':', disambiguation) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)") $(sql "SELECT COUNT(*) FROM migration_archive WHERE version = '0022'")"

echo "> down to the baseline brings the old schema back, and up removes it"
rows_before=$(sql "SELECT COUNT(*) FROM songs")
./scripts/migrate.sh down "$after_baseline" >"$T/out" 2>&1 || { bad "down $after_baseline succeeds"; cat "$T/out"; }
check "after down: 44 tables on MyISAM again, hhb_comments and the migrations' own on InnoDB" "InnoDB 2, MyISAM 44" "$(engines)"
check "after down: no row lost in the conversions" "$rows_before" "$(sql "SELECT COUNT(*) FROM songs")"
check "after down: none of the import's tables" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('external_ids', 'import_runs', 'import_provenance', 'album_covers', 'review_items', 'artist_merges')")"
check "after down: no import user" "0" "$(sql "SELECT COUNT(*) FROM users WHERE ID = 1100")"
check "after down: the old city table again, duplicate and orphan included" "1:1:999 1:99999:999 2:35:0 2:35:999 3:46:0" "$(pairs city_artist_lookup)"
check "after down: the table the pages read as the fixtures had it" "1:1:999" "$(pairs artist_city_lookup)"
check "after down: every duplicate back as the fixtures had it" \
    "album_artist:0,999,999 band:2 altnames:2 artist:2 collection:1,2 ratings:1@$(sql "SELECT added FROM ratings WHERE ID = 1"),1001@2010-05-02 10:00:00" "$(dupes)"
check "after down: no unique keys on the link tables" "NULL" "$(uniques)"
check "after down: the artists' types as the fixtures had them" "60:x 61:m 62:b 63:x" "$(types)"
check "after down: no credit columns" "0" "$(count "table_name = 'album_artist_lookup' AND column_name IN ('role', 'position', 'credited_as')")"
check "after down: the old track numbers, and the missing album's rows, back" "3:11:101 3:12:201 4:13:1 4:14:2 4:30:0 9999:14:1 9999:15:0" "$(tracks track)"
check "after down: the zero-part dates and the placeholder back" "49:2016-12-00:1 50:2013-00-00:1 778:0000-00-00:1 923:2017-01-00:1" "$(dates "(SELECT COUNT(*) FROM album_artist_lookup c WHERE c.albumid = albums.id)")"
check "after down: the placeholder label back, and its album on it" "BRAK 27" "$(sql "SELECT CONCAT((SELECT name FROM labels WHERE id = 27), ' ', (SELECT labelid FROM albums WHERE id = 48))")"
check "after down: artistid a smallint again, without the photo columns" "smallint 0" "$(sql "SELECT CONCAT((SELECT data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists_photos' AND column_name = 'artistid'), ' ', (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists_photos' AND column_name IN ('width', 'sha256', 'credit')))")"
check "after down: every catalogue table utf8mb3 again" "45" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_collation LIKE 'utf8mb3%'")"
check "after down: no release type columns" "0" "$(count "table_name = 'albums' AND column_name IN ('release_type', 'media_digital', 'catalog_digital')")"
check "after down: the roles and credits as the fixtures had them" "1 row 0, 1 testest, 0 key 6:36:0 7:41:0 10:42:0" "$(roles) $(credits)"
check "after down: no archive" "0" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migration_archive'")"
check "after down: added changes on update again, in 11 tables plus the lyrics log" "12" "$(count "$TIMES_WITH_ON_UPDATE")"
check "after down: the catalog's added has no default" "0" "$(count "table_name IN ($CATALOG) AND column_name = 'added' AND column_default = 'current_timestamp()'")"
check "after down: album_prices.added defaults to the zero date again" "album_prices.added" "$(col "column_default LIKE '%0000-00-00%'")"
check "after down: the lost cover and photo named again" "wally-prawda-naga-hhbdpl.jpg Enemis-1-hhbdpl.jpg" \
    "$(sql "SELECT CONCAT((SELECT cover FROM albums WHERE id = 576), ' ', (SELECT filename FROM artists_photos WHERE id = 120))")"
check "after down: the zero and partial dates back as the fixtures had them" "1998-00-00 0000-00-00 1998-03-00 2003-00-00 | 1998-00-00 2003-12-00 | 0000-00-00 00:00:00 0000-00-00 00:00:00 0000-00-00 00:00:00 | 0000-00-00 00:00:00" \
    "$(sql "SELECT CONCAT_WS(' | ', (SELECT GROUP_CONCAT(CONCAT_WS(' ', since, till) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (22, 23)), (SELECT CONCAT_WS(' ', insince, awaysince) FROM band_lookup WHERE artistid = 35 AND bandid = 23), (SELECT CONCAT_WS(' ', (SELECT usr_added FROM hhb_users WHERE usr_id = 8), usr_updated, usr_last_login) FROM hhb_users WHERE usr_id = 9), (SELECT expires FROM news WHERE ID = 2))")"
check "after down: the qualifiers folded into the names" "64:Solar (SBM Label) 65:Solar (raper z Poznania)" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)")"
./scripts/migrate.sh up >"$T/out" 2>&1 || { bad "up succeeds"; cat "$T/out"; }
check "after up again: every table InnoDB" "InnoDB $(tables)" "$(engines)"
check "after up again: no row lost" "$rows_before" "$(sql "SELECT COUNT(*) FROM songs")"
check "after up again: no added changes on update" "0" "$(count "$TIMES_WITH_ON_UPDATE")"
check "after up again: no zero-date default" "NULL" "$(col "column_default LIKE '%0000-00-00%'")"
check "after up again: no zero date anywhere" "0" "$(zero_dates)"
check "after up again: the cities merged as before" "1:1:999 2:35:999 3:46:0" "$(pairs artist_city_lookup)"
check "after up again: the keys and one row per key" "$UNIQUE_TABLES album_artist:999 band:1" "$(uniques) $(dupes | cut -d' ' -f1-2)"
check "after up again: the bands typed again" "60:b 61:b 62:b 63:x" "$(types)"
check "after up again: the roles cleaned again" "1 row 0, 0 testest, 1 key 6:36:1 7:41:0 10:42:0" "$(roles) $(credits)"
# Below 0009 the archive itself is gone, and the pairs with it: the names stay whole.
check "after up again: the folded names kept, with no qualifier" "64:Solar (SBM Label): 65:Solar (raper z Poznania):" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(id, ':', name, ':', disambiguation) ORDER BY id SEPARATOR ' ') FROM artists WHERE id IN (64, 65)")"

echo "> back to the fixtures"
if make -s reset-db >"$T/out" 2>&1; then ok "make reset-db leaves the database as the fixtures have it"; else bad "make reset-db leaves the database as the fixtures have it"; cat "$T/out"; fi

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
