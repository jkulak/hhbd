# Changelog

Releases use CalVer, `YYYY.MM.N`: the year, the month, and a counter that starts at 0 each
month (`2026.10.0`, `2026.10.1`, `2026.11.0`). A release is the git tag `vYYYY.MM.N`, and
pushing it is what deploys it (`.github/workflows/release.yml`). Tagging is a person's
decision; nothing tags on its own.

## Unreleased

### Added
- Production can run on the shared OVH host, following `CONTRACT.md` in
  `jkulak/gcloud-ovh-migrate` at `5f9a189`: `deploy/compose.ovh.yaml` with nothing published
  and no adminer, the edge snippet `deploy/hhbd.pl.caddyfile`, and `make ovh-install` to put
  both on the host with the secrets.
- Releases from CalVer tags: both images built under one tag, pushed privately to
  `ghcr.io/jkulak/hhbd-{app,nginx}`, deployed through the host's `ci-deploy`, smoke-tested, and
  rolled back to the tag that ran before when the smoke test fails.
- A healthcheck on every container. nginx's renders the home page, so a release that starts
  but cannot serve a page is taken back by the host.
- `content/` lives in a named volume on the OVH host, so the host's backup covers it.
- `deploy/ovh-data.sh` moves the database and `content/` from the Google VM and verifies them:
  exact `count(*)` and a checksum per table, and a sha256 per file.
- Secrets in `deploy/hhbd.enc.env`, SOPS-encrypted to the host and personal keys in
  `.sops.yaml`, with `scripts/secrets-check.sh` as the gate in `make secrets-check` and on
  every pull request.
- The smoke test takes extra curl options from `SMOKE_CURL_OPTS`, so it can reach a host before
  the DNS points at it.
- Tests for the production stack behind a stand-in edge, the data move and the release flow,
  run on every pull request by `deploy-checks.yml`.

### Changed
- PHP, the application and nginx log to the containers' stdout and stderr; nothing writes log
  files inside a container any more. Zend_Log has one writer instead of two files, and PHP-FPM
  no longer logs every request a second time.
- nginx takes the client's address from `X-Real-IP`, trusted only from the edge's fixed address
  172.30.0.2, so comments behind the OVH edge are stored with the poster's address.
- `deploy/05-populate-db.sh` uses the database container's own credentials instead of a
  password written in the script.

- The unit and smoke test workflows run on pull requests only, not again on the push to
  `main` that a merge makes, and a newer push to a pull request cancels the older run.
- `make test-ovh-release` runs the release flow's test.
- `make gcp-ps`, `make gcp-stop-writers` and `make gcp-start` are the only ways this repo
  touches the Google VM besides the data move: look, stop the writers for the final copy, and
  start them again as the way back.

### Fixed
- The PHP image builds again. Its base, Debian 11, is past long-term support, and the regular
  mirrors had stopped serving the libfcgi security fix that their index still listed; the image
  now installs from archive.debian.org.
