# HHBD Deployment Scripts

Production runs on the shared OVH host since 2026-10-08, following `CONTRACT.md` in
`jkulak/gcloud-ovh-migrate`. The Google Cloud project it ran on before was deleted the same
day, and with it every script that deployed there.

## OVH host

### What is where

| In this repo | On the host | What |
|---|---|---|
| `deploy/ovh/compose.yaml` | `/srv/hhbd/compose.yaml` | the stack: nginx, app, postgres (PostgreSQL 17, the database since #236), and the importer job; nothing published |
| `deploy/ovh/hhbd.enc.env` | `/srv/hhbd/hhbd.enc.env` | the secrets, SOPS-encrypted to the host and personal age keys |
| `deploy/ovh/hhbd.pl.caddyfile` | `/srv/edge/sites/hhbd.pl.caddyfile` | how the shared edge reaches nginx: through the live upstream below, and whom it turns away first (#295) |
| | `/srv/edge/live/hhbd.caddyfile` | the live colour's nginx (`hhbd-blue-web:80` or `hhbd-green-web:80`), which `ci-deploy` rewrites at each switch |
| `deploy/ovh/systemd/hhbd-release.{service,timer}` | `/etc/systemd/system/` | the day's release from the release queue (#258), once a day between 09:00 and 21:00 Warsaw time |
| `deploy/ovh/service.env` | | what the shared scripts and the deploy workflow read: the service, its images, its timers, and the containers whose log format is still to come |
| | `/srv/hhbd/.env` | `IMAGE_TAG=<running tag>`, written by the host's `ci-deploy` |
| | volumes `hhbd_pg_data`, `hhbd_content` | the database, and `content/` |

Configuration goes in with `make ovh-install`. Images go out with a release, and every merge to
`main` is one (#193): `.github/workflows/release.yml` refuses a merge whose pull request did not
pass its checks, names it `vYYYY.MM.N`, publishes it with a note made of the first sentence of
each entry `CHANGELOG.md` gained, and dispatches `.github/workflows/deploy.yml` against the tag.
Migrations and configuration go in before the merge, `make ship PR=<n>`, which waits for the
release and the deploy it starts (the order is in `CLAUDE.md`, "Finished means running on
hhbd.pl"). A release by hand, `gh release create vYYYY.MM.N`, is only for what
the workflow refuses.

The deploy workflow builds the app, nginx and importer
images under that one tag, pushes them privately to `ghcr.io/jkulak/hhbd-{app,nginx,importer}`,
and runs `scripts/ovh-release.sh`: read the running tag with `status hhbd`, `deploy hhbd <tag>`,
run `deploy/ovh/smoke.sh`, and when that fails deploy the tag read at the start. A release that
never becomes healthy is taken back by the host itself. Every container has a healthcheck;
nginx's renders the home page, so a release that starts but cannot serve counts as unhealthy.
A tag pushed on its own deploys nothing. An earlier release goes back by dispatching the
workflow against its tag: `gh workflow run deploy.yml --ref vYYYY.MM.N`.

The install and release scripts, `scripts/ovh-stack-test.sh`, `deploy/ovh/ovh.mk`, the secrets
scripts and `deploy.yml` are the shared host's service template, word for word the same in
every service on it; `make service-drift` in gcloud-ovh-migrate says when a copy here has
moved, so a change to them is made there. The rest of `deploy/ovh/` is this service's own.

Logs go to the containers' stdout and stderr, where the host's collector picks them up under
`service_name="hhbd"`; `make ovh-logs` reads them back through Grafana
(`SINCE=1h LEVEL=error COMPONENT=app GREP=text`).

What each container writes, in CONTRACT.md §9's format (#101):
- **app**: one JSON object per line, written by `Jkl_Log`:
  - `time` in UTC, `level`, `msg`;
  - `logger` (`app`, `php`, `importer`, `tool`);
  - the edge's `request_id`;
  - for an error, `error` and `stack`.
  
  Zend_Log writes through `Jkl_Log_Formatter_Json`. PHP's errors, uncaught exceptions and fatal
  errors come through handlers that `auto_prepend_file` installs in every script; `log_errors`
  is off. A page that does not exist is an `info` line, and a failed request an `error` line.
- **nginx**: no access log, since the edge logs every request. Errors at `warn` and above stay
  on stderr in nginx's own format, and a start says nothing.
- **PHP-FPM**: logs at `warning`, so a start says nothing either.
- **The importer**: its progress, the run's summary and every refusal are lines in the same
  format on stderr. Its report on stdout is unchanged.

nginx takes the client's address from `X-Real-IP`, trusted from the edge's fixed address
172.30.0.2 only. It passes `X-Forwarded-Proto: https`, which Cloudflare sends and the edge
keeps, on to PHP as `HTTPS=on`, so the application writes `https://` into its canonical tags
and sitemaps (#147).

There is no adminer and no backoffice. The database is reached over ssh, as the image's
superuser over the container's socket, where no password is asked:

```bash
ssh -t ubuntu@$OVH_HOST 'sudo docker exec -it hhbd-postgres-1 psql -U hhbd hhbd'
```

### Secrets

`deploy/ovh/hhbd.enc.env` holds `DB_PASSWORD` and `LEGACY_PASSWORD_SALT`, every value
`ENC[...]`. No registry login: the deploy and `make ovh-import` pull the private images with the
host's own, from `/srv/host/registry.enc.env` (#278; gcloud-ovh-migrate's `host/README.md` says
how its token is replaced).
`HEALTHCHECKS_ENABLED=true` and `HEALTHCHECKS_PROJECT_PING_KEY`, the host's Healthchecks project
key, let the release timer's job ping its check (CONTRACT.md §7); without them it runs all the
same, unwatched.
`make ovh-secrets-check` (and the `Deploy checks` workflow) refuses a plaintext value or a file
the two keys in `.sops.yaml` do not both open.

```bash
make ovh-secrets-init                         # once: database passwords generated straight into the file
make ovh-secrets-set KEY=LEGACY_PASSWORD_SALT # one value, from a hidden prompt
make ovh-secrets-show                         # key names, never values
```

The deploy workflow needs the `production` environment's secrets `OVH_HOST`, `OVH_HOST_KEY`
(the host's `known_hosts` line) and `OVH_DEPLOY_SSH_KEY` (a key made for this pipeline alone,
pinned on the host to `ci-deploy`); the environment admits `v*` tags alone.

### PostgreSQL's image

`postgres` runs one exact release, named by its tag and its digest,
`postgres:17.11-alpine@sha256:…` (#275). Under the moving tag `17-alpine`, whichever release
found a newer image behind the tag recreated the database, as v2026.10.52's did: six seconds
without it, and a 500 in between. Now a deploy pulls nothing new for it and its log says
`Container hhbd-postgres-1 Running`, unless the pull request changed that line. `compose.yaml`
names the same image, and the stack test fails when the two differ or the digest is missing.

A new minor release, or a rebuild of the image with Alpine's fixes in it:

1. `make postgres-pin TAG=17.12-alpine` writes the tag and Docker Hub's digest for it into both
   compose files. It refuses another major: PostgreSQL 18 needs the data moved, not a new pin.
2. The pull request goes the usual way, its tests running on the new image (`make reset-db`
   makes the local database again on it).
3. Its release recreates `hhbd-postgres-1`, and requests fail for those few seconds, so it is
   merged at a quiet hour. The deploy log says `Recreated` for it.
4. `make ovh-postgres` shows the image production runs, its digest and the server's version.

### Database migrations

Schema changes travel as migrations, `database/migrations/NNNN-slug.up.sql` with its
`.down.sql`, applied in order and recorded in the database by `scripts/migrate.sh`
([database/README.md](../../database/README.md)). Production's database gets them from the
Mac, as the admin account, over ssh into `hhbd-postgres-1`; the deploy key can run nothing but
`ci-deploy`, so a release does not migrate on its own.

```bash
make ovh-migrate-status   # what production has applied, what is pending
make ovh-migrate          # apply what is pending
make ovh-migrate-down     # revert the last one, after typing its version to confirm
```

A change that needs a schema change: `make ovh-migrate` first, then the merge that releases it. The
release that is running must work with the migrated schema, and the new one with the old:
`ci-deploy` takes a release that does not become healthy back to the one before, which then
runs against whatever the migration did (CONTRACT.md §6). Add a column in one release, use it
in the next, drop the old one in a third.

### Imports

Batches from the content project go into production's catalogue with the importer job (#56,
[docs/import.md](../../docs/import.md)): `importer` in `compose.yaml`, under the `jobs`
profile so a deploy never starts it, with the content volume read-write. It is the one container
with GD, which writes the cover, photo and logo sizes from the original a batch ships (#96).

```bash
make ovh-import BATCH=/path/to/batch             # a dry run: what would change, nothing written
make ovh-import BATCH=/path/to/batch MODE=apply  # the rows, and the images into the content volume
make ovh-import-runs                             # the runs, newest first
```

`deploy/ovh/import.sh` pulls the importer image of the running tag, logged in to GHCR for the
pull alone (ci-deploy pulls only what `up` starts), and streams the batch to it as a tar over
ssh, so no copy is left on the host. Progress comes back on stderr, the report on stdout; the
run's row in `import_runs` keeps the report too. A batch applied twice changes nothing the
second time. A release from before the importer has no importer image to pull.

What an apply would publish waits in the release queue (#258), and the `hhbd-release` timer
publishes the next five to ten albums every day, at a time of its own between 09:00 and 21:00
Warsaw time; docs/import.md has the rules. `RELEASE=now` on `make ovh-import` publishes at once.

```bash
make ovh-release-queue          # how many wait, by year, the next ten, and about when the last comes out
make ovh-release DRY_RUN=1      # what the day's release would publish
make ovh-release N=3            # three now, by hand, as the timer runs it
```

The timer runs `app/tools/release.php run` in a one-off `app` container of the running tag, the
`jobs` profile's, under `hc-wrap` with the check `hhbd-release`; its lines reach Grafana Cloud
as `component=release`. `make ovh-install` installs and enables it, as `TIMERS` in
`deploy/ovh/service.env` names it; leaving it out of `TIMERS` and installing again stops it.
gcloud-ovh-migrate's `make healthchecks-push` sets the check to follow the timer.

### IndexNow

Bing, whose index ChatGPT search and Copilot answer from, and IndexNow's other engines hear of
new pages as they appear (#220): an applied import tells IndexNow the labels it added or
changed, the artists with something to show and every published album with its songs, an
admin publishing an album tells it that album's, and the day's release the albums it brings
out, with their songs, artists and labels (#258). The key is `INDEXNOW_KEY` in `compose.yaml`, for the app and the
importer: not a secret, since the site serves it at `https://hhbd.pl/<key>.txt` for IndexNow to
check, and set nowhere else, so nothing local, in CI or in a stack test sends anything. A
failed request is a `logger=indexnow` line, never a failed import.

```bash
make ovh-indexnow DRY_RUN=1   # count every address in the sitemaps, send nothing
make ovh-indexnow             # send them all: the first time, and after a gap
```

### How production moved to PostgreSQL

From MariaDB to its own PostgreSQL 17 (#229), as every service on the host runs one engine.
Rehearsed on 2026-10-13 on a dump of production (#235), in a throwaway stack:

- The dump loaded into MariaDB (2 s), PostgreSQL's schema made by its migration 0039 (1 s),
  and the copy tool of #233: 56 tables, every one the same on both sides, in 2.8 s. It found the
  one value production held that the schema refused, two old comments with MariaDB's empty
  enum value, which 0039 admits.
- The whole smoke test on production's data passed alike on both engines (177, and the 3
  checks only production's environment passes).
- Pages: the sum of 21 pages' medians was 788-882 ms on PostgreSQL against 620-852 ms on
  MariaDB over several runs on a laptop, once PostgreSQL's statements went in one round trip
  each (`Jkl_Db::connectionOptions()`; 996 ms before). Search and Top 10 are faster on
  PostgreSQL; a page is some 5 ms slower for opening its connection, and an album's page some
  20 ms, as it runs hundreds of small queries (#255). Persistent connections brought the sum
  to 708 ms, but a worker then keeps a connection PostgreSQL has dropped, and its request after
  a database restart fails: not worth 5 ms behind Cloudflare.

The move, on 2026-10-13:

1. `postgres` beside `db` in the compose file, with the backup's labels and the whole database
   in memory (128 MB of shared buffers, `pg_prewarm`), the app still on MariaDB: installed,
   started, and released as v2026.10.40 (#253).
2. The schema made by `make ovh-migrate` in the empty database.
3. The compose file with the app and the importer on PostgreSQL installed, the running
   containers still on MariaDB.
4. The copy at 06:52 UTC: 56 tables in 7 s, each proved the same on both sides, then analysed.
5. The release of that compose file, v2026.10.41 (#254), at 06:53: the idle colour started on
   PostgreSQL and the edge moved to it. The writes MariaDB took in between, a minute of page
   views on a quiet site, were not carried over.
6. The whole smoke test on https://hhbd.pl passed (180), no error in the logs, and every
   identity past its table's highest id.

MariaDB went the same day, sooner than the week planned as a way back (#237): its service,
`php8.4-mysql`, the copy tool and MariaDB's own migrations left the repository, and its
container and volume the host.

### How production moved

On 2026-10-08, following CONTRACT.md §10, with no failed request:

1. `make ovh-install`, `make ovh-db-up`, `make ovh-data`, then the first release, `v2026.10.0`,
   smoke-tested straight at the host with `SMOKE_VIA_ORIGIN=true`.
2. A preview at `new.hhbd.pl`, not proxied, with its own certificate. nginx knows only
   `hhbd.pl` and `www.hhbd.pl`, so the preview block sent `Host: hhbd.pl` upstream. 40 pages
   matched Google on status, title and text.
3. A temporary `http://hhbd.pl, http://www.hhbd.pl` block, so Cloudflare, still in Flexible,
   reached the site over HTTP; then the DNS record moved, still proxied, at 21:49.
4. `tls internal` went, the edge obtained the certificates through Cloudflare, and the zone went
   to Full (strict).
5. The final snippet: no `http://` block, `import cloudflare_only`. The preview went, the
   smoke test passed on `https://hhbd.pl`, `SMOKE_VIA_ORIGIN` was unset, and
   `make gcp-stop-writers` stopped the app and nginx on Google.

The Google side was stopped once the switch was done, and its project deleted the same evening
after a fresh backup on the new host; `make ovh-data` and the `gcp-*` targets went with it.

### Tests

```bash
make ovh-stack-test     # the host's own checks: the files against its contract, the stack behind the edge's real configuration, each name served
make ovh-e2e            # this repo's own: compose.yaml behind a stand-in edge at the edge's address: health, smoke, client address, logs, an import
make ovh-secrets-check  # no plaintext secret or private key in the tree
```

`ovh-e2e` and the secrets check run on every pull request in `deploy-checks.yml`.
`ovh-stack-test` reads the edge's configuration from a gcloud-ovh-migrate checkout, so it runs
on the Mac, before the pull request. The release flow is the template's, tested there.
