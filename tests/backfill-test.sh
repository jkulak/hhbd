#!/usr/bin/env bash
#
# The cover and photo backfills (#60, #61) on the running local stack, after the fixtures and
# their generated images:
#   - a dry run reports exactly what the run then does, and leaves no row behind
#   - the run describes the covers and photos on content/
#   - a second run finds everything there and adds nothing
#
# It leaves the backfilled rows in place, as production has them, for the smoke test that reads
# them; make reset-db takes them away.
#
# DB_ENGINE=postgresql checks them on PostgreSQL's database, which the stack's app then uses
# (compose.postgresql.yaml, #234).
#
# Usage: [DB_ENGINE=postgresql] tests/backfill-test.sh
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=tests/lib-test-db.sh
. tests/lib-test-db.sh

pass=0
fail=0

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1"; echo "     expected: $2"; echo "     got:      $3"; fi; }

rows() { sql "SELECT CONCAT_WS(' ', (SELECT COUNT(*) FROM album_covers), (SELECT COUNT(*) FROM artists_photos WHERE sha256 IS NOT NULL))"; }

check "the covers and photos start undescribed" "0 0" "$(rows)"

echo "> covers"
dry=$(make -s covers-backfill DRY_RUN=1)
check "a dry run leaves no row behind" "0 0" "$(rows)"
run=$(make -s covers-backfill)
check "a dry run reports what the run does" "${dry/#would add/ok}" "$run"
# A tool that failed may have printed nothing, and the dry run alike.
case "$run" in
    "ok covers: orig 0, 600 0, 300 0, 75 0 added;"*) bad "the run describes the covers ($run)" ;;
    "ok covers: "*) ok "the run describes the covers ($(sql 'SELECT COUNT(*) FROM album_covers') rows)" ;;
    *) bad "the run describes the covers (${run:-no report})" ;;
esac
check "a second run adds nothing" "orig 0, 600 0, 300 0, 75 0 added" \
    "$(make -s covers-backfill | sed -n 's/^ok covers: \([^;]*\);.*/\1/p')"
check "and a dry run then says so" "orig 0, 600 0, 300 0, 75 0 added" \
    "$(make -s covers-backfill DRY_RUN=1 | sed -n 's/^would add covers: \([^;]*\);.*/\1/p')"

echo "> photos"
covers=$(sql 'SELECT COUNT(*) FROM album_covers')
dry=$(make -s photos-backfill DRY_RUN=1 2>/dev/null)
check "a dry run leaves no row behind" "$covers 0" "$(rows)"
run=$(make -s photos-backfill 2>/dev/null)
check "a dry run reports what the run does" "${dry/#would fill/ok}" "$run"
case "$run" in
    "ok photos: 0 filled;"*) bad "the run describes the photos ($run)" ;;
    "ok photos: "*) ok "the run describes the photos ($(sql 'SELECT COUNT(*) FROM artists_photos WHERE sha256 IS NOT NULL') rows)" ;;
    *) bad "the run describes the photos (${run:-no report})" ;;
esac
check "a second run fills nothing" "0 filled" \
    "$(make -s photos-backfill 2>/dev/null | sed -n 's/^ok photos: \([^;]*\);.*/\1/p')"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
