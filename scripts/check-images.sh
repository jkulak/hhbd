#!/usr/bin/env bash
#
# Every cover, thumbnail, artist photo and label logo the catalogue names, checked against the
# content volume (#47): how many each kind names, how many are missing, and the missing files.
# Exits 1 when a file is missing, so CI can run it after loading the fixtures and generating
# their images.
#
# Usage: scripts/check-images.sh
#
# Where it looks, DB_TARGET: local (this checkout's compose project, the default; the files
# in its nginx container) or ovh (production's database over ssh, and the files in hhbd-nginx-1,
# the only container that mounts the volume there).
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=scripts/lib-db.sh
. scripts/lib-db.sh
REFUSE_AS=check-images

target=${DB_TARGET:-local}
use_db_target "$target"
case "$target" in
    local) content_sh() { docker compose exec -T nginx sh -c "$1"; } ;;
    ovh)
        # One static command over ssh, the list on stdin, as for the database.
        content_sh() {
            # shellcheck disable=SC2086
            ${MIGRATE_SSH:-ssh -o BatchMode=yes} "${OVH_SSH_USER:-ubuntu}@$OVH_HOST" \
                "${OVH_SUDO-sudo} docker exec -i ${OVH_NGINX_CONTAINER:-hhbd-nginx-1} sh -c '$1'"
        }
        ;;
    *) refuse "DB_TARGET is local or ovh, not '$target'" ;;
esac

# kind<TAB>path under content/, as the pages build the paths (Model_Album_Container,
# Model_Image_Api, Model_Label_Container).
files=$(printf '%s\n' "
SELECT 'cover', CONCAT('a/', cover) FROM albums WHERE cover <> ''
UNION ALL SELECT 'thumbnail', CONCAT('a/th/', LEFT(cover, CHAR_LENGTH(cover) - 4), '-th.jpg') FROM albums WHERE cover <> ''
UNION ALL SELECT 'photo', CONCAT('p/', filename) FROM artists_photos WHERE filename <> ''
UNION ALL SELECT 'logo', CONCAT('l/', logo) FROM labels WHERE logo <> '';" | db_sql)

missing=$(printf '%s\n' "$files" | content_sh 'while IFS="	" read -r kind path; do [ -f "/var/www/html/content/$path" ] || printf "%s\t%s\n" "$kind" "$path"; done')

# Every album_covers row (#60): its file is there and still has the hash the row recorded.
# Before migration 0019 there is no such table, and nothing to check.
covers=
if [ "$(printf '%s\n' "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'album_covers';" | db_sql)" = 1 ]; then
    covers=$(printf '%s\n' "SELECT 'cover file', path, sha256 FROM album_covers;" | db_sql)
fi
# And every artist photo with a recorded hash (#61), from 0020 on.
if [ "$(printf '%s\n' "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'artists_photos' AND column_name = 'sha256';" | db_sql)" = 1 ]; then
    covers=$(printf '%s\n%s\n' "$covers" "$(printf '%s\n' "SELECT 'photo file', CONCAT('p/', filename), sha256 FROM artists_photos WHERE sha256 IS NOT NULL;" | db_sql)" | grep . || true)
fi
changed=$(printf '%s\n' "$covers" | content_sh 'while IFS="	" read -r kind path sum; do [ -n "$path" ] || continue; f="/var/www/html/content/$path"; if [ ! -f "$f" ]; then printf "%s\t%s\t%s\n" "$kind" "$path" "missing"; elif [ "$(sha256sum "$f" | cut -d" " -f1)" != "$sum" ]; then printf "%s\t%s\t%s\n" "$kind" "$path" "changed"; fi; done')
if [ -n "$changed" ]; then
    missing=$(printf '%s\n%s' "$missing" "$changed" | grep . || true)
fi

echo "images the catalogue names on $where"
for kind in cover thumbnail photo logo "cover file" "photo file"; do
    named=$(printf '%s\n%s\n' "$files" "$covers" | awk -F'\t' -v k="$kind" '$1 == k' | grep -c . || true)
    lost=$(printf '%s\n' "$missing" | awk -F'\t' -v k="$kind" '$1 == k' | grep -c . || true)
    printf '  %-10s %5s named, %5s missing or changed\n' "$kind" "$named" "$lost"
done
if [ -n "$missing" ]; then
    echo "missing:"
    printf '%s\n' "$missing" | awk -F'\t' '{ print "  content/" $2 ($3 == "changed" ? " (its hash differs from album_covers)" : "") }'
    exit 1
fi
echo "ok every file is there"
