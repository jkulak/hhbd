#!/usr/bin/env bash
#
# Settling what an import left for a person (#103), on the running local stack, as the fixtures'
# admin through the site's own forms:
#   - a form without the session's token, or from someone not logged in, changes nothing
#   - merging a namesake into the artist it is moves every reference, drops the ones the kept
#     artist has already, deletes the duplicate, keeps its old URL as a redirect, and records
#     what it moved so it can be undone; no column anywhere still names the duplicate
#   - "keep apart", "change the qualifier", "accept the cover", "pick this date" and "pick this
#     type" each do what they say and close the item with the admin and the time
#   - a value that is no date is refused and leaves the item open
#   - the list counts what is still open
#
# It ends with make reset-db, so the database ends as a reset leaves it.
#
# Usage: tests/review-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-reviewtest.XXXXXX")
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
# settle <item> <action> <value> <note> [token]: the panel's form, as the admin; prints the status
settle() {
    curl -s -o /dev/null -w '%{http_code}' --max-time 20 -b "$T/jar" -c "$T/jar" \
        --data-urlencode "token=${5-$token}" --data-urlencode "id=$1" --data-urlencode "do=$2" \
        --data-urlencode "value=$3" --data-urlencode "note=$4" --data-urlencode "back=/admin/do-przejrzenia.html" \
        "$URL/admin/do-przejrzenia/rozstrzygnij.html"
}
item() { sql "SELECT CONCAT_WS(' ', IFNULL(resolution, 'open'), IFNULL(resolved_by, '-'), resolved IS NOT NULL, IFNULL(note, '-')) FROM review_items WHERE id = $1"; }
# Every column that may hold an artist's id, and how many rows name artist $1 there.
references() {
    sql "SELECT CONCAT_WS(' ', $(sql "SELECT GROUP_CONCAT(CONCAT('(SELECT COUNT(*) FROM \`', table_name, '\` WHERE \`', column_name, '\` = $1)') SEPARATOR ', ') FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name IN ('artistid', 'bandid', 'aid')"), (SELECT COUNT(*) FROM external_ids WHERE entity_type = 'artist' AND entity_id = $1), (SELECT COUNT(*) FROM import_provenance WHERE entity_type = 'artist' AND entity_id = $1), (SELECT COUNT(*) FROM hhb_comments WHERE com_object_type = 'p' AND com_object_id = $1))" | tr ' ' '\n' | awk '{ s += $1 } END { print s }'
}

cleanup() {
    make -s reset-db >/dev/null 2>&1 || echo "x make reset-db failed on the way out" >&2
    rm -rf "$T"
}
trap cleanup EXIT

echo "> make reset-db, then the admin logs in"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }
curl -s -o /dev/null --max-time 10 -c "$T/jar" --data-urlencode "email=admin@example.com" --data-urlencode "password=adminpass" "$URL/uzytkownik/logowanie.html"
token=$(curl -s --max-time 10 -b "$T/jar" -c "$T/jar" "$URL/solar-raper-z-poznania-p65.html" | grep -o 'name="token" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//; s/"$//')
check "the panel's forms carry a token" "64" "${#token}"

echo "> forms that do not count"
check "without the token: refused" "403" "$(settle 3 pick 2013 '' 'not-the-token')"
check "from someone not logged in: sent to log in" "302" \
    "$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 --data-urlencode "token=$token" -d 'id=3&do=pick&value=2013' "$URL/admin/do-przejrzenia/rozstrzygnij.html")"
check "and the item still open" "open - 0 -" "$(item 3)"
check "an action the reason does not have changes nothing" "302 open - 0 -" "$(settle 3 merge 64 '') $(item 3)"
check "a value that is no date is refused, the item left open" "302 open - 0 - 2013" \
    "$(settle 3 pick '2013-02-30' '') $(item 3) $(sql "SELECT YEAR(year) FROM albums WHERE id = 50")"

echo "> merging Solar from Poznań into the SBM one"
# The merge's list against the schema: a column that may hold an artist's id and is not on it
# would be left naming an artist that is gone.
covered=$(docker compose exec -T app php -r 'class Jkl_Model_Api {} require "/var/www/html/app/application/models/Review/Api.php"; foreach (Model_Review_Api::ARTIST_COLUMNS as $t => $cs) { foreach ($cs as $c) { echo "$t.$c\n"; } }' | sort | paste -sd' ' -)
check "every column that may hold an artist's id is one the merge moves" \
    "$(sql "SELECT CONCAT(table_name, '.', column_name) FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name IN ('artistid', 'bandid', 'aid') ORDER BY 1" | sort | paste -sd' ' -)" "$covered"
# References of every kind for the duplicate, one of them a band membership the kept artist
# has already.
sql "INSERT INTO album_artist_lookup (albumid, artistid, role, position) VALUES (1, 65, 'featured', 3)"
sql "INSERT INTO feature_lookup (songid, artistid, feattype, status) VALUES (7329, 65, 0, 999)"
sql "INSERT INTO altnames_lookup (artistid, altname, status) VALUES (65, 'Solar z Poznania', 999)"
sql "INSERT INTO band_lookup (artistid, bandid, status) VALUES (65, 66, 999)"
sql "INSERT INTO artists_photos (artistid, filename, description, source, sourceurl) VALUES (65, 'solar-poznan.jpg', '', '', '')"
sql "INSERT INTO external_ids (entity_type, entity_id, source, kind, value) VALUES ('artist', 65, 'discogs', 'artist', '999000065')"
sql "INSERT INTO import_provenance (entity_type, entity_id, field, source, source_ref, fetched, run_id) VALUES ('artist', 65, 'core', 'discogs', 'discogs:artist:999000065', NOW(), 1)"
sql "INSERT INTO hhb_comments (com_object_id, com_object_type, com_author_id, com_content, com_added) VALUES (65, 'p', 1, 'Który to Solar?', NOW())"
before64=$(references 64)
check "the duplicate has references in every kind of table" "8" "$(references 65)"
check "the merge goes through" "302" "$(settle 1 merge 64 'ten sam, inna wytwórnia')"
check "no column anywhere still names the duplicate" "0" "$(references 65)"
check "the kept artist has them, but the membership it had already" "$((before64 + 7))" "$(references 64)"
check "the duplicate is gone, and its URL redirects to the kept one" "0 301 /solar-sbm-label-p64.html" \
    "$(sql "SELECT COUNT(*) FROM artists WHERE id = 65") $(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$URL/solar-raper-z-poznania-p65.html" | sed "s|$URL||")"
check "the item closed by the admin, with the note" "merged 10 1 ten sam, inna wytwórnia" "$(item 1)"
check "what it moved and dropped is recorded to undo it" "Solar raper z Poznania 8 1" \
    "$(sql "SELECT CONCAT_WS(' ', JSON_VALUE(undo_data, '$.artist.name'), JSON_VALUE(undo_data, '$.artist.disambiguation'), JSON_LENGTH(JSON_KEYS(undo_data, '$.moved')), JSON_LENGTH(undo_data, '$.dropped.band_lookup')) FROM review_items WHERE id = 1")"
check "the kept artist is recorded as changed by the admin" "10" "$(sql "SELECT updatedby FROM artists WHERE id = 64")"

echo "> keeping apart, and a new qualifier"
sql "INSERT INTO review_items (id, entity_type, entity_id, reason, detail) VALUES (6, 'artist', 66, 'namesake', '{\"suggestions\": [64]}'), (7, 'artist', 66, 'namesake', '{\"suggestions\": [64]}')"
check "keep apart closes the item and changes nothing else" "302 kept 10 1 - Skład Solara:" "$(settle 6 keep '' '') $(item 6) $(sql "SELECT CONCAT(name, ':', disambiguation) FROM artists WHERE id = 66")"
check "a new qualifier is written" "302 qualified 10 1 - Skład Solara:zespół" "$(settle 7 qualifier 'zespół' '') $(item 7) $(sql "SELECT CONCAT(name, ':', disambiguation) FROM artists WHERE id = 66")"

echo "> the album's doubts"
check "the stand-in cover accepted" "302 accepted 10 1 -" "$(settle 2 accept '' '') $(item 2)"
check "a date picked, whole with its precision" "302 picked 10 1 - 2013-05-17 day 10" \
    "$(settle 3 pick '2013-05-17' '') $(item 3) $(sql "SELECT CONCAT_WS(' ', year, release_date_precision, updatedby) FROM albums WHERE id = 50")"
check "a type picked" "302 picked 10 1 - ep" "$(settle 4 pick 'ep' '') $(item 4) $(sql "SELECT release_type FROM albums WHERE id = 2")"
check "a release only one catalogue knew, checked" "302 accepted 10 1 -" "$(settle 5 accept '' '') $(item 5)"
check "a settled item cannot be settled again" "302 merged 10 1 ten sam, inna wytwórnia" "$(settle 1 keep '' '') $(item 1)"

echo "> the list"
check "nothing open is left" "0" "$(curl -s --max-time 10 -b "$T/jar" "$URL/admin/do-przejrzenia.html" | grep -o 'Wszystkie</a>: [0-9]*' | grep -o '[0-9]*$')"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
