#!/usr/bin/env bash
#
# HHBD - read an import batch into production's catalogue on the shared OVH host (#56)
#
# The batch goes over ssh as a tar on stdin into a one-off importer container (the `jobs`
# profile in deploy/ovh/compose.yaml) of the running release's tag, so no copy of it is left on
# the host. Each document's line comes back on stderr as it is read, the report, JSON, on
# stdout; import_runs keeps the report too (make ovh-import-runs). What the batch would publish
# waits in the release queue for the daily release (#258), unless RELEASE=now.
#
# ci-deploy pulls only the services `up` starts, so the importer image is pulled here first,
# with the host's own registry login, as ci-deploy pulls (#278, gcloud-ovh-migrate's CONTRACT.md
# §6): /srv/host/registry.enc.env, root's, opened with the host's age key for the login alone,
# its token on stdin to docker login into a configuration made for this pull and removed with
# it, so the login never reaches /root/.docker or the stack's environment.
#
# Usage: [RELEASE=now] ./deploy/ovh/import.sh <batch directory> [dry-run|apply]     (or make ovh-import)
#
set -euo pipefail

BATCH=${1:-}
MODE=${2:-dry-run}
HOST=${OVH_HOST:-}
USER_=${OVH_SSH_USER:-ubuntu}
[ -n "$HOST" ] || { echo "x OVH_HOST is not set: it lives in gcloud-ovh-migrate's .env, which make loads" >&2; exit 2; }
[ -d "$BATCH" ] || { echo "x usage: $0 <batch directory> [dry-run|apply]" >&2; exit 2; }
case "$MODE" in
    dry-run|apply) ;;
    *) echo "x the mode is dry-run or apply, not '$MODE'" >&2; exit 2 ;;
esac
case "${RELEASE:-}" in
    '') release='' how='queued for the daily release' ;;
    now) release=' --release-now' how='released at once' ;;
    *) echo "x RELEASE is now or nothing, not '$RELEASE'" >&2; exit 2 ;;
esac
ndjson=$(find "$BATCH" -maxdepth 1 -name '*.ndjson' | wc -l | tr -d ' ')
[ "$ndjson" = 1 ] || { echo "x $BATCH holds $ndjson .ndjson files; a batch is one" >&2; exit 2; }

remote() { ${OVH_SSH:-ssh} -o BatchMode=yes "$USER_@$HOST" "$@"; }
# As root in /srv/hhbd with hhbd.enc.env's values in the environment, which compose needs to
# read the file at all; .env there names the running tag.
in_stack() { remote "cd /srv/hhbd && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env '$1'"; }

echo "> the importer image of the running release on $HOST" >&2
# sh on the host, inside sops exec-env, which hands it SOPS_AGE_KEY_FILE; in_stack quotes it in
# single quotes, so it holds none. What docker says as it logs in, its warning about where the
# login is kept among it, is shown only when the registry refuses.
# shellcheck disable=SC2016 # expanded on the host
pull='set -e
plain=$(/usr/local/bin/sops -d /srv/host/registry.enc.env)
user=$(printf "%s\n" "$plain" | sed -n "s/^GHCR_USER=//p")
token=$(printf "%s\n" "$plain" | sed -n "s/^GHCR_READ_TOKEN=//p")
plain=
config=$(mktemp -d)
trap "rm -rf $config" EXIT
if ! said=$(printf %s "$token" | DOCKER_CONFIG=$config docker login ghcr.io -u "$user" --password-stdin 2>&1); then
    printf "%s\n" "$said" | tail -n 1 >&2
    exit 1
fi
DOCKER_CONFIG=$config docker compose pull -q importer'
if ! in_stack "$pull"; then
    echo "x the importer image of the running release did not pull with the host's registry login;" \
         "the lines above say why, and a release from before #56 has none" >&2
    exit 2
fi

echo "> $MODE of $(basename "$BATCH") on $HOST, $how" >&2
# No resource forks or extended attributes from macOS's tar, which GNU tar would warn about.
COPYFILE_DISABLE=1 tar --no-xattrs -C "$BATCH" -cf - . | in_stack "docker compose run --rm --no-deps -T importer --$MODE$release"
