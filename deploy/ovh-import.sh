#!/usr/bin/env bash
#
# HHBD - read an import batch into production's catalogue on the shared OVH host (#56)
#
# The batch goes over ssh as a tar on stdin into a one-off importer container (the `jobs`
# profile in deploy/compose.ovh.yaml) of the running release's tag, so no copy of it is left on
# the host. Each document's line comes back on stderr as it is read, the report, JSON, on
# stdout; import_runs keeps the report too (make ovh-import-runs).
#
# ci-deploy pulls only the services `up` starts, so the importer image is pulled here first,
# logged in to GHCR for exactly as long as the pull takes, as ci-deploy does.
#
# Usage: ./deploy/ovh-import.sh <batch directory> [dry-run|apply]     (or make ovh-import)
#
set -euo pipefail

BATCH=${1:-}
MODE=${2:-dry-run}
HOST=${OVH_HOST:-}
USER_=${OVH_SSH_USER:-ubuntu}
[ -n "$HOST" ] || { echo "x OVH_HOST is not set. Put it in .env or export it." >&2; exit 2; }
[ -d "$BATCH" ] || { echo "x usage: $0 <batch directory> [dry-run|apply]" >&2; exit 2; }
case "$MODE" in
    dry-run|apply) ;;
    *) echo "x the mode is dry-run or apply, not '$MODE'" >&2; exit 2 ;;
esac
ndjson=$(find "$BATCH" -maxdepth 1 -name '*.ndjson' | wc -l | tr -d ' ')
[ "$ndjson" = 1 ] || { echo "x $BATCH holds $ndjson .ndjson files; a batch is one" >&2; exit 2; }

remote() { ${OVH_SSH:-ssh} -o BatchMode=yes "$USER_@$HOST" "$@"; }
# As root in /srv/hhbd with hhbd.enc.env's values in the environment, which compose needs to
# read the file at all; .env there names the running tag.
in_stack() { remote "cd /srv/hhbd && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env '$1'"; }

echo "> the importer image of the running release on $HOST" >&2
# shellcheck disable=SC2016 # expanded on the host, inside sops exec-env
if ! in_stack 'printf %s "$GHCR_READ_TOKEN" | docker login ghcr.io -u "$GHCR_USER" --password-stdin >/dev/null && { docker compose pull -q importer; s=$?; docker logout ghcr.io >/dev/null 2>&1; exit $s; }'; then
    echo "x no importer image for the running release; one released with #56 or later has it" >&2
    exit 2
fi

echo "> $MODE of $(basename "$BATCH") on $HOST" >&2
# No resource forks or extended attributes from macOS's tar, which GNU tar would warn about.
COPYFILE_DISABLE=1 tar --no-xattrs -C "$BATCH" -cf - . | in_stack "docker compose run --rm --no-deps -T importer --$MODE"
