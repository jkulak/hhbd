# Running on the OVH host

What this service owes the shared OVH host, and what the host gives it in return. This file is
the same, word for word, in every service on the host; what makes this one different lives in
`service.env` and the files beside it.

## 1. What is where

| In the repository | On the host | Whose |
|---|---|---|
| `deploy/ovh/compose.yaml` | `/srv/<service>/compose.yaml` | this service's: the stack |
| `deploy/ovh/<service>.enc.env` | `/srv/<service>/<service>.enc.env` | this service's: every secret the stack needs, SOPS-encrypted |
| `deploy/ovh/<hostname>.caddyfile` | `/srv/edge/sites/<hostname>.caddyfile` | this service's: how the edge reaches it |
| `deploy/ovh/systemd/<service>-<job>.{service,timer}` | `/etc/systemd/system/` | this service's: scheduled jobs, if any |
| `deploy/ovh/service.env` | | this service's: what the shared scripts and workflows read about it (§12) |
| `deploy/ovh/smoke.sh` | | this service's: what a release must pass once it is deployed |
| `deploy/ovh/<service>.env.example` | | this service's: the names its secrets go by, no values |
| | `/srv/<service>/.env` | the deploy's: the tag running now, and each colour's |
| | `/srv/edge/live/<service>.caddyfile` | the deploy's: the live colour's upstream (§3) |

Shared, the same in every service, and not to be changed here, since the next update of the
template puts them back: this file, `deploy/ovh/ovh.mk`, `scripts/ovh-install.sh`,
`scripts/ovh-stack-test.sh`, `scripts/ovh-release.sh`, `scripts/secrets.sh`,
`scripts/secrets-check.sh`, `scripts/release-notes.py`, `scripts/ship.sh`, and
`.github/workflows/release.yml` and `deploy.yml`. Whatever has to differ goes in `service.env`.

`<service>` is a short lowercase name, used for the directory, the compose project, the secrets
file and every alias below. It is what the service is called in the logs and on the dashboards.

## 2. How a change reaches production

- **Configuration goes in by hand**, with `make ovh-install` from the Mac: the compose file, the
  secrets, the snippets and the timers, copied as the admin account. The edge is reloaded, and
  when it refuses a new snippet the one before goes back, so a broken snippet never stays on
  disk. The host's address comes from the `.env` of the platform checkout that `ovh.mk` names as
  `OVH_PLATFORM`.
- **Images go out with a release, and every merge to `main` is one.** `make ship PR=<n>` merges:
  it rebases the branch onto `main`, signs its commits again where they need it, waits for the
  pull request's checks and pushes the branch to `main` as a fast-forward, so `main` is one
  straight line of signed commits. GitHub's merge button and `gh pr merge` are not used.
- **The release workflow refuses a merge whose pull request did not pass its checks, or ran
  none**, so the repository needs at least one workflow that runs on its pull requests. It names
  the release `vYYYY.MM.PATCH`, writes its note from what `CHANGELOG.md` gained, publishes it and
  dispatches the deploy workflow against the tag.
- **The deploy workflow** builds the images `service.env` names, pushes them, and runs
  `scripts/ovh-release.sh`: it reads the tag running now, deploys the new one, runs `smoke.sh`,
  and when that fails deploys the one it read. It runs in the repository's `production`
  environment, which admits `v*` tags alone and holds the host's address, its host key and a
  deploy key made for this pipeline only. The key can do nothing on the host but deploy, and the
  service never handles it.
- **A merge that changes `compose.yaml`, `service.env`, a secret, a snippet or a unit** needs
  `make ovh-install` from its branch first, since the deploy would otherwise come up against the
  configuration before. `make ship` refuses such a pull request unless `INSTALLED=1` says it was.
- **Putting an earlier release back** is a dispatch of the deploy workflow against its tag:
  `gh workflow run deploy.yml --ref vYYYY.MM.N`.
- **`CHANGELOG.md`** is sections by date, newest first, each change a bullet that leads with a
  sentence that stands alone, which is what the release note keeps. What nobody using the
  service can see goes under `### Internal`. No version numbers in it: the merge names the
  release.

## 3. compose.yaml

- **`name:` is the service's name.** Every log line is labelled with the compose project.
- **Every image of the service's own is `ghcr.io/<owner>/<image>:${IMAGE_TAG:?}`,** one tag for
  all of them, set by the deploy; a coloured one is on its colour's tag (below).
- **Only the container the edge proxies to joins the external `edge` network,** with an alias
  unique across services, `<service>-blue-web` and the like. Compose also gives it its bare
  service name there, and two services' `web` would answer for each other at random.
- **No public ports.** Anything published binds `127.0.0.1`; an admin UI is reached with
  `ssh -L`. Admin tools such as adminer are not part of the stack.
- **No `logging:` override, and no `env_file:` pointing at decrypted secrets.** The host rotates
  logs, and secrets arrive through `sops exec-env` alone.
- **Secrets by name, without defaults:** `${DB_PASSWORD:?}`, never `${DB_PASSWORD:-something}`,
  so a stack missing a secret fails to start instead of running on a value from the repository.
- **State in named volumes,** where the backup finds it (§9). A bind mount is not backed up.
- **A memory limit on every container,** so one that leaks cannot take the host with it.
- **Every container is `restart: unless-stopped`.** The host reboots itself at 03:50 Warsaw time
  when an installed update waits for it, and everything has to come back without a person.

### The request path in two colours

The containers a request passes through are declared twice, `<name>-blue` and `<name>-green`,
and a deploy starts the idle colour on the new tag beside the live one, waits for it to be
healthy, and only then moves the edge to it with one graceful reload. No request waits on a
release, and one that never becomes healthy never takes a request.

- **What both colours share is one YAML anchor** (`x-web: &web`). A merge takes a key whole, so a
  colour that sets its own `labels` or `environment` replaces the anchor's.
- **Each colour is on its own tag:** `${IMAGE_TAG_BLUE:-${IMAGE_TAG:?}}`, never `${IMAGE_TAG}`
  alone, so the colour that keeps running does not change when the other is deployed. Each is
  labelled `deploy.colour: blue` or `green`.
- **One container per colour carries `deploy.upstream: <alias>:<port>`,** its alias on `edge`:
  where the edge sends the service while that colour is live.
- **A colour's tiers find each other on the colour's own network** under the names they had, so
  an image does not change: on `blue`, only blue's app answers to `app`.
- **What must not run twice stays single:** a database, a worker taking jobs from a queue, a sync
  client. It follows the new tag after the switch and restarts in place.
- **Coloured containers publish no port and share their volumes.**
- **The colour before keeps running until the smoke test passes**, so going back is a switch of
  the edge, not a start.

## 4. Health

- **Anything that serves HTTP has a health endpoint**, `/healthz` unless the framework names its
  own, which answers whether the process can do its work: 200 when it can, an error when it
  cannot, quickly and without side effects. `HEALTH_PATH` in `service.env` names it.
- **Every long-running container has a `healthcheck`** that asks what the service needs to serve,
  through a tool its image has. Without one, healthy only means started, and a version that
  starts and cannot serve would be kept. A healthcheck in the image counts as one in compose.
- **A deploy waits for the stack to be healthy**, three minutes unless `<service>.enc.env` sets
  `CI_DEPLOY_WAIT_SECONDS`. A release that does not become healthy is put back to the last good
  tag by the host itself, and the pipeline goes red. Keep the healthcheck quick and the wait
  about twice the real start-up time.
- **Containers that run and exit carry a profile**, `profiles: [jobs]`, so `up` never starts
  them: the wait counts an exited container as a failed deploy. `docker compose run` still finds
  them.
- **Start-up migrations stay compatible with the version before.** A rollback runs the previous
  version against whatever the new one did, and two colours run side by side for a while: add a
  column, use it in the next release, drop the old one in a third, each its own pull request.
- **`smoke.sh` starts by asking the public name for its health** and grows into what the service
  cannot do without. The public name is also probed from outside every few minutes, on its
  health path, and an alert goes out when it stops answering.

## 5. The edge

One snippet per public name, `deploy/ovh/<hostname>.caddyfile`:

```caddyfile
example.itsnotezra.com {
	import access_log
	import security_headers
	import compress
	import ../live/<service>.caddyfile
}
```

| Import | What it does | When |
|---|---|---|
| `access_log` | the request log, as JSON, each request under the id the service gets as `X-Request-Id` | always |
| `security_headers` | HSTS, `nosniff`, a referrer policy, `X-Frame-Options: SAMEORIGIN`, no `Server` header; the app's own Content-Security-Policy passes | always |
| `compress` | zstd or gzip, as the client accepts | anything that serves text |
| `../live/<service>.caddyfile` | the live colour's upstream, which the deploy rewrites; `make ovh-install` creates it the first time | a service in two colours |
| `proxy <alias>:<port>` | a reverse proxy that keeps retrying for 30 seconds while the upstream restarts | a service in one colour |
| `cloudflare_only` | drops any connection that does not come from Cloudflare | a name Cloudflare proxies |

- **Every site block imports `access_log` and `security_headers`, and every address names a
  host:** never `:443`, `http://` or `*` on its own.
- **The client's address arrives in `X-Real-IP`**, set by the edge, which sits at `172.30.0.2` on
  the `edge` network. Trust it from there and nowhere else; in nginx,
  `set_real_ip_from 172.30.0.2;` and `real_ip_header X-Real-IP;`.
- **Certificates come from Let's Encrypt, issued by the edge** the moment the name's DNS points at
  the host. A name that still points elsewhere stays on `tls internal` until its record moves:
  without it the edge would ask for a certificate it cannot get, and failed attempts lock a name
  out for an hour. Once the record points here, the line goes and `make ovh-install` brings the
  real certificate within seconds.
- **A name Cloudflare proxies** imports `cloudflare_only` and is listed in `CLOUDFLARE_PROXIED`.
- **Per-host request rate, errors and latency** are collected at the edge with nothing to add.

## 6. Secrets

- **`deploy/ovh/<service>.enc.env`**, encrypted to the two age keys in `.sops.yaml`: the host's
  and the owner's. Every value is `ENC[...]`; `make ovh-secrets-check` fails on one that is not,
  and on anything secret about to be committed.
- **One at a time, from a hidden prompt:** `make ovh-secrets-set KEY=NAME`. `make
  ovh-secrets-show` lists the names, never the values. A secret never goes on a command line,
  into a file in clear, or into a log.
- **No registry login:** the images are private, and the host pulls them with a token of its own.
  A `GHCR_READ_TOKEN` here still wins over the host's, so drop it, and `GHCR_USER` with it, once
  the host has one. A service with no secrets at all keeps the file empty: `make ovh-secrets-edit`,
  delete everything, save.
- **With scheduled jobs:** `HEALTHCHECKS_ENABLED=true` and `HEALTHCHECKS_PROJECT_PING_KEY`.
- **A secret the app reads as a file** is one value in the same file, newlines and all, which
  compose hands the container as a file:

```yaml
services:
  web:
    secrets: [apns_key]          # the app reads /run/secrets/apns_key
secrets:
  apns_key:
    environment: APNS_KEY        # a key in <service>.enc.env
```

## 7. Images

Built by the deploy workflow and pushed privately to `ghcr.io/<owner>/<name>`, with the run's own
token. `IMAGES` in `service.env` names each as `name=context:dockerfile[:target]`, the target a
stage of a multi-stage Dockerfile. The tag is the release, `vYYYY.MM.PATCH`. Beside the release
tags each image keeps `buildcache`, the layers the next build starts from.

## 8. Scheduled jobs

One systemd service and timer per job, `deploy/ovh/systemd/<service>-<job>.{service,timer}`,
named in `TIMERS`, on Warsaw time stated explicitly, since the host's clock is UTC:

```ini
# <service>-sync.service
[Unit]
Description=<service>: sync
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
WorkingDirectory=/srv/<service>
Environment=SOPS_AGE_KEY_FILE=/etc/sops/age.key
Environment=HEALTHCHECKS_SLUG=<service>-sync
LogExtraFields=SERVICE=<service> JOB=sync
TimeoutStartSec=10min
ExecStart=/usr/local/bin/sops exec-env /srv/<service>/<service>.enc.env '/usr/local/bin/hc-wrap docker compose run --rm --no-deps job sync'

# <service>-sync.timer
[Timer]
OnCalendar=*-*-* 05:00:00 Europe/Warsaw
Persistent=true
RandomizedDelaySec=60

[Install]
WantedBy=timers.target
```

- **`hc-wrap`** pings the job's Healthchecks check at start and end, creating it on its first
  ping, and keeps the job's exit code. The check is then set to follow the timer.
- **`LogExtraFields`** files the job's lines under the service, with the job as its component.
- **`TimeoutStartSec`** sits well above the job's usual run; without it a job that hangs holds
  its unit for ever and its timer never starts it again.
- A job that must not be cut off by the 03:50 reboot says so before it is scheduled.

## 9. Data and backups

Every labelled database and volume is backed up nightly at 02:30 Warsaw time, encrypted, and
every labelled database is restored once a week into a throwaway copy to prove the dump opens.
The service declares what is taken, in its own `compose.yaml`:

```yaml
services:
  db:
    image: postgres:17-alpine
    labels:
      backup.postgres: app          # the database to dump
      backup.user: app              # the role, when the superuser is not postgres
  web-blue:
    labels:
      backup.volumes: uploads       # compose's volume keys, mounted by this container
```

A volume key the container does not mount fails the backup rather than backing up nothing.
Leave out what rebuilds itself: caches, models. The worst case is the last nightly dump.

## 10. Logs

To stdout and stderr, never to a file inside a container. Every line the service's own code
writes is one JSON object:

- **Required:** `time` (RFC 3339 in UTC, to the millisecond: `2026-10-09T07:54:10.123Z`), `level`
  (`debug`, `info`, `warn` or `error`) and `msg`, a sentence a person reads.
- **Optional, under these names when present:** `logger`, `request_id`, `duration_ms`, `error`
  and `stack`, the frames inside the one field. Everything else flat, in `snake_case`, with its
  unit in its name: `_ms`, `_bytes`, `_count`.
- **Under 16 KB a line**, where Docker splits it: a long stack is cut, not spread over lines.
- **Nothing secret or personal, at any level:** no token, password, credential, key or personal
  data.
- **No access log of the service's own:** the edge logs every request. A line written while
  handling one carries its `X-Request-Id` as `request_id`.

What a third-party image writes, Postgres or nginx's error log, stays as it is. `make
ovh-stack-test` fails a stack whose own image writes a line any other way, unless
`LOG_FORMAT_PENDING` names it. `make ovh-logs` reads production's lines, by level, component,
text or request id, with a read-only token kept on the Mac.

## 11. Outbound mail

No mail server on the host: everything is sent through the estate's SMTP relay, Brevo's, on
port 587 with STARTTLS. A service that sends requires its settings, `SMTP_URL: ${SMTP_URL:?}` and
`MAIL_FROM: ${MAIL_FROM:?}`, both in `<service>.enc.env`, and names the domain it sends from in
`MAIL_DOMAIN`. Nothing is sent from a domain before it is authenticated with the relay (DKIM and
DMARC in its DNS); that is done by hand, once per domain.

## 12. service.env

Not secret, committed, and read by the shared scripts, the workflows and the host's tooling:

| Key | What |
|---|---|
| `SERVICE` | the service's name |
| `IMAGES` | the images built on each release, `name=context:dockerfile[:target]`, space-separated |
| `TIMERS` | the jobs whose timers `make ovh-install` enables |
| `LOG_FORMAT_PENDING` | containers whose lines are not yet in §10's format, each with the issue that brings it there |
| `HEALTH_PATH` | the health endpoint the probes from outside ask |
| `CLOUDFLARE_PROXIED` | the public names Cloudflare proxies, space-separated |
| `MAIL_DOMAIN` | the domain it sends mail from, empty when it sends none |

## 13. The stack test

`make ovh-stack-test` runs the stack on the Mac behind the edge's real configuration: built,
started, asked for its name through the edge, moved from blue to green under a stream of
requests without one failing or waiting, and every line its own code writes read against §10.
It also reads the files: the rules of §3, the units of §8, the snippets of §5. Optional, for the
test alone:

- `deploy/ovh/stack-test-setup.sh`: what the stack needs before it comes up, a database migrated
  and seeded, run with compose pointed at the stack under test;
- `deploy/ovh/stack-test.env`: values that pass the app's own checks of its settings,
  `KEY=value` with `\n` for a line break;
- `deploy/ovh/stack-test.yaml`: what has to differ for the stack to run here at all, added to the
  running stack only.

## 14. Moving a name that serves elsewhere today

The new side runs beside the old one under a preview name, `new.<name>`, and is looked at before
the public name moves; whatever is written between the copy of the data and the switch may be
lost, unless the service says it writes enough for that to matter. Until the record moves, the
snippet keeps `tls internal`. A name Cloudflare proxies first gets a temporary `http://` block
that proxies, and `cloudflare_only` only once it is on Full (strict). After the switch the old
side is stopped for a week, not deleted, so going back is the DNS record.
