#!/usr/bin/env bash
#
# deploy/compose.ovh.yaml on this machine, as the OVH host runs it: images built from this
# checkout, the external `edge` network on its fixed subnet, and a stand-in for the shared edge
# at the edge's fixed address, proxying the way the edge's `proxy` snippet does.
#
# What it proves:
#   - every container becomes healthy (`up --wait`, which is how ci-deploy judges a deploy)
#   - nothing is published on the host, and the database runs the cache sizes chosen in #67,
#     which compose.yaml and compose.ci.yaml share
#   - the smoke test passes through the edge
#   - nginx takes the client's address from X-Real-IP sent by the edge, and from nobody else
#   - nginx, PHP and the application log to the containers' output, and write no log files
#   - a request made while the database is down is logged by the application
#
# Everything it creates is removed on the way out: the compose project with its volumes, the
# stand-in edge, the edge network, two image tags and one directory.
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

export IMAGE_TAG=$TAG DB_PASSWORD=throwaway DB_ROOT_PASSWORD=throwaway-root
compose() { docker compose -p "$PROJECT" -f deploy/compose.ovh.yaml "$@"; }

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
    docker rmi "ghcr.io/jkulak/hhbd-app:$TAG" "ghcr.io/jkulak/hhbd-nginx:$TAG" >/dev/null 2>&1 || true
    rm -rf "$T"
    echo "> removed the $PROJECT project and its volumes, the stand-in edge, the edge network, the $TAG images and $T"
}
trap cleanup EXIT

ok()  { echo "ok   $1"; pass=$((pass + 1)); }
bad() { echo "x    $1"; fail=$((fail + 1)); }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (expected '$2', got '$3')"; fi; }
via_edge() { curl -s --connect-to "hhbd.pl:80:127.0.0.1:$PORT" "$@"; }
nginx_log() { docker logs "$PROJECT-nginx-1" 2>&1; }
app_log() { docker logs "$PROJECT-app-1" 2>&1; }
# Files a running container added or changed that look like logs; `docker diff` lists every
# change against the image, so build-time files such as apt's logs do not count.
written_logs() { docker diff "$1" | grep -E '^[AC] (/var/log/.+|.*\.log)$' || true; }

echo "> images from this checkout, tagged $TAG, for linux/amd64 like the release"
docker build -q --platform linux/amd64 -f Dockerfile-php --target production -t "ghcr.io/jkulak/hhbd-app:$TAG" . >/dev/null
docker build -q --platform linux/amd64 -f Dockerfile-nginx -t "ghcr.io/jkulak/hhbd-nginx:$TAG" . >/dev/null

echo "> the edge network, 172.30.0.0/24 as on the host"
docker network create --subnet 172.30.0.0/24 --gateway 172.30.0.1 edge >/dev/null

echo "> a stand-in edge at 172.30.0.2, up before any service as on the host"
# Like the edge's `proxy` snippet: X-Real-IP is set from the client address the edge resolved.
# Addresses from private ranges are believed in X-Forwarded-For, standing in for Cloudflare's.
cat >"$T/Caddyfile" <<'CADDY'
{
	auto_https off
	servers {
		trusted_proxies static private_ranges
	}
}
http://hhbd.pl, http://www.hhbd.pl {
	reverse_proxy hhbd-web:80 {
		header_up X-Real-IP {client_ip}
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
db_flags() { sed -n '/^  db:/,/^  [a-z]/p' "$1" | sed -n '/command: >/{n;p;}' | sed 's/^ *//'; }
check "compose.yaml and compose.ci.yaml run the database with production's flags" \
    "$(db_flags deploy/compose.ovh.yaml) | $(db_flags deploy/compose.ovh.yaml)" "$(db_flags compose.yaml) | $(db_flags compose.ci.yaml)"

for _ in $(seq 1 30); do
    via_edge -o /dev/null http://hhbd.pl/ && break
    sleep 1
done

echo "> the smoke test, through the edge"
if SMOKE_CURL_OPTS="--connect-to hhbd.pl:80:127.0.0.1:$PORT" ./tests/smoke-test.sh http://hhbd.pl >"$T/smoke" 2>&1; then
    ok "the smoke test passes through the edge ($(grep -o '[0-9]* passed' "$T/smoke"))"
else
    bad "the smoke test passes through the edge"
    tail -30 "$T/smoke"
fi

echo "> the client's address"
via_edge -o /dev/null -H 'X-Forwarded-For: 203.0.113.7' http://hhbd.pl/o-nas.html
check "a client behind the edge is logged with its own address" \
    "203.0.113.7" "$(nginx_log | grep 'GET /o-nas.html' | tail -1 | cut -d' ' -f1)"

via_edge -o /dev/null -H 'X-Real-IP: 198.51.100.10' http://hhbd.pl/kontakt.html
logged=$(nginx_log | grep 'GET /kontakt.html' | tail -1 | cut -d' ' -f1)
if [ -n "$logged" ] && [ "$logged" != 198.51.100.10 ]; then
    ok "an X-Real-IP sent by the client through the edge is replaced ($logged)"
else
    bad "an X-Real-IP sent by the client through the edge is replaced (got '$logged')"
fi

docker run --rm --network edge busybox:1.37.0 \
    wget -q -O /dev/null --header 'Host: hhbd.pl' --header 'X-Real-IP: 198.51.100.9' http://hhbd-web/wykonawcy.html
logged=$(nginx_log | grep 'GET /wykonawcy.html' | tail -1 | cut -d' ' -f1)
case "$logged" in
    172.30.0.*) ok "X-Real-IP from anywhere but the edge is ignored ($logged)" ;;
    *)          bad "X-Real-IP from anywhere but the edge is ignored (got '$logged')" ;;
esac

echo "> the logs"
check "the healthcheck's own requests stay out of the access log" \
    "0" "$(nginx_log | grep -c '^127\.0\.0\.1 ' || true)"

docker exec "$PROJECT-app-1" sh -c 'printf "%s" "<?php error_log(\"stacktest-php-error-log\"); echo 1;" > /var/www/html/app/public/stacktest.php'
via_edge -o /dev/null http://hhbd.pl/stacktest.php
docker exec "$PROJECT-app-1" rm -f /var/www/html/app/public/stacktest.php
sleep 1
check "PHP's error_log reaches the app container's output" \
    "1" "$(app_log | grep -c 'stacktest-php-error-log' || true)"

docker stop "$PROJECT-db-1" >/dev/null
status=$(via_edge -o /dev/null -w '%{http_code}' http://hhbd.pl/albumy.html)
sleep 1
check "a request while the database is down gets a 500" "500" "$status"
check "and the application's log of it reaches the app container's output" \
    "1" "$(app_log | grep 'EMERG' | grep -c '/albumy.html|2002|' || true)"

check "the app container wrote no log files" "" "$(written_logs "$PROJECT-app-1")"
check "the nginx container wrote no log files" "" "$(written_logs "$PROJECT-nginx-1")"

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
