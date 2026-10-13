#!/usr/bin/env bash
#
# HHBD - production's database dumped to this Mac, for a rehearsal or a look at the data (#235)
#
# Read-only on production: pg_dump in the postgres container, as its superuser over the
# socket, one consistent snapshot, nothing secret passing here (#236).
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
out="$DIR/hhbd-prod-$(date +%F).pg.sql.gz"
# shellcheck disable=SC2016 # expanded inside the container, not here
dump='sudo docker exec hhbd-postgres-1 sh -c '\''exec pg_dump -U "$POSTGRES_USER" --no-owner --no-privileges "$POSTGRES_DB"'\'''

echo "> dumping production's hhbd from $HOST to $out" >&2
ssh -o BatchMode=yes "${OVH_SSH_USER:-ubuntu}@$HOST" "$dump" | gzip >"$out.part"
mv "$out.part" "$out"
echo "ok $out, $(du -h "$out" | cut -f1)"
