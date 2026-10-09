#!/usr/bin/env bash
#
# The importer (#56) on the running local stack, with the test batch in tests/import/batch:
#   - a dry run reports what an apply would do, and leaves no row and no file behind
#   - an apply creates the rows, fills what was empty in the rows hhbd has, keeps what was not
#     and says so, and writes every image size into content/, where the pages find them
#   - the same batch applied again changes nothing
#   - a document that does not hold up (a file that is not what it says, a reference to a
#     refused document, a line that is not JSON, a field the schema refuses) is refused alone,
#     with nothing of it left, while the rest of the batch goes in
#
# The importer runs in its own image, as make import runs it. The test ends with make reset-db
# and removes the files the imports wrote, so the stack ends as a reset leaves it.
#
# Usage: tests/import-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

URL=${1:-http://localhost:8080}
BATCH=tests/import/batch
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-importtest.XXXXXX")
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

sql() { # sql <statement>: in this project's database, as root
    docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
}
import() { # import <batch dir> <mode> <name>: the report in $T/<name>.json, the progress in $T/<name>.err
    local status=0
    make -s import BATCH="$1" MODE="$2" >"$T/$3.json" 2>"$T/$3.err" || status=$?
    echo "$status"
}
totals() { jq -r '.totals | "\(.created) created, \(.updated) updated, \(.unchanged) unchanged, \(.refused) refused"' "$T/$1.json"; }
actions() { jq -r '[.documents[] | .action] | join(" ")' "$T/$1.json"; }
content() { find content -type f | sort; }
rows() { sql "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM artists), (SELECT COUNT(*) FROM albums), (SELECT COUNT(*) FROM songs), (SELECT COUNT(*) FROM external_ids), (SELECT COUNT(*) FROM artists_photos), (SELECT COUNT(*) FROM album_covers), (SELECT COUNT(*) FROM import_provenance))"; }
sha() { jq -r --arg p "files/$1" 'first(.. | objects | select(.path? == $p) | .sha256)' "$BATCH/batch.ndjson" | head -1; }

# The files the imports write belong to the importer's user, root on Linux, in directories it
# made; so they go the way they came, from inside its container. Prints how many went.
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
rows_start=$(rows)
cover=$(sha cover-album.jpg)
single=$(sha cover-single.png)
photo=$(sha photo-mc.webp)
logo=$(sha logo-label.png)

echo "> a dry run"
check "the dry run reads every document" "0" "$(import "$BATCH" dry-run dry)"
check "and reports what an apply would do" "7 created, 3 updated, 1 unchanged, 0 refused" "$(totals dry)"
check "with a warning for each value hhbd keeps, and the namesake and the stand-in cover to review" "5" "$(jq '[.documents[].warnings[]] | length' "$T/dry.json")"
check "and leaves no row behind" "$rows_start" "$(rows)"
check "and no file" "" "$(content | comm -13 "$T/content-start" -)"
check "its progress on stderr is one JSON line per document and one for the run, in the host's format (#101)" "12 12" \
    "$(grep -c '^{' "$T/dry.err") $(grep '^{' "$T/dry.err" | jq -c 'select((.time | test("^[0-9-]{10}T[0-9:]{8}[.][0-9]{3}Z$")) and .level == "info" and (.msg | length > 0) and .logger == "importer")' | grep -c .)"
check "but its run, closed" "dry-run 7 1" "$(sql "SELECT CONCAT_WS(' ', mode, created_count, finished IS NOT NULL) FROM import_runs ORDER BY id DESC LIMIT 1")"

echo "> an apply"
check "the apply reads every document" "0" "$(import "$BATCH" apply apply)"
check "and does what the dry run said" "$(actions dry)" "$(actions apply)"
check "the new rows are the import's" "Testowy Raper Ąę 1100, Testowy Album Importu 1100" \
    "$(sql "SELECT CONCAT((SELECT CONCAT(name, ' ', addedby) FROM artists WHERE name = 'Testowy Raper Ąę'), ', ', (SELECT CONCAT(title, ' ', addedby) FROM albums WHERE title = 'Testowy Album Importu'))")"
album=$(jq -r '.documents[] | select(.ref == "release:testowy-album") | .hhbd_id' "$T/apply.json")
check "the album's cover in four sizes, from the one original" "orig:1200 600:600 300:300 75:75" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(variant, ':', width) ORDER BY width DESC SEPARATOR ' ') FROM album_covers WHERE albumid = $album")"
check "the single's smaller cover never upscaled, and flagged to replace" "300:300:1 75:75:1" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(c.variant, ':', c.width, ':', c.needs_upgrade) ORDER BY c.width DESC SEPARATOR ' ') FROM album_covers c JOIN albums a ON a.id = c.albumid WHERE a.title = 'Testowy Singiel'")"
check "every size written into content/" "a/orig a/600 a/300 a/75" \
    "$(for v in orig 600 300 75; do [ -f "content/a/$v/$cover.jpg" ] && printf 'a/%s ' "$v"; done | sed 's/ $//')"
page=$(curl -s "$URL$(jq -r '.documents[] | select(.ref == "release:testowy-album") | .url' "$T/apply.json")")
check "the album's page shows the 600 cover" "1" "$(grep -c "/content/a/600/$cover.jpg" <<<"$page" || true)"
check "and its tracklist, credits and label" "Wstęp Testowy|Numer Testowy (remix)|Dj Technik|Beatbox testowy|Testowe Nagrania Źródłowe" \
    "$(for s in 'Wstęp Testowy' 'Numer Testowy (remix)' 'Dj Technik' 'Beatbox testowy' 'Testowe Nagrania Źródłowe'; do grep -qF -- "$s" <<<"$page" && printf '%s|' "$s"; done | sed 's/|$//')"
check "nginx serves the cover" "200 image/jpeg" "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$URL/content/a/600/$cover.jpg")"
check "the single's song is the album's, by its recording id" "1" \
    "$(sql "SELECT COUNT(DISTINCT songid) FROM album_lookup WHERE songid = (SELECT songid FROM album_lookup WHERE albumid = $album AND disc = 1 AND track = 1)")"
check "a WebP photo scaled to 600 as JPEG, with its credit, and said to be changed" "480x600 image/jpeg Anna Fotograf 1 y" \
    "$(sql "SELECT CONCAT(width, 'x', height, ' ', mime, ' ', credit, ' ', modified, ' ', main) FROM artists_photos WHERE filename = '$photo.jpg'")"
check "the logo at 300, as PNG" "300x150" "$(sql "SELECT logo FROM labels WHERE name = 'Testowe Nagrania Źródłowe'" | grep -c "^$logo.png$" >/dev/null && file -b "content/l/$logo.png" | grep -oE '[0-9]+ x [0-9]+' | tr -d ' ')"
check "a band with members is a band" "b 2" "$(sql "SELECT CONCAT(type, ' ', (SELECT COUNT(*) FROM band_lookup WHERE bandid = a.id)) FROM artists a WHERE name = 'Testowy Skład'")"
check "a role nobody used before is added" "1" "$(sql "SELECT COUNT(*) FROM feattypes WHERE feattype = 'Beatbox testowy'")"
check "Eldo: the empty website and start filled, the real name kept" "https://example.com/eldo 1998-01-01 Leszek Kaźmierczak 1100" \
    "$(sql "SELECT CONCAT_WS(' ', website, since, realname, updatedby) FROM artists WHERE id = 2")"
check "album 1: found by its Discogs master, its catalogue number filled, its tracklist kept" "TEST 0001 3" \
    "$(sql "SELECT CONCAT(catalog_cd, ' ', (SELECT COUNT(*) FROM album_lookup WHERE albumid = 1)) FROM albums WHERE id = 1")"
check "Mes keeps his Discogs id" "271903" "$(sql "SELECT GROUP_CONCAT(value) FROM external_ids WHERE entity_type = 'artist' AND entity_id = 35 AND source = 'discogs'")"
check "a namesake the batch tells apart is an artist of its own, Mes himself without a qualifier" "35: new:testowy imiennik" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(IF(id = 35, '35', 'new'), ':', disambiguation) ORDER BY id SEPARATOR ' ') FROM artists WHERE name = 'Mes'")"
check "its page is under the qualified name" "1" \
    "$(curl -s "$URL$(jq -r '.documents[] | select(.ref == "artist:mes-imiennik") | .url' "$T/apply.json")" | grep -c '<h1>Mes (testowy imiennik)</h1>' || true)"
check "and the report asks a person to look at it" "1" \
    "$(jq '[.documents[] | select(.ref == "artist:mes-imiennik") | .warnings[] | select(test("for a person to review: same name as hhbd artist 35"))] | length' "$T/apply.json")"
check "the namesake and the single's stand-in cover wait for a person, on their pages" "artist:namesake album:cover_placeholder" \
    "$(sql "SELECT GROUP_CONCAT(CONCAT(entity_type, ':', reason) ORDER BY id SEPARATOR ' ') FROM review_items WHERE run_id = (SELECT MAX(id) FROM import_runs) AND resolved IS NULL")"
check "where each changed group came from, and only those" "core cover tracklist" \
    "$(sql "SELECT GROUP_CONCAT(field ORDER BY field SEPARATOR ' ') FROM import_provenance WHERE entity_type = 'album' AND entity_id = $album")"
check "Eldo's core is not credited to the batch" "facts" \
    "$(sql "SELECT GROUP_CONCAT(field) FROM import_provenance WHERE entity_type = 'artist' AND entity_id = 2")"
check "the run holds the report" "apply 11" "$(sql "SELECT CONCAT(mode, ' ', JSON_LENGTH(report, '$.documents')) FROM import_runs ORDER BY id DESC LIMIT 1")"
content >"$T/content-applied"
rows_applied=$(rows)

echo "> the same batch again"
check "the second apply reads every document" "0" "$(import "$BATCH" apply again)"
check "and changes nothing" "0 created, 0 updated, 11 unchanged, 0 refused" "$(totals again)"
check "not a row" "$rows_applied" "$(rows)"
check "not a file" "" "$(content | comm -3 "$T/content-applied" -)"
check "nor a second review item" "2" "$(sql "SELECT COUNT(*) FROM review_items WHERE run_id IS NOT NULL AND run_id > 1")"

echo "> a larger cover for the single, whose cover is a stand-in"
single_id=$(jq -r '.documents[] | select(.ref == "release:testowy-singiel") | .hhbd_id' "$T/apply.json")
mkdir -p "$T/upgrade/files"
cp "$BATCH/files/cover-album.jpg" "$T/upgrade/files/"
jq -c --argjson id "$single_id" 'select(.ref == "release:testowy-album") | {kind: "image", target: {entity: "album", ref: "hhbd:album:\($id)"}, role: "cover", file: .cover}' "$BATCH/batch.ndjson" >"$T/upgrade/batch.ndjson"
check "the import reads it" "0" "$(import "$T/upgrade" apply upgrade)"
check "it takes the stand-in's place on the page" "1 orig:1200:0 600:600:0 300:300:0 75:75:0" \
    "$(sql "SELECT COUNT(*) FROM album_covers WHERE albumid = $single_id AND main = 'n' AND variant = '300'") $(sql "SELECT GROUP_CONCAT(CONCAT(variant, ':', width, ':', needs_upgrade) ORDER BY width DESC SEPARATOR ' ') FROM album_covers WHERE albumid = $single_id AND main = 'y'")"
check "and settles the doubt about it" "replaced 1100" \
    "$(sql "SELECT CONCAT_WS(' ', resolution, resolved_by) FROM review_items WHERE entity_type = 'album' AND entity_id = $single_id AND reason = 'cover_placeholder'")"
check "the same cover again changes nothing" "0 created, 0 updated, 1 unchanged, 0 refused" "$([ "$(import "$T/upgrade" apply upgrade-again)" = 0 ] && totals upgrade-again)"

echo "> a batch with documents that do not hold up, on a fresh database and content/"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; }
remove_written >/dev/null
mkdir -p "$T/broken"
cp -R "$BATCH/files" "$T/broken/"
# The album's cover says another hash; the single names the album as its parent; one line is
# not JSON, one has a type the schema does not know; one asks for a review without a qualifier,
# one names a band member by a name two artists share (#102).
jq -c 'if .ref == "release:testowy-album" then .cover.sha256 = ("0" * 64) else . end' "$BATCH/batch.ndjson" >"$T/broken/batch.ndjson"
echo '{"kind": "label", "ref": "label:broken", "name": ' >>"$T/broken/batch.ndjson"
echo '{"kind": "artist", "ref": "artist:x", "name": "X", "type": "q"}' >>"$T/broken/batch.ndjson"
echo '{"kind": "artist", "ref": "artist:y", "name": "Pezet", "review": {"reason": "same name as hhbd artist 1"}}' >>"$T/broken/batch.ndjson"
echo '{"kind": "artist", "ref": "artist:z", "name": "Testowy Zespół Solara", "members": [{"ref": "name:Solar"}]}' >>"$T/broken/batch.ndjson"
rows_fresh=$(rows)
check "make import fails, as some were refused" "failed" "$([ "$(import "$T/broken" apply broken)" != 0 ] && echo failed)"
check "those alone" "created created created created updated unchanged created refused refused updated updated refused refused refused refused" "$(actions broken)"
check "the album for its cover's hash" "1" "$(jq '[.documents[] | select(.ref == "release:testowy-album") | .errors[] | select(test("the document says sha256"))] | length' "$T/broken.json")"
check "the single for the parent that never went in" "1" "$(jq '[.documents[] | select(.ref == "release:testowy-single" or .ref == "release:testowy-singiel") | .errors[] | select(test("release:testowy-album"))] | length' "$T/broken.json")"
check "the line that is not JSON, and the type the schema refuses" "not a JSON object|\$.type" \
    "$(jq -r '[.documents[-4].errors[0][0:17], (.documents[-3].errors[0] | split(":")[0])] | join("|")' "$T/broken.json")"
check "a review without a qualifier, and a name two artists share" "\$: disambiguation is missing|2 artists are called \"Solar\" (hhbd:artist:64 \"SBM Label\", hhbd:artist:65 \"raper z Poznania\")" \
    "$(jq -r '[.documents[-2].errors[0], (.documents[-1].errors[0] | split(";")[0])] | join("|")' "$T/broken.json")"
check "nothing of the refused album in the database" "0" "$(sql "SELECT COUNT(*) FROM albums WHERE title IN ('Testowy Album Importu', 'Testowy Singiel')")"
check "nor in content/, not even half-written" "0" "$(find content -name "$cover.jpg*" -o -name "$single.jpg*" | wc -l | tr -d ' ')"
check "and the rest went in" "1 1" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM artists WHERE name = 'Testowy Skład'), ' ', (SELECT COUNT(*) FROM albums WHERE id = 1 AND catalog_cd = 'TEST 0001'))")"
[ "$rows_fresh" != "$(rows)" ] && ok "the database changed by the documents that held up" || bad "the database changed by the documents that held up"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
