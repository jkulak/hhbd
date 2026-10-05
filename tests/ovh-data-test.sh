#!/usr/bin/env bash
#
# deploy/ovh-data.sh against two throwaway MariaDB containers and two content locations on
# this machine, instead of the Google VM and the OVH host. The ssh hops are replaced by a
# local shell; every command inside them is the one that runs in production.
#
# What it proves:
#   - the dump restores, and the restored side matches on exact counts and checksums
#   - a missing row on the OVH side fails the database check
#   - content is copied exactly, file names with Polish characters included
#   - a stray file left on the OVH side fails the content check, and a second copy removes it
#
# Everything it creates is removed on the way out: two containers, one volume, one directory.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

SRC=hhbd-datatest-src
DST=hhbd-datatest-dst
VOL=hhbd-datatest-content
TMP=$(mktemp -d "${TMPDIR:-/tmp}/hhbd-datatest.XXXXXX")
pass=0
fail=0

cleanup() {
    docker rm -f "$SRC" "$DST" >/dev/null 2>&1 || true
    docker volume rm -f "$VOL" >/dev/null 2>&1 || true
    rm -rf "$TMP"
    echo "> removed the containers $SRC and $DST, the volume $VOL and $TMP"
}
trap cleanup EXIT

ok()   { echo "ok   $1"; pass=$((pass + 1)); }
bad()  { echo "x    $1"; fail=$((fail + 1)); }
expect_success() { if "${@:2}" >"$TMP/out" 2>&1; then ok "$1"; grep "^  " "$TMP/out" || true; else bad "$1"; cat "$TMP/out"; fi; }
expect_failure() { if "${@:2}" >"$TMP/out" 2>&1; then bad "$1"; cat "$TMP/out"; else ok "$1"; fi; }

data() {
    FROM_SHELL="sh -c" TO_SHELL="sh -c" SUDO="" \
    FROM_DB=$SRC TO_DB=$DST FROM_CONTENT="$TMP/content" TO_CONTENT=$VOL \
        ./deploy/ovh-data.sh "$@"
}

echo "> two throwaway databases: one seeded with the test fixtures, one empty"
for name in "$SRC" "$DST"; do
    seed=()
    [ "$name" = "$SRC" ] && seed=(-v "$PWD/database/tests:/docker-entrypoint-initdb.d:ro")
    docker run -d --name "$name" ${seed[@]+"${seed[@]}"} \
        -e MYSQL_ROOT_PASSWORD=throwaway-root -e MYSQL_DATABASE=hhbd \
        -e MYSQL_USER=hhbd -e MYSQL_PASSWORD=throwaway \
        mariadb:10.11.8 >/dev/null
done
for name in "$SRC" "$DST"; do
    for _ in $(seq 1 90); do
        docker exec "$name" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break
        sleep 1
    done
done
# The seeded database is ready only once the init scripts are through and the server restarted.
for _ in $(seq 1 90); do
    docker exec "$SRC" sh -c 'MYSQL_PWD=throwaway-root mariadb -uroot -N -B hhbd -e "SELECT COUNT(*) FROM albums"' >/dev/null 2>&1 && break
    sleep 1
done

echo "> content on the source side; the volume on the other is created by the copy"
mkdir -p "$TMP/content/a/th" "$TMP/content/p"
printf 'cover' >"$TMP/content/a/535.jpg"
printf 'thumb' >"$TMP/content/a/th/535-th.jpg"
printf 'photo' >"$TMP/content/p/zażółć gęślą jaźń.jpg"
head -c 100000 /dev/urandom >"$TMP/content/p/large.bin"

expect_success "the dump restores on the empty side"      data db
expect_success "both sides match on counts and checksums" data db-check

docker exec "$DST" sh -c 'MYSQL_PWD=throwaway-root mariadb -uroot hhbd -e "DELETE FROM albums ORDER BY id DESC LIMIT 1"'
expect_failure "a missing row fails the database check"   data db-check
expect_success "restoring again replaces what was there"  data db
expect_success "and the sides match again"                data db-check

expect_success "content copies into the volume"           data content
expect_success "both sides match on files and checksums"  data content-check

docker run --rm -v "$VOL:/c" busybox:1.37.0 sh -c 'echo stray > /c/stray.txt'
expect_failure "a stray file fails the content check"     data content-check
expect_success "copying again leaves no stray file"       data content
expect_success "and the sides match again"                data content-check

echo ""
echo "$pass passed, $fail failed"
[ "$fail" -eq 0 ]
