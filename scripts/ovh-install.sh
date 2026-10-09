#!/usr/bin/env bash
# Put this service's configuration on the shared OVH host, the way gcloud-ovh-migrate's
# CONTRACT.md §1 says configuration arrives: from this Mac, as the admin account, by rsync.
# Starts nothing — the stack's first start and every image roll is CI's
# `deploy <service> <tag>`.
#
# From gcloud-ovh-migrate's service template, word for word the same in every service; what
# differs lives in deploy/ovh/service.env. OVH_HOST comes from that repo's .env, which
# deploy/ovh/ovh.mk loads.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=/dev/null
. deploy/ovh/service.env

HOST=${OVH_HOST:-}
USER_=${OVH_SSH_USER:-ubuntu}
[ -n "$HOST" ] || { echo "x OVH_HOST is not set: it lives in gcloud-ovh-migrate's .env, which make loads" >&2; exit 1; }
[[ ${SERVICE:-} =~ ^[a-z][a-z0-9-]*$ ]] || { echo "x deploy/ovh/service.env names no SERVICE" >&2; exit 1; }

SECRETS=deploy/ovh/$SERVICE.enc.env
[ -f "$SECRETS" ] || { echo "x $SECRETS does not exist: make ovh-secrets-set KEY=… first" >&2; exit 1; }
# Never a stack without its secrets, and never one whose secrets are readable.
./scripts/secrets-check.sh

remote() { ssh -o BatchMode=yes "$USER_@$HOST" "$@"; }
# No --chmod: macOS ships openrsync, which does not have it. New files take the source's mode
# masked by root's umask.
push() { rsync -az --no-perms --rsync-path="sudo rsync" "$@"; }

timers=()
if [ -d deploy/ovh/systemd ]; then
  while IFS= read -r t; do timers+=("$(basename "$t" .timer)"); done \
    < <(find deploy/ovh/systemd -name "$SERVICE-*.timer" | sort)
fi
for job in ${TIMERS:-}; do
  printf '%s\n' ${timers[@]+"${timers[@]}"} | grep -qx "$SERVICE-$job" \
    || { echo "x TIMERS names $job, but there is no deploy/ovh/systemd/$SERVICE-$job.timer" >&2; exit 1; }
done

remote "sudo mkdir -p /srv/$SERVICE"
push deploy/ovh/compose.yaml "$SECRETS" "$USER_@$HOST:/srv/$SERVICE/"
# Encrypted, and still root's alone to read: rsync leaves a new file to root's umask.
remote "sudo chmod 600 /srv/$SERVICE/$SERVICE.enc.env"

# Only this service's snippets, and nothing deleted from sites/: the rest are other services'.
# Each one there now is kept beside it as .prev first, which the edge's *.caddyfile never reads.
snippets=()
for f in deploy/ovh/*.caddyfile; do snippets+=("$(basename "$f")"); done
remote "cd /srv/edge/sites && for s in ${snippets[*]}; do if [ -e \"\$s\" ]; then sudo cp -p \"\$s\" \"\$s.prev\"; else sudo rm -f \"\$s.prev\"; fi; done"
push deploy/ovh/*.caddyfile "$USER_@$HOST:/srv/edge/sites/"
[ ${#timers[@]} -eq 0 ] || push deploy/ovh/systemd/ "$USER_@$HOST:/etc/systemd/system/"

# A reload, not a restart: other services' connections survive. A snippet that does not load
# leaves the old configuration serving, but it would stay on disk, and the edge's next restart
# — a reboot, an upgrade — would refuse to start at all: so the snippets before go back.
reload='sudo docker exec edge-caddy-1 caddy reload --config /etc/caddy/Caddyfile'
if ! remote "$reload"; then
  remote "cd /srv/edge/sites && for s in ${snippets[*]}; do if [ -e \"\$s.prev\" ]; then sudo mv -f \"\$s.prev\" \"\$s\"; else sudo rm -f \"\$s\"; fi; done && $reload" \
    || echo "x and the snippets before did not load either: the edge needs a hand" >&2
  echo "x the edge refused this service's snippets (${snippets[*]}); the ones before are back in place" >&2
  exit 1
fi
remote "cd /srv/edge/sites && sudo rm -f ${snippets[*]/%/.prev}"

if [ ${#timers[@]} -gt 0 ]; then
  remote 'sudo systemctl daemon-reload'
  for unit in "${timers[@]}"; do
    if [[ " ${TIMERS:-} " == *" ${unit#"$SERVICE"-} "* ]]; then
      remote "sudo systemctl enable --now $unit.timer"
    else
      remote "sudo systemctl disable --now $unit.timer 2>/dev/null || true"
    fi
  done
fi
echo "ok $SERVICE installed: compose.yaml, $SECRETS, ${snippets[*]}, timers on: ${TIMERS:-none}"
