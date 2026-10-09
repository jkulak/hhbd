# HHBD Deployment Scripts

Production runs on the shared OVH host since 2026-10-08, following `CONTRACT.md` in
`jkulak/gcloud-ovh-migrate`. The Google Cloud project it ran on before was deleted the same
day, and with it every script that deployed there.

## OVH host

### What is where

| In this repo | On the host | What |
|---|---|---|
| `deploy/compose.ovh.yaml` | `/srv/hhbd/compose.yaml` | the stack: nginx, app, db, and the importer job; nothing published |
| `deploy/hhbd.enc.env` | `/srv/hhbd/hhbd.enc.env` | the secrets, SOPS-encrypted to the host and personal age keys |
| `deploy/hhbd.pl.caddyfile` | `/srv/edge/sites/hhbd.pl.caddyfile` | how the shared edge reaches nginx (`hhbd-web:80`) |
| | `/srv/hhbd/.env` | `IMAGE_TAG=<running tag>`, written by the host's `ci-deploy` |
| | volumes `hhbd_db_data`, `hhbd_content` | the database and `content/` |

Configuration goes in with `make ovh-install`. Images go out with a release: a CalVer tag
`vYYYY.MM.N` pushed by a person starts `.github/workflows/release.yml`, which builds the app,
nginx and importer images under that one tag, pushes them privately to
`ghcr.io/jkulak/hhbd-{app,nginx,importer}`, and runs
`deploy/ovh-release.sh`: read the running tag with `status hhbd`, `deploy hhbd <tag>`, smoke
test, and on a failed smoke test deploy the tag read at the start. A release that never becomes
healthy is taken back by the host itself. Every container has a healthcheck; nginx's renders
the home page, so a release that starts but cannot serve counts as unhealthy.

Logs go to the containers' stdout and stderr, where the host's collector picks them up under
`service_name="hhbd"`. nginx takes the client's address from `X-Real-IP`, trusted from the
edge's fixed address 172.30.0.2 only.

There is no adminer and no backoffice. The database is reached over ssh:

```bash
ssh ubuntu@$OVH_HOST 'sudo docker exec -it hhbd-db-1 sh -c "MYSQL_PWD=\"\${MYSQL_ROOT_PASSWORD:?}\" mariadb -uroot hhbd"'
```

### Secrets

`deploy/hhbd.enc.env` holds `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `GHCR_USER` and
`GHCR_READ_TOKEN`, every value `ENC[...]`. `make secrets-check` (and the `Deploy checks`
workflow) refuses a plaintext value or a file the two keys in `.sops.yaml` do not both open.

```bash
make secrets-init                    # once: database passwords generated straight into the file
make secrets-set KEY=GHCR_READ_TOKEN # a classic PAT with read:packages only, from a hidden prompt
make secrets-show                    # key names, never values
```

The release workflow needs the repository secrets `OVH_HOST`, `OVH_HOST_KEY` (the host's
`known_hosts` line) and `OVH_DEPLOY_SSH_KEY` (a key made for this pipeline alone, pinned on the
host to `ci-deploy`). The repository variable `SMOKE_VIA_ORIGIN=true` sends a release's smoke
test straight to the host; it is needed only before `hhbd.pl` points there, and is unset now.

### Database migrations

Schema changes travel as migrations, `database/migrations/NNNN-slug.up.sql` with its
`.down.sql`, applied in order and recorded in the database by `scripts/migrate.sh`
([database/README.md](../database/README.md)). Production's database gets them from the Mac, as
the admin account, over ssh into `hhbd-db-1`; the deploy key can run nothing but `ci-deploy`,
so a release does not migrate on its own.

```bash
make ovh-migrate-status   # what production has applied, what is pending
make ovh-migrate          # apply what is pending
make ovh-migrate-down     # revert the last one, after typing its version to confirm
```

A release that needs a schema change: `make ovh-migrate` first, then push the tag. The release
that is running must work with the migrated schema, and the new one with the old: `ci-deploy`
takes a release that does not become healthy back to the one before, which then runs against
whatever the migration did (CONTRACT.md §6). Add a column in one release, use it in the next,
drop the old one in a third.

Once, before the first `make ovh-migrate`: production already has the baseline schema, so
`make ovh-migrate-baseline` records `0001-baseline` as applied without running it, after
checking that every column the baseline creates is there. Until then `make ovh-migrate` refuses
a database that has tables but no record.

### Imports

Batches from the content project go into production's catalogue with the importer job (#56,
[docs/import.md](../docs/import.md)): `importer` in `compose.ovh.yaml`, under the `jobs`
profile so a deploy never starts it, with the content volume read-write. It is the one container
with GD, which writes the cover, photo and logo sizes from the original a batch ships (#96).

```bash
make ovh-import BATCH=/path/to/batch             # a dry run: what would change, nothing written
make ovh-import BATCH=/path/to/batch MODE=apply  # the rows, and the images into the content volume
make ovh-import-runs                             # the runs, newest first
```

`deploy/ovh-import.sh` pulls the importer image of the running tag, logged in to GHCR for the
pull alone (ci-deploy pulls only what `up` starts), and streams the batch to it as a tar over
ssh, so no copy is left on the host. Progress comes back on stderr, the report on stdout; the
run's row in `import_runs` keeps the report too. A batch applied twice changes nothing the
second time. A release from before the importer has no importer image to pull.

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
make test-ovh-stack     # compose.ovh.yaml locally behind a stand-in edge: health, smoke, client address, logs, an import
make test-ovh-release   # every path of a release against a stand-in ci-deploy
make secrets-check      # no plaintext secret or private key in the tree
```

All three run on every pull request in `deploy-checks.yml`.
