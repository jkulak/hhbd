#!/usr/bin/env bash
#
# How many queries a page sends to the database (#255), on the running local stack, by the header
# X-Query-Count that compose.yaml's QUERY_COUNT_HEADER turns on:
#   - on the fixtures, an album's, an artist's, a song's and a label's page, the home page, the
#     album and artist lists and the top 10 each send at most 25
#   - with a row of each kind more (a band with albums for the artist, a track with credits, the
#     song on another album, another album on the label, a name and a photo for every artist),
#     still at most 25
#   - with many more of each (every published album twice, ten more tracks, more bands, albums
#     and names), the same number as with one: a page reads its rows' details a kind at a time,
#     never row by row
#
# Each page is opened once before it is counted: a song's first view after a reset stores its
# video's address. It ends with make reset-db, so the database ends as a reset leaves it.
#
# Usage: tests/query-count-test.sh [base URL of the running site, default http://localhost:8080]
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

URL=${1:-http://localhost:8080}
LIMIT=25
pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }

# The canonical addresses, so no redirect is counted in place of the page
PAGES=(
    /
    /albumy.html
    /wykonawcy.html
    /top10.html
    /wdowa-superextra-a535.html
    /mes-p35.html
    /domowa-produkcja-s9102.html
    /alkopoligamia-l58.html
)

# queries <path>: the page's X-Query-Count, empty when it sent none
queries() {
    curl -s -o /dev/null -D - --max-time 20 "$URL$1" | tr -d '\r' | sed -n 's/^[Xx]-[Qq]uery-[Cc]ount: //p'
}

# links <path> <letter>: how many links the page has to pages of that kind: a, p, s or l
links() {
    curl -s --max-time 20 "$URL$1" | { grep -oE -- "-$2[0-9]+\.html" || true; } | wc -l | tr -d ' '
}

# Each page that lists rows of its own, and the kind of row it lists
LISTS=(
    "/wdowa-superextra-a535.html s"
    "/mes-p35.html a"
    "/domowa-produkcja-s9102.html a"
    "/alkopoligamia-l58.html a"
)

# count_all <file>: each page once to warm it, then its count, one "path count" line each
count_all() {
    local page
    for page in "${PAGES[@]}"; do
        curl -s -o /dev/null --max-time 20 "$URL$page"
        echo "$page $(queries "$page")"
    done >"$1"
}

# at_most <file> <what>: every page in the file within the limit
at_most() {
    local page n
    while read -r page n; do
        if [ -z "$n" ]; then
            bad "$page $2: no X-Query-Count (is QUERY_COUNT_HEADER set?)"
        elif [ "$n" -le "$LIMIT" ]; then
            ok "$page $2: $n queries"
        else
            bad "$page $2: $n queries, more than $LIMIT"
        fi
    done <"$1"
}

T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-querycount.XXXXXX")
cleanup() {
    make -s reset-db >/dev/null 2>&1 || echo "x make reset-db failed on the way out" >&2
    rm -rf "$T"
}
trap cleanup EXIT

echo "> make reset-db, the fixtures"
make -s reset-db >"$T/out" 2>&1 || { bad "make reset-db succeeds"; cat "$T/out"; exit 1; }
count_all "$T/fixtures"
at_most "$T/fixtures" "on the fixtures"

echo "> a row of each kind more"
sql_in <<'SQL'
-- Mes in a band with published albums, so his page lists its albums
INSERT INTO band_lookup (artistid, bandid) VALUES (35, 2) ON CONFLICT DO NOTHING;
-- Album 535 a track more, and every song on it a guest, music, scratches and an artist credit
INSERT INTO album_lookup (songid, albumid, disc, track)
    SELECT id, 535, 2, 1 FROM songs WHERE id NOT IN (SELECT songid FROM album_lookup WHERE albumid = 535) ORDER BY id LIMIT 1;
INSERT INTO feature_lookup (songid, artistid, feattype) SELECT songid, 35, 0 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO music_lookup (songid, artistid) SELECT songid, 2 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO scratch_lookup (songid, artistid) SELECT songid, 1 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO artist_lookup (songid, artistid) SELECT songid, 3 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
-- Song 9102 on another album
INSERT INTO album_lookup (songid, albumid, disc, track) VALUES (9102, 535, 3, 1) ON CONFLICT DO NOTHING;
-- Label 58 another album
UPDATE albums SET labelid = 58 WHERE id = (SELECT min(id) FROM albums WHERE status = 999 AND labelid IS DISTINCT FROM 58);
-- Every artist a name more, and a main photo, a copy of the first one, where it has none
INSERT INTO altnames_lookup (artistid, altname) SELECT id, name || ' I' FROM artists ON CONFLICT DO NOTHING;
CREATE TEMP TABLE copy AS
    SELECT a.id AS artist, p.* FROM artists AS a CROSS JOIN (SELECT * FROM artists_photos WHERE main = 'y' ORDER BY id LIMIT 1) AS p
    WHERE NOT EXISTS (SELECT 1 FROM artists_photos WHERE artistid = a.id AND main = 'y');
UPDATE copy SET id = artist + 100000, artistid = artist, sha256 = md5(CAST(artist AS text));
ALTER TABLE copy DROP COLUMN artist;
INSERT INTO artists_photos SELECT * FROM copy;
SQL
count_all "$T/one"
at_most "$T/one" "with a row of each kind more"
for list in "${LISTS[@]}"; do
    read -r page kind <<<"$list"
    echo "$page $(links "$page" "$kind")"
done >"$T/one-links"

echo "> many more of each"
sql_in <<'SQL'
-- Every published album again under its id + 100000, with its artists, tracks and covers: every
-- song on twice as many albums, every artist and label with twice as many
CREATE TEMP TABLE copy AS SELECT * FROM albums WHERE status = 999;
UPDATE copy SET id = id + 100000;
INSERT INTO albums SELECT * FROM copy;
DROP TABLE copy;
CREATE TEMP TABLE copy AS SELECT * FROM album_artist_lookup WHERE albumid + 100000 IN (SELECT id FROM albums);
UPDATE copy SET albumid = albumid + 100000;
INSERT INTO album_artist_lookup SELECT * FROM copy;
DROP TABLE copy;
CREATE TEMP TABLE copy AS SELECT * FROM album_lookup WHERE albumid + 100000 IN (SELECT id FROM albums);
UPDATE copy SET albumid = albumid + 100000;
INSERT INTO album_lookup SELECT * FROM copy;
DROP TABLE copy;
CREATE TEMP TABLE copy AS SELECT * FROM album_covers WHERE albumid + 100000 IN (SELECT id FROM albums);
UPDATE copy SET id = id + 100000, albumid = albumid + 100000;
INSERT INTO album_covers SELECT * FROM copy;
DROP TABLE copy;
-- Album 535 ten tracks more, with the same credits
INSERT INTO album_lookup (songid, albumid, disc, track)
    SELECT id, 535, 4, row_number() OVER (ORDER BY id) FROM songs
    WHERE id NOT IN (SELECT songid FROM album_lookup WHERE albumid = 535) ORDER BY id LIMIT 10;
INSERT INTO feature_lookup (songid, artistid, feattype) SELECT songid, 35, 0 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO music_lookup (songid, artistid) SELECT songid, 2 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO scratch_lookup (songid, artistid) SELECT songid, 1 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
INSERT INTO artist_lookup (songid, artistid) SELECT songid, 3 FROM album_lookup WHERE albumid = 535 ON CONFLICT DO NOTHING;
-- Song 9102 on one more album, Mes in two more bands, label 58 five more albums
INSERT INTO album_lookup (songid, albumid, disc, track) VALUES (9102, 31, 2, 1) ON CONFLICT DO NOTHING;
INSERT INTO band_lookup (artistid, bandid) VALUES (35, 1), (35, 3) ON CONFLICT DO NOTHING;
UPDATE albums SET labelid = 58 WHERE id IN (SELECT id FROM albums WHERE status = 999 AND id < 100000 AND labelid IS DISTINCT FROM 58 ORDER BY id LIMIT 5);
-- Every artist another name
INSERT INTO altnames_lookup (artistid, altname) SELECT id, name || ' II' FROM artists ON CONFLICT DO NOTHING;
SQL
count_all "$T/many"
for list in "${LISTS[@]}"; do
    read -r page kind <<<"$list"
    before=$(awk -v p="$page" '$1 == p { print $2 }' "$T/one-links")
    after=$(links "$page" "$kind")
    if [ "$after" -gt "$before" ]; then
        ok "$page lists more rows: $after links to -$kind pages, $before before"
    else
        bad "$page lists no more rows: $after links to -$kind pages, $before before"
    fi
done
while read -r page n; do
    m=$(awk -v p="$page" '$1 == p { print $2 }' "$T/one")
    if [ -n "$n" ] && [ "$n" = "$m" ]; then
        ok "$page with many more rows: $n queries, as with one"
    else
        bad "$page with many more rows: ${n:-none} queries, ${m:-none} with one"
    fi
done <"$T/many"

echo
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
