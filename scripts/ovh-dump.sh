#!/usr/bin/env bash
#
# HHBD - production's database dumped to this Mac, for a rehearsal or a look at the data (#235)
#
# Read-only on production: pg_dump in the postgres container, as its superuser over the
# socket, one consistent snapshot, nothing secret passing here (#236). DB_ENGINE=mariadb dumps
# MariaDB instead, with the data as it was at the move, until #237: mariadb-dump with
# --single-transaction, inside the db container with the password it was started with.
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
# shellcheck disable=SC2016 # expanded inside the container, not here
case "${DB_ENGINE:-postgresql}" in
    postgresql)
        out="$DIR/hhbd-prod-$(date +%F).pg.sql.gz"
        dump='sudo docker exec hhbd-postgres-1 sh -c '\''exec pg_dump -U "$POSTGRES_USER" --no-owner --no-privileges "$POSTGRES_DB"'\'''
        ;;
    mariadb)
        out="$DIR/hhbd-prod-$(date +%F).sql.gz"
        dump='sudo docker exec hhbd-db-1 sh -c '\''MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb-dump -uroot --single-transaction --hex-blob --default-character-set=utf8mb4 --skip-dump-date "$MYSQL_DATABASE"'\'''
        ;;
    *) echo "x DB_ENGINE is postgresql or mariadb, not '$DB_ENGINE'" >&2; exit 2 ;;
esac

echo "> dumping production's hhbd from $HOST to $out" >&2
ssh -o BatchMode=yes "${OVH_SSH_USER:-ubuntu}@$HOST" "$dump" | gzip >"$out.part"
mv "$out.part" "$out"
echo "ok $out, $(du -h "$out" | cut -f1)"
