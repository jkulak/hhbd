#!/usr/bin/env bash
#
# HHBD - production's database dumped to this Mac, for a rehearsal or a look at the data (#235)
#
# Read-only on production: mariadb-dump with --single-transaction, one consistent snapshot,
# inside the db container with the password it was started with, nothing secret passing here.
# The dump lands outside every repository, in ~/Developer/hhbd-dumps (HHBD_DUMPS), the
# directory 700 and the file 600: it holds e-mail addresses and password hashes, and this
# repository is public. Loaded only into a throwaway stack, which goes afterwards.
#
# Usage: make ovh-dump     (OVH_HOST from gcloud-ovh-migrate's .env, which make loads)
#
set -euo pipefail
HOST=${OVH_HOST:-}
[ -n "$HOST" ] || { echo "x OVH_HOST is not set: it lives in gcloud-ovh-migrate's .env, which make loads" >&2; exit 2; }
DIR=${HHBD_DUMPS:-$HOME/Developer/hhbd-dumps}
umask 077
mkdir -p "$DIR"
out="$DIR/hhbd-prod-$(date +%F).sql.gz"

echo "> dumping production's hhbd from $HOST to $out" >&2
# shellcheck disable=SC2016 # expanded inside the container, not here
ssh -o BatchMode=yes "${OVH_SSH_USER:-ubuntu}@$HOST" \
    'sudo docker exec hhbd-db-1 sh -c '\''MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --hex-blob --default-character-set=utf8mb4 --skip-dump-date "$MYSQL_DATABASE"'\''' \
    | gzip >"$out.part"
mv "$out.part" "$out"
echo "ok $out, $(du -h "$out" | cut -f1)"
