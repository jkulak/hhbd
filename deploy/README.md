# HHBD Deployment Scripts

Production runs on the shared OVH host since 2026-10-08, following `CONTRACT.md` in
`jkulak/gcloud-ovh-migrate`. The Google Cloud project it ran on before was deleted the same
day, and with it every script that deployed there.

## OVH host

### What is where

| In this repo | On the host | What |
|---|---|---|
| `deploy/compose.ovh.yaml` | `/srv/hhbd/compose.yaml` | the stack: nginx, app, db; nothing published |
| `deploy/hhbd.enc.env` | `/srv/hhbd/hhbd.enc.env` | the secrets, SOPS-encrypted to the host and personal age keys |
| `deploy/hhbd.pl.caddyfile` | `/srv/edge/sites/hhbd.pl.caddyfile` | how the shared edge reaches nginx (`hhbd-web:80`) |
| | `/srv/hhbd/.env` | `IMAGE_TAG=<running tag>`, written by the host's `ci-deploy` |
| | volumes `hhbd_db_data`, `hhbd_content` | the database and `content/` |

Configuration goes in with `make ovh-install`. Images go out with a release: a CalVer tag
`vYYYY.MM.N` pushed by a person starts `.github/workflows/release.yml`, which builds both images
under that one tag, pushes them privately to `ghcr.io/jkulak/hhbd-{app,nginx}`, and runs
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
make test-ovh-stack     # compose.ovh.yaml locally behind a stand-in edge: health, smoke, client address, logs
make test-ovh-release   # every path of a release against a stand-in ci-deploy
make secrets-check      # no plaintext secret or private key in the tree
```

All three run on every pull request in `deploy-checks.yml`.
