#!/usr/bin/env bash
#
# The release queue (#258) on the running local stack, with the importer's test batch:
#   - an import queues what it would publish: the album waits unpublished, a visitor gets a 404
#     for it, and the artists the import made for it wait too, out of the lists of artists
#     added, the sitemap and search engines' index
#   - a later batch that brings what an album lacked queues it behind the first
#   - make release-queue says what waits, in order, and an admin sees an album's place on its
#     page
#   - a dry run names what a release would publish and changes nothing
#   - a release publishes the next albums, added now, so they lead the home page's newest; the
#     artists that waited for them are shown, those waiting for another album are not; each is
#     an operation of the edit journal, written as the import's user
#   - make edit undoes a release after the page was seen, and the album waits in its old place
#   - a release takes no more than waits, and an empty queue is no error
#
# It ends with make reset-db and removes the files the imports wrote, so the stack ends as a
# reset leaves it.
#
# Usage: tests/release-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

URL=${1:-http://localhost:8080}
BATCH=tests/import/batch
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-releasetest.XXXXXX")
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

import() { # import <batch dir> <name>: an apply, queued as make ovh-import queues; the report in $T/<name>.json
    local status=0
    make -s import BATCH="$1" MODE=apply >"$T/$2.json" 2>"$T/$2.err" || status=$?
    echo "$status"
}
release() { # release <name> [make variables...]: the run's lines in $T/<name>.err, its exit status echoed
    local name=$1 status=0
    shift
    make -s release "$@" >"$T/$name.out" 2>"$T/$name.err" || status=$?
    echo "$status"
}
code() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$URL$1"; }
content() { find content -type f | sort; }
newest() { curl -s --max-time 10 "$URL/" | sed -n '/id="recently-added"/,$p' | grep -o -- '-a[0-9]*\.html' | head -1; }
artists_added() { curl -s --max-time 10 "$URL/wykonawcy.html" | sed -n '/Ostatnio dodani/,/<\/ol>/p' | grep -c "$1" || true; }
noindex() { curl -s --max-time 10 "$URL$1" | grep -c 'name="robots" content="noindex' || true; }
waiting() { sql "SELECT string_agg(CASE WHEN queued IS NULL THEN 'shown' ELSE 'waits' END, ' ' ORDER BY id) FROM artists WHERE name IN ($1)"; }
queue_line() { make -s release-queue | sed -n 1p; }

remove_written() {
    content | comm -13 "$T/content-start" - | sed 's|^content/||' >"$T/written"
    if [ -s "$T/written" ]; then
        # shellcheck disable=SC2016 # expanded in the container
        xargs docker compose run --rm --no-deps -T --entrypoint sh importer -c \
            'cd /var/www/html/content && rm -f "$@" && rmdir a/orig a/600 a/300 a/75 2>/dev/null; true' sh <"$T/written" 2>/dev/null
    fi
    wc -l <"$T/written" | tr -d ' '
}
cleanup() {
    make -s reset-db >/dev/null 2>&1 || echo "x make reset-db failed on the way out" >&2
    echo "> removed $(remove_written) files the imports wrote, and $T"
    rm -rf "$T"
}

echo "> make reset-db: the migrations, over the fixtures"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }
content >"$T/content-start"
trap cleanup EXIT
newest_before=$(newest)
made="'Testowy Raper Ąę', 'Testowy Producent', 'Testowy Skład'"

echo "> an import, as make ovh-import runs it"
check "the apply reads every document" "0" "$(import "$BATCH" first)"
album_id=$(jq -r '.documents[] | select(.ref == "release:testowy-album") | .hhbd_id' "$T/first.json")
single_id=$(jq -r '.documents[] | select(.ref == "release:testowy-singiel") | .hhbd_id' "$T/first.json")
album_url=$(jq -r '.documents[] | select(.ref == "release:testowy-album") | .url' "$T/first.json")
band_url=$(jq -r '.documents[] | select(.ref == "artist:testowy-sklad") | .url' "$T/first.json")
check "its report lists the album it queued, and not the single that lacks a label and a day" "[$album_id]" "$(jq -c .queued "$T/first.json")"
check "and its summary says so" "1" "$(grep -c '1 queued for release' "$T/first.err" || true)"
check "the album waits unpublished, joined when its run started, so a run's albums come out by date" "0 1" \
    "$(sql "SELECT CONCAT_WS(' ', a.status, CASE WHEN a.queued = r.started THEN 1 ELSE 0 END) FROM albums a, import_runs r WHERE a.id = $album_id AND r.id = $(jq -r .run "$T/first.json")")"
check "the single waits for what it lacks, outside the queue" "0 0" "$(sql "SELECT CONCAT_WS(' ', status, CASE WHEN queued IS NULL THEN 0 ELSE 1 END) FROM albums WHERE id = $single_id")"
check "a visitor gets a 404 for the album" "404" "$(code "$album_url")"
check "and the home page's newest are as they were" "$newest_before" "$(newest)"
check "the artists the import made wait with it" "waits waits waits" "$(waiting "$made")"
check "the band is not among the artists added" "0" "$(artists_added 'Testowy Skład')"
check "its page asks search engines not to index it" "1" "$(noindex "$band_url")"
check "and the sitemap leaves it out" "0" "$(curl -s --max-time 10 "$URL/sitemap-artists.xml" | grep -c "${band_url}<" || true)"
check "make release-queue says what waits" "1 album waits (2020: 1)." "$(queue_line)"

echo "> a second batch: what the single lacked, and a guest that only the single credits"
mkdir -p "$T/second"
cp -R "$BATCH/files" "$T/second/"
{
    jq -c 'select(.ref == "artist:testowy-producent") | .ref = "artist:testowy-gosc" | .name = "Testowy Gość" | del(.external_ids) | .provenance.core.source_ref = "https://www.discogs.com/artist/999000099"' "$BATCH/batch.ndjson"
    jq -c 'if .ref == "release:testowy-singiel" then .self_released = true | .release_date = "2020-03-13" | .release_date_precision = "day" | .artists += [{"ref": "artist:testowy-gosc", "role": "featured", "position": 3}] else . end' "$BATCH/batch.ndjson"
} >"$T/second/batch.ndjson"
check "the import reads it" "0" "$(import "$T/second" second)"
check "and queues the single, now complete" "[$single_id]" "$(jq -c .queued "$T/second.json")"
check "behind the album" "2 albums wait (2020: 2)." "$(queue_line)"
check "next the album, then the single" "$album_id $single_id" "$(make -s release-queue | sed -n 's/^ *[0-9]*\. album \([0-9]*\) .*/\1/p' | tr '\n' ' ' | sed 's/ $//')"
check "the guest waits too" "waits" "$(waiting "'Testowy Gość'")"

curl -s -o /dev/null --max-time 10 -c "$T/jar" --data-urlencode "email=admin@example.com" --data-urlencode "password=adminpass" "$URL/uzytkownik/logowanie.html"
check "an admin sees the single's place on its page" "1" \
    "$(curl -s --max-time 10 -b "$T/jar" "$URL$(jq -r '.documents[] | select(.ref == "release:testowy-singiel") | .url' "$T/second.json")" | grep -c 'W kolejce do publikacji:</strong> 2. z 2' || true)"

echo "> a dry run"
check "names what a release would publish" "0" "$(release dry DRY_RUN=1)"
check "the album, and the single after it" "2" "$(grep -c '"msg":"would release album' "$T/dry.err" || true)"
check "and changes nothing" "0 0 0" "$(sql "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM edit_operations), (SELECT COUNT(*) FROM albums WHERE status = 999 AND id IN ($album_id, $single_id)), (SELECT COUNT(*) FROM artists WHERE queued IS NULL AND name IN ($made)))")"

echo "> the first day: one album"
check "the release goes through" "0" "$(release day1 N=1)"
check "and publishes the album, out of the queue" "999 0" "$(sql "SELECT CONCAT_WS(' ', status, CASE WHEN queued IS NULL THEN 0 ELSE 1 END) FROM albums WHERE id = $album_id")"
check "published as it is added, the moment its sitemap entry gives (#221)" "$(sql "SELECT added FROM albums WHERE id = $album_id")" \
    "$(sql "SELECT published FROM albums WHERE id = $album_id")"
check "a visitor opens it" "200" "$(code "$album_url")"
check "and it leads the home page's newest" "-a$album_id.html" "$(newest)"
check "its new songs were added with it" "$(sql "SELECT added FROM albums WHERE id = $album_id")" \
    "$(sql "SELECT string_agg(DISTINCT CAST(s.added AS text), ' ') FROM songs s JOIN album_lookup l ON l.songid = s.id WHERE l.albumid = $album_id")"
check "the artists that waited for it are shown, added with it" "shown shown shown $(sql "SELECT added FROM albums WHERE id = $album_id")" \
    "$(waiting "$made") $(sql "SELECT string_agg(DISTINCT CAST(added AS text), ' ') FROM artists WHERE name IN ($made)")"
check "the band is among the artists added" "1" "$(artists_added 'Testowy Skład')"
check "and its page is for search engines again" "0" "$(noindex "$band_url")"
check "the guest still waits for the single" "waits" "$(waiting "'Testowy Gość'")"
check "the release is an operation of the edit journal, written as the import's user" "release {\"id\":\"$album_id\"} 1100 job" \
    "$(sql "SELECT CONCAT_WS(' ', operation, CAST(args AS text), user_id, path) FROM edit_operations ORDER BY id DESC LIMIT 1")"
check "its line names the album" "1" "$(grep -c "\"msg\":\"released album $album_id," "$T/day1.err" || true)"
check "and the single waits" "1 album waits (2020: 1)." "$(queue_line)"

echo "> make edit undoes it, after the page was seen"
operation=$(sql "SELECT MAX(id) FROM edit_operations")
queued=$(sql "SELECT queued FROM edit_journal j, json_to_record(j.row_before) AS r(queued text) WHERE j.operation_id = $operation AND j.table_name = 'albums'")
sql "UPDATE albums SET viewed = viewed + 3 WHERE id = $album_id" >/dev/null
viewed=$(sql "SELECT viewed FROM albums WHERE id = $album_id")
check "the undo goes through" "0" "$(make -s edit DO="undo $operation" BY=Admin WHY="release-test: undo" MODE=apply >"$T/undo.out" 2>&1; echo $?)"
check "the album waits again, in its old place, with the views it had since" "0 $queued $viewed" "$(sql "SELECT CONCAT_WS(' ', status, queued, viewed) FROM albums WHERE id = $album_id")"
check "and so do its artists" "waits waits waits" "$(waiting "$made")"
check "a visitor gets a 404 for it again" "404" "$(code "$album_url")"
check "next the album again, then the single" "2 albums wait (2020: 2)." "$(queue_line)"

echo "> the next day: the rest"
check "the release goes through" "0" "$(release day2)"
check "it publishes both, as fewer than five wait" "999 999" "$(sql "SELECT string_agg(CAST(status AS text), ' ') FROM albums WHERE id IN ($album_id, $single_id)")"
check "and every artist that waited, the guest too" "shown shown shown shown" "$(waiting "$made, 'Testowy Gość'")"
check "nothing waits" "Nothing waits in the release queue." "$(queue_line)"
check "a release with nothing to release goes through" "0" "$(release day3)"
check "and says so" "1" "$(grep -c '"msg":"released 0 (today up to [0-9]*), 0 still waiting"' "$T/day3.err" || true)"

echo
echo "Results: $pass passed, $fail failed"
[ "$fail" -eq 0 ]
