# HHBD Deployment Scripts

Production is moving from Google Cloud to the shared OVH host. The OVH side follows
`CONTRACT.md` in `jkulak/gcloud-ovh-migrate` at
[`5f9a189`](https://github.com/jkulak/gcloud-ovh-migrate/blob/5f9a1895db6c3d3670482fdfb0486dbf89f7b610/CONTRACT.md);
the Google side below stays until the soak after the cutover is over.

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
ssh ubuntu@$OVH_HOST 'sudo docker exec -it hhbd-db-1 sh -c "MYSQL_PWD=\"\$MYSQL_ROOT_PASSWORD\" mariadb -uroot hhbd"'
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
host to `ci-deploy`), and the repository variable `SMOKE_VIA_ORIGIN` while it is needed (below).

### Moving production

The order is CONTRACT.md §10. A break of up to an hour is accepted.

Before the window, nothing users see changes. It starts once the platform has applied this
repo's hand-back: the deploy key, the `deployable_services` line and the backup targets.

1. `make ovh-install` puts the configuration on the host; `make edge-smoke` in
   gcloud-ovh-migrate lints the snippet.
2. `make ovh-db-up` starts the database alone, and `make ovh-data` copies the database and
   `content/` from Google and verifies both. The home page needs data, so the data comes
   before the first deploy.
3. `gh variable set SMOKE_VIA_ORIGIN --body true`, so the release's smoke test goes to the host
   directly. Then push a release tag; `make ovh-ps` shows the containers healthy and
   `make ovh-smoke` runs the smoke test against the host by hand.

In the window:

4. Stop the writers on Google:
   `gcloud compute ssh hhbd-server --project=hhbd-483111 --zone=us-central1-a --command='sudo docker stop hhbd-nginx-1 hhbd-app-1'`.
   Then `make ovh-data` again, for the final copy. It must end with every table matching on
   `count(*)` and every file on its sha256.
5. In `deploy/hhbd.pl.caddyfile`, replace `tls internal` with
   `tls { ca https://acme-staging-v02.api.letsencrypt.org/directory }` and `make ovh-install`.
   Point `hhbd.pl` at the host with `flarectl`, still proxied; `www` follows as a CNAME. Watch
   the staging certificate issue, then remove the `tls` block and `make ovh-install` again for
   the real one.
6. Switch Cloudflare's SSL mode for the zone to **Full (strict)**. Until then Flexible loops on
   the edge's redirect to HTTPS: that loop is the break.
7. Check through Cloudflare, then add `import cloudflare_only` to the snippet,
   `make ovh-install`, and check that a direct request is dropped. From here a direct request
   cannot reach the host: `gh variable delete SMOKE_VIA_ORIGIN`.
8. Smoke-test the public name: `make smoke URL=https://hhbd.pl`.

After: a week of soak with the Google VM stopped, not deleted. Going back is `flarectl` to
35.209.126.165 and starting the VM.

### Tests

```bash
make test-ovh-stack   # compose.ovh.yaml locally behind a stand-in edge: health, smoke, client address, logs
make test-ovh-data    # the data move between two throwaway databases
./tests/ovh-release-test.sh   # every path of a release against a stand-in ci-deploy
```

All three, and the secrets gate, run on every pull request in `deploy-checks.yml`.

## Google Cloud

This directory also contains scripts and configuration for deploying HHBD to Google Cloud Platform.

## Image Tagging Strategy

The deployment uses a multi-tag strategy to ensure safe and traceable deployments:

### Tag Types

1. **SHA tags** (`sha-<commit>`): Immutable tags for specific commits
   - Created during CI build for every commit to `prod` branch
   - Example: `sha-abc123f`
   - Used for production deployments to ensure tested code is deployed

2. **Latest tag** (`latest`): Mutable convenience tag
   - Updated during every CI build
   - Points to the most recently built commit
   - Can be used for manual deployments (not recommended for production)
   - Defaults for deployment script when `APP_TAG`/`NGINX_TAG` not specified

3. **Production Last-Known-Good** (`prod-lkg`): Stable production tag
   - Only updated after successful smoke tests in production
   - Used by rollback script to restore working deployments
   - Represents the last confirmed working version

### CI/CD Workflow

```
1. Build Stage:
   - Builds Docker images for app and nginx
   - Tags images with both sha-<commit> AND latest
   - Pushes to Artifact Registry

2. Deploy Stage:
   - Deploys SHA-tagged images (exact tested code)
   - Sets APP_TAG=sha-<commit> and NGINX_TAG=sha-<commit>

3. Smoke Test Stage:
   - Runs smoke tests against production
   - If successful: Promotes sha-<commit> → prod-lkg
   - If failed: Rolls back to prod-lkg
```

### Why SHA Tags for Deployment?

Using SHA tags ensures:
- **Traceability**: Know exactly which commit is deployed
- **Safety**: Deploy the exact images that were tested, not newer untested builds
- **Consistency**: No race conditions if multiple commits are pushed quickly

The `latest` tag is still created for convenience and backwards compatibility, but production deployments use SHA tags.

## Scripts

- `01-setup-gcp.sh` - Initial GCP project setup (one-time)
- `02-setup-server.sh` - Configure VM after creation (one-time)
- `03-build-push.sh` - Build and push images locally
- `04-deploy.sh` - Deploy to production VM
- `05-populate-db.sh` - Import database dump
- `06-upload-content.sh` - Upload content files
- `rollback.sh` - Rollback to last-known-good deployment

## Manual Deployment

To manually deploy a specific version:

```bash
# Deploy a specific commit
export APP_TAG=sha-abc123f
export NGINX_TAG=sha-abc123f
./04-deploy.sh

# Deploy latest (not recommended for production)
./04-deploy.sh  # Uses latest by default
```

## Rollback

The rollback script always uses `prod-lkg` tags:

```bash
./rollback.sh           # Rollback all services
./rollback.sh app       # Rollback only app
./rollback.sh nginx     # Rollback only nginx
```

## Configuration

- `compose.gcp.yaml` - Production docker-compose configuration
- `.env.production.example` - Environment variables template
