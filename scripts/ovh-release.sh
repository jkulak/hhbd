#!/usr/bin/env bash
# Roll a release out to the shared OVH host, as gcloud-ovh-migrate's CONTRACT.md §6 has every
# service do it: read the tag running now, deploy the new one, run the service's smoke test,
# and on a failed smoke test deploy the tag read at the start. A release that never becomes
# healthy is not this script's to undo: ci-deploy takes it back on its own and fails, and so
# does this.
#
# From gcloud-ovh-migrate's service template, word for word the same in every service. What
# the smoke test asks is the service's own: deploy/ovh/smoke.sh.
#
#   scripts/ovh-release.sh <tag>     run by .github/workflows/deploy.yml
#
# Environment:
#   OVH_HOST             the host
#   OVH_DEPLOY_KEY_FILE  the private key pinned to ci-deploy (default ~/.ssh/ovh-deploy); the
#                        host's key must already be in known_hosts
#   CI_DEPLOY_SSH, SMOKE stand in for the ssh hop and the smoke test, in the platform's tests
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=/dev/null
. deploy/ovh/service.env

TAG=${1:?usage: $0 <tag>}
[[ ${SERVICE:-} =~ ^[a-z][a-z0-9-]*$ ]] || { echo "x deploy/ovh/service.env names no SERVICE" >&2; exit 1; }

ci() { # ci <command>: one line of ci-deploy's grammar; nothing else reaches the host
  if [ -n "${CI_DEPLOY_SSH:-}" ]; then
    $CI_DEPLOY_SSH "$1"
  else
    ssh -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes \
      -i "${OVH_DEPLOY_KEY_FILE:-$HOME/.ssh/ovh-deploy}" "deploy@${OVH_HOST:?OVH_HOST is not set}" "$1"
  fi
}

previous=$(ci "status $SERVICE")
echo "> $SERVICE runs $previous; deploying $TAG"

# Fails when the release does not become healthy; ci-deploy has then put $previous back.
ci "deploy $SERVICE $TAG"

smoke=${SMOKE:-./deploy/ovh/smoke.sh}
echo "> smoke test: $smoke"
if "$smoke"; then
  echo "ok $SERVICE runs $TAG"
  exit 0
fi

if [ "$previous" = none ] || [ "$previous" = "$TAG" ]; then
  echo "x the smoke test failed on $TAG and there is no earlier tag to go back to; $SERVICE needs a hand" >&2
  exit 1
fi
echo "x the smoke test failed on $TAG; deploying $previous again" >&2
ci "deploy $SERVICE $previous"
echo "x $SERVICE is back on $previous; $TAG failed its smoke test" >&2
exit 1
