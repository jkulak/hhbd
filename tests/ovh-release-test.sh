#!/usr/bin/env bash
#
# deploy/ovh-release.sh against a stand-in for the host's ci-deploy and a stand-in smoke test,
# so every path of a release is exercised without a host: what it sends, in which order, and
# how it ends.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

# Exported, since the stand-ins below find their state through it.
export T
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-releasetest.XXXXXX")
trap 'rm -rf "$T"' EXIT
pass=0
fail=0

# The stand-in ci-deploy: answers status from $T/running, records every command in $T/sent,
# and fails a deploy of any tag containing "unhealthy", as the host does after going back.
cat >"$T/ci-deploy" <<'SH'
#!/usr/bin/env bash
echo "$1" >>"$T/sent"
case "$1" in
    "status hhbd") cat "$T/running" ;;
    "deploy hhbd "*unhealthy*) exit 1 ;;
    "deploy hhbd "*) echo "${1#deploy hhbd }" >"$T/running" ;;
    *) exit 64 ;;
esac
SH
# The stand-in smoke test: fails while $T/smoke-fails exists, and records its curl options.
cat >"$T/smoke" <<'SH'
#!/usr/bin/env bash
echo "${SMOKE_CURL_OPTS:-}" >"$T/smoke-opts"
[ ! -e "$T/smoke-fails" ]
SH
chmod +x "$T/ci-deploy" "$T/smoke"

release() { # release <running before> <tag> [smoke fails] -> sets $status
    echo "$1" >"$T/running"
    rm -f "$T/sent" "$T/smoke-opts" "$T/smoke-fails"
    [ "${3:-}" = "smoke fails" ] && touch "$T/smoke-fails"
    status=0
    CI_DEPLOY_SSH="$T/ci-deploy" SMOKE="$T/smoke" OVH_HOST=ovh.example \
        ./deploy/ovh-release.sh "$2" >"$T/out" 2>&1 || status=$?
}
check() { # check <what> <expected> <actual>
    if [ "$2" = "$3" ]; then echo "ok   $1"; pass=$((pass + 1))
    else echo "x    $1"; echo "     expected: $2"; echo "     got:      $3"; fail=$((fail + 1)); fi
}
sent() { tr '\n' ';' <"$T/sent"; }

release none v2026.10.0
check "a first release reads the status, deploys and succeeds" \
    "0 status hhbd;deploy hhbd v2026.10.0;" "$status $(sent)"

release v2026.10.0 v2026.10.1
check "a release over an earlier one succeeds and runs the new tag" \
    "0 v2026.10.1" "$status $(cat "$T/running")"

release v2026.10.0 v2026.10.1 "smoke fails"
check "a failed smoke test deploys the tag that ran before, and fails" \
    "1 status hhbd;deploy hhbd v2026.10.1;deploy hhbd v2026.10.0;" "$status $(sent)"
check "after it the earlier tag runs" "v2026.10.0" "$(cat "$T/running")"

release none v2026.10.0 "smoke fails"
check "a failed first release has nothing to go back to, and fails" \
    "1 status hhbd;deploy hhbd v2026.10.0;" "$status $(sent)"

release v2026.10.1 v2026.10.1 "smoke fails"
check "a failed redeploy of the running tag does not deploy it a third time" \
    "1 status hhbd;deploy hhbd v2026.10.1;" "$status $(sent)"

release v2026.10.0 v2026.10.1-unhealthy
check "an unhealthy release fails without a smoke test or a second deploy" \
    "1 status hhbd;deploy hhbd v2026.10.1-unhealthy; no smoke" "$status $(sent) $([ -e "$T/smoke-opts" ] && echo smoke || echo no smoke)"

release v2026.10.0 v2026.10.1
check "the smoke test goes through the public name by default" "" "$(cat "$T/smoke-opts")"

echo v2026.10.0 >"$T/running"; rm -f "$T/sent" "$T/smoke-fails"
CI_DEPLOY_SSH="$T/ci-deploy" SMOKE="$T/smoke" OVH_HOST=ovh.example SMOKE_VIA_ORIGIN=true \
    ./deploy/ovh-release.sh v2026.10.1 >"$T/out" 2>&1
check "with SMOKE_VIA_ORIGIN the smoke test goes to the host directly" \
    "--connect-to hhbd.pl:443:ovh.example:443 --insecure" "$(cat "$T/smoke-opts")"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
