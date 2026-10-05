#!/usr/bin/env bash
#
# HHBD - roll a release out to the OVH host
#
# CONTRACT.md §6 in jkulak/gcloud-ovh-migrate, the same way for every service: read the tag
# running now, deploy the new one, smoke-test, and on a failed smoke test deploy the tag read
# at the start. A release that never becomes healthy is not this script's to undo: ci-deploy
# takes it back on its own and fails, and so does this.
#
# Usage: deploy/ovh-release.sh <tag>     (run by .github/workflows/release.yml)
#
# Environment:
#   OVH_HOST             the host
#   OVH_DEPLOY_KEY_FILE  the private key pinned to ci-deploy (default ~/.ssh/ovh-deploy);
#                        the host key must already be in known_hosts
#   SMOKE_VIA_ORIGIN     "true" while hhbd.pl does not point at the OVH host yet: the smoke test
#                        then goes to the host directly instead of through Cloudflare. Must be
#                        off once the edge only lets Cloudflare in.
#   CI_DEPLOY_SSH, SMOKE replace the ssh hop and the smoke test; used by tests/ovh-release-test.sh
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

TAG=${1:?usage: $0 <tag>}
SERVICE=hhbd
URL=https://hhbd.pl

ci() { # ci <command>: one line of ci-deploy's grammar, nothing else reaches the host
    if [ -n "${CI_DEPLOY_SSH:-}" ]; then
        $CI_DEPLOY_SSH "$1"
    else
        ssh -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes \
            -i "${OVH_DEPLOY_KEY_FILE:-$HOME/.ssh/ovh-deploy}" "deploy@${OVH_HOST:?OVH_HOST is not set}" "$1"
    fi
}

smoke() {
    local opts=""
    if [ "${SMOKE_VIA_ORIGIN:-}" = true ]; then
        # The edge's certificate for hhbd.pl is not publicly trusted before the DNS moves.
        opts="--connect-to hhbd.pl:443:${OVH_HOST:?OVH_HOST is not set}:443 --insecure"
    fi
    SMOKE_CURL_OPTS="$opts" ${SMOKE:-./tests/smoke-test.sh} "$URL"
}

previous=$(ci "status $SERVICE")
echo "> $SERVICE runs $previous; deploying $TAG"

# Fails when the release does not become healthy; ci-deploy has then put $previous back.
ci "deploy $SERVICE $TAG"

echo "> smoke test against $URL$([ "${SMOKE_VIA_ORIGIN:-}" = true ] && echo " on the OVH host directly")"
if smoke; then
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
