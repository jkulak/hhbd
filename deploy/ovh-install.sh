#!/usr/bin/env bash
#
# HHBD - install the configuration on the shared OVH host
#
# CONTRACT.md §1 in jkulak/gcloud-ovh-migrate: configuration arrives on the host only this
# way, from this Mac, as the admin account. It puts three files in place:
#
#   deploy/compose.ovh.yaml    -> /srv/hhbd/compose.yaml
#   deploy/hhbd.enc.env        -> /srv/hhbd/hhbd.enc.env      (encrypted; decrypted by ci-deploy)
#   deploy/hhbd.pl.caddyfile   -> /srv/edge/sites/hhbd.pl.caddyfile
#
# and reloads the edge. Images are not touched: they roll out through the release workflow.
# There are no scheduled jobs, so no systemd units.
#
# Usage: OVH_HOST=<address> ./deploy/ovh-install.sh     (or make ovh-install with OVH_HOST in .env)
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

HOST=${OVH_HOST:-}
USER_=${OVH_SSH_USER:-ubuntu}
[ -n "$HOST" ] || { echo "x OVH_HOST is not set. Put it in .env or export it." >&2; exit 1; }

COMPOSE=deploy/compose.ovh.yaml
SECRETS=deploy/hhbd.enc.env
SNIPPET=deploy/hhbd.pl.caddyfile
for f in "$COMPOSE" "$SECRETS" "$SNIPPET"; do
    [ -f "$f" ] || { echo "x $f is missing" >&2; exit 1; }
done

# The same gate a commit goes through: a secrets file with a plaintext value, or one the host
# key cannot open, must not reach the host either.
./scripts/secrets-check.sh

remote() { ssh -o BatchMode=yes "$USER_@$HOST" "$@"; }
# /srv is root-owned, so copies go through sudo rsync. No --chmod: macOS ships openrsync,
# which does not have it; modes that matter are set explicitly after the copy.
push() { rsync -az --no-perms --rsync-path="sudo rsync" "$@"; }

echo "> /srv/hhbd on $HOST"
remote 'sudo install -d -m 0755 /srv/hhbd'
push "$COMPOSE" "$USER_@$HOST:/srv/hhbd/compose.yaml"
push "$SECRETS" "$USER_@$HOST:/srv/hhbd/hhbd.enc.env"
remote 'sudo chmod 0600 /srv/hhbd/hhbd.enc.env'
echo "ok compose.yaml and hhbd.enc.env in place"

# The snippet arrives under a name the edge does not import (it imports sites/*.caddyfile),
# replaces the live one, and stays only if the edge accepts it. A snippet Caddy refuses would
# otherwise sit on disk and fail the next reload of every other service's snippet too.
echo "> the edge snippet"
push "$SNIPPET" "$USER_@$HOST:/srv/edge/sites/hhbd.pl.caddyfile.new"
remote 'set -e
    cd /srv/edge/sites
    reload() { sudo docker exec edge-caddy-1 caddy reload --config /etc/caddy/Caddyfile; }
    if [ -f hhbd.pl.caddyfile ]; then sudo cp -p hhbd.pl.caddyfile hhbd.pl.caddyfile.prev; else sudo rm -f hhbd.pl.caddyfile.prev; fi
    sudo mv hhbd.pl.caddyfile.new hhbd.pl.caddyfile
    if reload; then
        sudo rm -f hhbd.pl.caddyfile.prev
        exit 0
    fi
    if [ -f hhbd.pl.caddyfile.prev ]; then sudo mv hhbd.pl.caddyfile.prev hhbd.pl.caddyfile; else sudo rm -f hhbd.pl.caddyfile; fi
    reload >/dev/null 2>&1 || true
    echo "x the edge refused the snippet; the previous one is back" >&2
    exit 1'
echo "ok edge reloaded with hhbd.pl.caddyfile"

echo ""
echo "Next: make edge-smoke in gcloud-ovh-migrate, and a release through the workflow."
