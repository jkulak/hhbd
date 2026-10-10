#!/usr/bin/env bash
#
# deploy/ovh/compose.yaml on this machine, as the OVH host runs it: images built from this
# checkout, the external `edge` network on its fixed subnet, and a stand-in for the shared edge
# at the edge's fixed address, proxying the way the edge's `proxy` snippet does.
#
# What it proves:
#   - every container becomes healthy (`up --wait`, which is how ci-deploy judges a deploy)
#   - nothing is published on the host, and the database runs the cache sizes chosen in #67,
#     which compose.yaml and compose.ci.yaml share
#   - the importer's image writes the fixtures' images into the content volume, and the smoke
#     test passes through the edge
#   - nginx takes the client's address from X-Real-IP sent by the edge, and from nobody else
#   - a request Cloudflare took over HTTPS is HTTPS to the application, so its canonical tags
#     and sitemaps say https:// (#147)
#   - a .php file that does not exist is nginx's own 404, without PHP-FPM or a log line (#34)
#   - nginx writes no access log and nothing at all on a healthy run; the app container writes
#     only JSON lines in the shared host's format (#101; CONTRACT.md §9 in gcloud-ovh-migrate),
#     a PHP error among them, as one line with `error`, `stack` and the edge's `request_id`
#   - a request made while the database is down is logged by the application, at error
#   - nothing is written to a log file in a container
#   - `up` leaves the import job alone, and the job, fed a batch on stdin as make ovh-import
#     feeds it, writes covers into the content volume that nginx then serves, and its progress
#     lines are JSON too
#
# Everything it creates is removed on the way out: the compose project with its volumes, the
# stand-in edge, the edge network, three image tags and one directory. With STACKTEST_PREBUILT=1
# it takes the three images as they are, built and tagged by its caller (CI, with its layer
# cache), and removes them all the same.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

PROJECT=hhbd-stacktest
TAG=stacktest
EDGE=hhbd-stacktest-edge
PORT=${STACKTEST_PORT:-18480}
T=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-stacktest.XXXXXX")
pass=0
fail=0

export IMAGE_TAG=$TAG DB_PASSWORD=throwaway DB_ROOT_PASSWORD=throwaway-root LEGACY_PASSWORD_SALT=fixtures-legacy-salt GA_MEASUREMENT_ID=
compose() { docker compose -p "$PROJECT" -f deploy/ovh/compose.yaml "$@"; }

if docker network inspect edge >/dev/null 2>&1; then
    echo "x a Docker network called edge exists already; this test makes its own and will not touch that one" >&2
    exit 1
fi
# Docker hands out 172.17-31.0.0/16 to compose projects' default networks, so on a machine with
# many of them one may hold the edge's range already.
taken=$(docker network ls -q | xargs docker network inspect --format '{{.Name}} {{range .IPAM.Config}}{{.Subnet}} {{end}}' \
    | awk '$2 ~ /^172\.30\./ { print $1 " (" $2 ")" }')
if [ -n "$taken" ]; then
    echo "x the edge's range 172.30.0.0/24 is taken by: $taken; remove that network, or stop its project, and run again" >&2
    exit 1
fi

cleanup() {
    compose down -v --remove-orphans >/dev/null 2>&1 || true
    docker rm -f "$EDGE" >/dev/null 2>&1 || true
    docker network rm edge >/dev/null 2>&1 || true
    docker rmi "ghcr.io/jkulak/hhbd-app:$TAG" "ghcr.io/jkulak/hhbd-nginx:$TAG" "ghcr.io/jkulak/hhbd-importer:$TAG" >/dev/null 2>&1 || true
    rm -rf "$T"
    echo "> removed the $PROJECT project and its volumes, the stand-in edge, the edge network, the $TAG images and $T"
}
trap cleanup EXIT

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected '$2', got '$3')"; fi; }
via_edge() { curl -s --connect-to "hhbd.pl:80:127.0.0.1:$PORT" "$@"; }
# Both colours run, as while a deploy overlaps them; the stand-in edge sends to blue.
nginx_log() { docker logs "$PROJECT-nginx-blue-1" 2>&1; }
app_log() { docker logs "$PROJECT-app-blue-1" 2>&1; }
# Files a running container added or changed that look like logs; `docker diff` lists every
# change against the image, so build-time files such as apt's logs do not count.
written_logs() { docker diff "$1" | grep -E '^[AC] (/var/log/.+|.*\.log)$' || true; }
# The lines of a log that are not one JSON object with time (UTC, to the millisecond), a level
# of the four and a msg, as the shared stack test reads them.
not_contract() {
    python3 -c '
import json, re, sys
TIME = re.compile(r"\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3,9}(Z|\+00:00)")
for line in sys.stdin:
    line = line.strip()
    if not line:
        continue
    try:
        o = json.loads(line)
    except ValueError:
        o = None
    if not (isinstance(o, dict) and TIME.fullmatch(str(o.get("time", ""))) and o.get("level") in ("debug", "info", "warn", "error") and isinstance(o.get("msg"), str) and o["msg"]):
        print(line[:120])'
}
# Runs a PHP file in the app, as a request through the edge: probe <name> <php> [curl options]
# nginx passes on only a script its own copy of public/ has (#34), so an empty file of the same
# name goes there too; the PHP runs in the app.
probe() {
    local name=$1 php=$2
    shift 2
    printf '%s' "$php" | docker exec -i "$PROJECT-app-blue-1" sh -c "cat > /var/www/html/app/public/$name.php"
    docker exec "$PROJECT-nginx-blue-1" touch "/var/www/html/app/public/$name.php"
    via_edge "$@" "http://hhbd.pl/$name.php"
    docker exec "$PROJECT-app-blue-1" rm -f "/var/www/html/app/public/$name.php"
    docker exec "$PROJECT-nginx-blue-1" rm -f "/var/www/html/app/public/$name.php"
}

if [ "${STACKTEST_PREBUILT:-}" = 1 ]; then
    # CI builds them in the steps before, with its layer cache, under the same tags.
    echo "> images from this checkout, tagged $TAG, built before the test"
    for image in app nginx importer; do
        docker image inspect "ghcr.io/jkulak/hhbd-$image:$TAG" >/dev/null || { echo "x STACKTEST_PREBUILT=1, but there is no ghcr.io/jkulak/hhbd-$image:$TAG" >&2; exit 1; }
    done
else
    echo "> images from this checkout, tagged $TAG, for linux/amd64 like the release"
    docker build -q --platform linux/amd64 -f Dockerfile-php --target production -t "ghcr.io/jkulak/hhbd-app:$TAG" . >/dev/null
    docker build -q --platform linux/amd64 -f Dockerfile-nginx -t "ghcr.io/jkulak/hhbd-nginx:$TAG" . >/dev/null
    docker build -q --platform linux/amd64 -f Dockerfile-php --target importer -t "ghcr.io/jkulak/hhbd-importer:$TAG" . >/dev/null
fi

echo "> the edge network, 172.30.0.0/24 as on the host"
docker network create --subnet 172.30.0.0/24 --gateway 172.30.0.1 edge >/dev/null

echo "> a stand-in edge at 172.30.0.2, up before any service as on the host"
# Like the edge's `proxy` snippet: X-Real-IP is set from the client address the edge resolved,
# and every request gets an id of its own as X-Request-Id, replacing any a client sent.
# Addresses from private ranges are believed in X-Forwarded-For, standing in for Cloudflare's.
cat >"$T/Caddyfile" <<'CADDY'
{
	auto_https off
	servers {
		trusted_proxies static private_ranges
	}
}
http://hhbd.pl, http://www.hhbd.pl {
	reverse_proxy hhbd-blue-web:80 {
		header_up X-Real-IP {client_ip}
		header_up X-Request-Id {http.request.uuid}
	}
}
CADDY
docker run -d --name "$EDGE" --network edge --ip 172.30.0.2 -p "127.0.0.1:$PORT:80" \
    -v "$T/Caddyfile:/etc/caddy/Caddyfile:ro" caddy:2.11.4-alpine >/dev/null

echo "> the database alone, migrated from scratch and seeded with the test fixtures, as before a first deploy"
compose up -d --wait --wait-timeout 180 db
migrate() { MIGRATE_TARGET=container MIGRATE_CONTAINER="$PROJECT-db-1" ./scripts/migrate.sh "$@" >/dev/null; }
migrate up 0001
seed() { docker exec -i "$PROJECT-db-1" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -uroot --default-character-set=utf8mb4 "$MYSQL_DATABASE"' <"$1"; }
seed database/tests/fixtures.sql
migrate up
# The rows for the tables the migrations add, as make reset-db loads them.
seed database/tests/fixtures-latest.sql

echo "> the whole stack"
if compose up -d --wait --wait-timeout 180; then
    ok "every container becomes healthy"
else
    bad "every container becomes healthy"
    compose ps
    compose logs --tail=50
    exit 1
fi
check "nothing is published on the host" \
    "0" "$(docker ps --filter "label=com.docker.compose.project=$PROJECT" --format '{{.Ports}}' | grep -c -- '->' || true)"

# The cache sizes chosen for an all-InnoDB database (#67), as the server reports them.
check "the database runs with production's cache sizes (buffer pool, key cache, Aria cache)" \
    "100663296 8388608 33554432" \
    "$(docker exec "$PROJECT-db-1" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -uroot -N -B -e "SELECT CONCAT_WS(\" \", @@innodb_buffer_pool_size, @@key_buffer_size, @@aria_pagecache_buffer_size)"')"
check "the database runs with NO_ZERO_IN_DATE and NO_ZERO_DATE (#88)" "1 1" \
    "$(docker exec "$PROJECT-db-1" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mariadb -uroot -N -B -e "SELECT CONCAT_WS(\" \", FIND_IN_SET(\"NO_ZERO_IN_DATE\", @@GLOBAL.sql_mode) > 0, FIND_IN_SET(\"NO_ZERO_DATE\", @@GLOBAL.sql_mode) > 0)"')"
db_flags() { sed -n '/^  db:/,/^  [a-z]/p' "$1" | sed -n '/command: >/{n;p;}' | sed 's/^ *//'; }
check "compose.yaml and compose.ci.yaml run the database with production's flags" \
    "$(db_flags deploy/ovh/compose.yaml) | $(db_flags deploy/ovh/compose.yaml)" "$(db_flags compose.yaml) | $(db_flags compose.ci.yaml)"

for _ in $(seq 1 30); do
    via_edge -o /dev/null http://hhbd.pl/ && break
    sleep 1
done

# The fixtures' covers, photos, logos and news images in the content volume, as make test-images
# makes them, so the smoke test finds the files a page names (#133).
echo "> the fixtures' images, written into the content volume by the importer's image"
if compose run --rm --no-deps -T --entrypoint php importer app/tools/generate-test-images.php >"$T/images" 2>&1; then
    ok "the importer's image writes the fixtures' images into the content volume"
else
    bad "the importer's image writes the fixtures' images into the content volume"
    tail -10 "$T/images"
fi

echo "> the smoke test, through the edge"
if SMOKE_CURL_OPTS="--connect-to hhbd.pl:80:127.0.0.1:$PORT" ./tests/smoke-test.sh http://hhbd.pl >"$T/smoke" 2>&1; then
    ok "the smoke test passes through the edge ($(grep -o '[0-9]* passed' "$T/smoke"))"
else
    bad "the smoke test passes through the edge"
    tail -30 "$T/smoke"
fi

echo "> the scheme, as the application sees it (#147)"
# Cloudflare sends X-Forwarded-Proto: https, which the edge passes on from Cloudflare's address
# as this one does from a private one; a plain request gets the edge's own http.
scheme='<?php echo empty($_SERVER["HTTPS"]) ? "http" : "https";'
check "a request Cloudflare took over HTTPS is HTTPS to the application" "https" "$(probe scheme "$scheme" -H 'X-Forwarded-Proto: https')"
check "one the edge took over plain HTTP is not" "http" "$(probe scheme "$scheme")"

echo "> the client's address, as the application sees it"
addr='<?php echo $_SERVER["REMOTE_ADDR"];'
check "a client behind the edge is seen with its own address" "203.0.113.7" "$(probe addr "$addr" -H 'X-Forwarded-For: 203.0.113.7')"
seen=$(probe addr "$addr" -H 'X-Real-IP: 198.51.100.10')
if [ -n "$seen" ] && [ "$seen" != 198.51.100.10 ]; then
    ok "an X-Real-IP sent by the client through the edge is replaced ($seen)"
else
    bad "an X-Real-IP sent by the client through the edge is replaced (got '$seen')"
fi
printf '%s' "$addr" | docker exec -i "$PROJECT-app-blue-1" sh -c 'cat > /var/www/html/app/public/addr.php'
docker exec "$PROJECT-nginx-blue-1" touch /var/www/html/app/public/addr.php
seen=$(docker run --rm --network edge busybox:1.37.0 wget -q -O - --header 'Host: hhbd.pl' --header 'X-Real-IP: 198.51.100.9' http://hhbd-blue-web/addr.php)
docker exec "$PROJECT-app-blue-1" rm -f /var/www/html/app/public/addr.php
docker exec "$PROJECT-nginx-blue-1" rm -f /var/www/html/app/public/addr.php
case "$seen" in
    172.30.0.*) ok "X-Real-IP from anywhere but the edge is ignored ($seen)" ;;
    *)          bad "X-Real-IP from anywhere but the edge is ignored (got '$seen')" ;;
esac

echo "> the import job, as make ovh-import runs it: the batch as a tar on stdin"
check "up started no importer: it is a job" "" "$(compose ps -a --format '{{.Service}}' | grep -x importer || true)"
if COPYFILE_DISABLE=1 tar --no-xattrs -C tests/import/batch -cf - . \
    | compose run --rm --no-deps -T importer --apply >"$T/import.json" 2>"$T/import.err"; then
    ok "the importer reads the test batch"
else
    bad "the importer reads the test batch"
    tail -20 "$T/import.err"
fi
check "into the catalogue" "7 created, 3 updated, 1 unchanged, 0 refused" \
    "$(jq -r '.totals | "\(.created) created, \(.updated) updated, \(.unchanged) unchanged, \(.refused) refused"' "$T/import.json" 2>/dev/null)"
cover=$(jq -r 'select(.ref == "release:testowy-album") | .cover.sha256' tests/import/batch/batch.ndjson)
album=$(jq -r '.documents[] | select(.ref == "release:testowy-album") | .url' "$T/import.json" 2>/dev/null)
check "the album's page, through the edge, shows the cover the importer wrote" "1" \
    "$(via_edge "http://hhbd.pl$album" | grep -c "/content/a/600/$cover.jpg" || true)"
check "and nginx serves it from the content volume" "200 image/jpeg" \
    "$(via_edge -o /dev/null -w '%{http_code} %{content_type}' "http://hhbd.pl/content/a/600/$cover.jpg")"
check "its progress lines are JSON in the host's format, one per document and one for the run" "12 " \
    "$(grep -c '"logger":"importer"' "$T/import.err") $(grep '^{' "$T/import.err" | not_contract)"

echo "> a .php file that does not exist (#34)"
check "answers nginx's own 404, never reaching PHP-FPM" "404 nginx" \
    "$(via_edge -o "$T/missing" -w '%{http_code}' http://hhbd.pl/wp-login.php) $(grep -o nginx "$T/missing" | head -1)"

echo "> the logs"
check "nginx wrote no access-log line for the requests above, and nothing else on a healthy run" "0" \
    "$(nginx_log | grep -c . || true)"

probe stacktest '<?php trigger_error("stacktest-php-error", E_USER_WARNING); echo 1;' -o /dev/null
sleep 1
line=$(app_log | grep '"msg":"stacktest-php-error"' || true)
check "a PHP error reaches the app's output as one JSON line with error, stack and the edge's request id" "1 warn yes yes yes" \
    "$(grep -c . <<<"$line") $(jq -r '[.level, (if (.error // "") != "" then "yes" else "no" end), (if (.stack // "") != "" then "yes" else "no" end), (if (.request_id // "") != "" then "yes" else "no" end)] | join(" ")' <<<"$line" 2>/dev/null)"

docker stop "$PROJECT-db-1" >/dev/null
status=$(via_edge -o /dev/null -w '%{http_code}' http://hhbd.pl/albumy.html)
sleep 1
check "a request while the database is down gets a 500" "500" "$status"
check "and the application logs it once, at error, with its path and the database's 2002" "1" \
    "$(app_log | jq -c 'select(.level == "error" and .path == "/albumy.html" and .status == 500 and (.error | test("2002")))' 2>/dev/null | grep -c . || true)"
check "every line the app container wrote is one JSON object with time, level and msg" "" "$(app_log | not_contract)"

check "the app container wrote no log files" "" "$(written_logs "$PROJECT-app-blue-1")"
check "the nginx container wrote no log files" "" "$(written_logs "$PROJECT-nginx-blue-1")"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
