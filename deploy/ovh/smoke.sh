#!/usr/bin/env bash
# What a release has to pass on the host before it is kept. scripts/ovh-release.sh runs this
# once the deploy is healthy, and when it fails puts the release before back (gcloud-ovh-migrate's
# CONTRACT.md §6). The whole smoke test, on production's data: the checks only the test
# fixtures can pass stay out.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
SMOKE_TARGET=production exec ./tests/smoke-test.sh https://hhbd.pl
