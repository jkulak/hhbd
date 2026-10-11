#!/usr/bin/env bash
# What a release has to pass on the host before it is kept. scripts/ovh-release.sh runs this
# once the deploy is healthy, and when it fails puts the release before back (gcloud-ovh-migrate's
# CONTRACT.md §6). The release's own checks, in seconds: each kind of page once, and what only
# production holds (#206). The whole suite ran on the pull request, on the fixtures and on the
# production stack behind a stand-in edge; SMOKE_TARGET=production runs it against hhbd.pl.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
SMOKE_TARGET=release exec ./tests/smoke-test.sh https://hhbd.pl
