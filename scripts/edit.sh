#!/usr/bin/env bash
#
# HHBD - an admin's edit to the catalogue, journalled and undoable (#115), on the local stack or
# on production: app/tools/edit.php in the app container, as make import and make ovh-import
# reach theirs. Each row the edit changes is one line on stdout.
#
# The call comes from the environment, so `make edit` needs no quoting of its own:
#   DO     the operation and its ids: "merge-albums 850 841", "delete-artist 2197", "undo 3",
#          "set albums 841 title" (the value in VALUE, or VALUE unset for NULL), "set albums 841"
#          (the columns in VALUE as a JSON object, '{"title": "...", "notes": null}')
#   BY     the display name of an hhbd admin, WHY why; neither may be empty
#   MODE   apply to write; anything else, or nothing, is a dry run
#
# The arguments travel to the tool NUL-separated on stdin, so no shell on the way, the host's
# or the container's, reads WHY or VALUE again.
#
# Usage: DO=... BY=... WHY=... [MODE=apply] scripts/edit.sh local|ovh     (or make edit / ovh-edit)
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

TARGET=${1:-}
[ -n "${DO:-}" ] || { echo "x DO is the operation and its ids, e.g. DO=\"merge-albums 850 841\"" >&2; exit 2; }
read -r -a words <<<"$DO"
args=("${words[@]}")
if [ "${words[0]}" = set ]; then
    if [ "${VALUE+set}" = set ]; then args+=("$VALUE"); else args+=(--null); fi
fi
args+=("--by=${BY:-}" "--why=${WHY:-}")
[ "${MODE:-}" = apply ] && args+=(--apply)

case "$TARGET" in
    local)
        printf '%s\0' "${args[@]}" | docker compose exec -T app php /var/www/html/app/tools/edit.php -
        ;;
    ovh)
        HOST=${OVH_HOST:?OVH_HOST is not set: it lives in gcloud-ovh-migrate\'s .env, which make loads}
        # As root in /srv/hhbd with hhbd.enc.env's values in the environment, which compose needs
        # to read its file; the app image is the running release's, already on the host.
        printf '%s\0' "${args[@]}" | ${OVH_SSH:-ssh} -o BatchMode=yes "${OVH_SSH_USER:-ubuntu}@$HOST" \
            "cd /srv/hhbd && sudo SOPS_AGE_KEY_FILE=/etc/sops/age.key /usr/local/bin/sops exec-env hhbd.enc.env 'docker compose run --rm --no-deps -T app php /var/www/html/app/tools/edit.php -'"
        ;;
    *)
        echo "x usage: $0 local|ovh" >&2
        exit 2
        ;;
esac
