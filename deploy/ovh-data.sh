#!/usr/bin/env bash
#
# HHBD - move the data from the Google VM to the OVH host
#
# The database and content/, streamed through this Mac; nothing lands on its disk. The Google
# side is only read. On the OVH host it writes into hhbd's database, which must be running
# (`make ovh-db-up` starts it alone, before the first deploy), and into the hhbd_content
# volume, which it creates if needed.
#
#   deploy/ovh-data.sh db              dump on Google, restore on OVH
#   deploy/ovh-data.sh db-check        exact count(*) and CHECKSUM TABLE per table, both sides
#   deploy/ovh-data.sh content         make hhbd_content an exact copy of content/
#   deploy/ovh-data.sh content-check   file count, bytes and a sha256 of every file, both sides
#   deploy/ovh-data.sh all             all four, in that order
#
# The first run, before the window, gives the first deploy a copy to work against. The final
# run comes after the writers on Google are stopped (deploy/README.md). Each run replaces what
# the previous one left, so a run can be repeated.
#
# Credentials never pass through here: each command runs inside its database container and
# takes the root password from that container's own environment.
#
# Every remote command goes through `from` and `to`. FROM_SHELL and TO_SHELL replace the ssh
# hop with a local shell, which is how tests/ovh-data-test.sh runs this against two
# throwaway databases.
#
# Commands in single quotes are expanded on the far side, inside a container, on purpose.
# shellcheck disable=SC2016
set -euo pipefail

GCP_PROJECT=${GCP_PROJECT:-hhbd-483111}
GCP_ZONE=${GCP_ZONE:-us-central1-a}
GCP_VM=${GCP_VM:-hhbd-server}

# compose names the containers after the project: /opt/hhbd on Google, `name: hhbd` on OVH.
FROM_DB=${FROM_DB:-hhbd-db-1}
FROM_CONTENT=${FROM_CONTENT:-/opt/hhbd/content}
TO_DB=${TO_DB:-hhbd-db-1}
TO_CONTENT=${TO_CONTENT:-hhbd_content}
SUDO=${SUDO-sudo}
# The same tools on both sides, so file listings and checksums compare byte for byte.
BUSYBOX=${BUSYBOX:-busybox:1.37.0}

from() {
    if [ -n "${FROM_SHELL:-}" ]; then $FROM_SHELL "$1"
    else gcloud compute ssh "$GCP_VM" --project="$GCP_PROJECT" --zone="$GCP_ZONE" --quiet --command="$1"
    fi
}
to() {
    if [ -n "${TO_SHELL:-}" ]; then $TO_SHELL "$1"
    else ssh -o BatchMode=yes "${OVH_SSH_USER:-ubuntu}@${OVH_HOST:?OVH_HOST is not set}" "$1"
    fi
}

# Runs inside a database container, as root with the container's own password.
in_db() { # in_db <container> <shell command>
    printf "%s docker exec -i %s sh -c 'export MYSQL_PWD=\"\${MYSQL_ROOT_PASSWORD:?}\"; %s'" "$SUDO" "$1" "$2"
}

db() {
    echo "> database: $FROM_DB on Google -> $TO_DB on OVH"
    to "$SUDO docker inspect $TO_DB >/dev/null" \
        || { echo "x no $TO_DB on the OVH host; start it with make ovh-db-up" >&2; exit 1; }
    # --single-transaction for a consistent snapshot without locking; routines, triggers and
    # events are not dumped unless asked for. --hex-blob keeps binary columns byte-exact.
    from "$(in_db "$FROM_DB" 'exec mysqldump -uroot --single-transaction --routines --triggers --events --hex-blob --default-character-set=utf8mb4 "$MYSQL_DATABASE"') | gzip -1" \
        | to "gunzip | $(in_db "$TO_DB" 'exec mariadb -uroot "$MYSQL_DATABASE"')"
    echo "ok database restored"
}

# One line per table: name, exact row count, checksum of the rows. Never information_schema's
# row estimates, which are approximate for InnoDB.
TABLE_REPORT='
q() { mariadb -uroot -N -B "$MYSQL_DATABASE" -e "$1" </dev/null; }
q "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = \"BASE TABLE\" ORDER BY table_name" |
while read -r t; do
    printf "%s\t%s\t%s\n" "$t" "$(q "SELECT COUNT(*) FROM \`$t\`")" "$(q "CHECKSUM TABLE \`$t\` EXTENDED" | cut -f2)"
done'

db_check() {
    echo "> database: exact counts and checksums, both sides"
    local a b
    a=$(from "$(in_db "$FROM_DB" "$TABLE_REPORT")")
    b=$(to "$(in_db "$TO_DB" "$TABLE_REPORT")")
    [ -n "$a" ] || { echo "x no tables on the Google side" >&2; exit 1; }
    printf '%s\n' "$a" | awk -F'\t' '{ rows += $2 } END { printf "  Google: %d tables, %d rows\n", NR, rows }'
    printf '%s\n' "$b" | awk -F'\t' '{ rows += $2 } END { printf "  OVH:    %d tables, %d rows\n", NR, rows }'
    if [ "$a" = "$b" ]; then
        echo "ok every table matches on count(*) and checksum"
        return 0
    fi
    echo "x the two sides differ (table, count, checksum; < Google, > OVH):" >&2
    diff <(printf '%s\n' "$a") <(printf '%s\n' "$b") >&2 || true
    echo "  Equal counts with different checksums mean the rows differ; a different ROW_FORMAT" >&2
    echo "  alone also changes a checksum, so compare SHOW CREATE TABLE before anything else." >&2
    exit 1
}

content() {
    echo "> content: $FROM_CONTENT on Google -> volume $TO_CONTENT on OVH"
    # Before the first deploy the volume does not exist yet. It is made with the labels compose
    # would give it, so compose adopts it as the project's own instead of warning that it was
    # not created by compose.
    to "$SUDO docker volume inspect $TO_CONTENT >/dev/null 2>&1 || $SUDO docker volume create --label com.docker.compose.project=hhbd --label com.docker.compose.volume=content $TO_CONTENT >/dev/null"
    # The volume is emptied first, so it ends up an exact copy, not a copy plus leftovers of an
    # earlier run. Owners are not restored: everything belongs to root and is made readable,
    # which is all nginx needs.
    from "$SUDO docker run --rm -v $FROM_CONTENT:/c:ro -w /c $BUSYBOX tar -cf - ." \
        | to "$SUDO docker run --rm -i -v $TO_CONTENT:/c $BUSYBOX sh -c 'find /c -mindepth 1 -delete && tar -xof - -C /c && chmod -R a+rX /c'"
    echo "ok content copied"
}

FILE_REPORT='find . -type f -exec sha256sum {} + | sort -k2'

content_check() {
    echo "> content: files, bytes and checksums, both sides"
    local a b
    a=$(from "$SUDO docker run --rm -v $FROM_CONTENT:/c:ro -w /c $BUSYBOX sh -c '$FILE_REPORT'")
    b=$(to "$SUDO docker run --rm -v $TO_CONTENT:/c:ro -w /c $BUSYBOX sh -c '$FILE_REPORT'")
    local bytes='find . -type f -exec stat -c %s {} + | awk "{ s += \$1 } END { print s + 0 }"'
    echo "  Google: $(printf '%s\n' "$a" | grep -c . || true) files, $(from "$SUDO docker run --rm -v $FROM_CONTENT:/c:ro -w /c $BUSYBOX sh -c '$bytes'") bytes"
    echo "  OVH:    $(printf '%s\n' "$b" | grep -c . || true) files, $(to "$SUDO docker run --rm -v $TO_CONTENT:/c:ro -w /c $BUSYBOX sh -c '$bytes'") bytes"
    if [ "$a" = "$b" ]; then
        echo "ok every file matches by name and sha256"
        return 0
    fi
    echo "x the two sides differ (sha256, path; < Google, > OVH):" >&2
    diff <(printf '%s\n' "$a") <(printf '%s\n' "$b") | head -50 >&2 || true
    exit 1
}

case "${1:-}" in
    db)            db ;;
    db-check)      db_check ;;
    content)       content ;;
    content-check) content_check ;;
    all)           db && db_check && content && content_check ;;
    *)
        echo "usage: $0 {db|db-check|content|content-check|all}" >&2
        exit 2
        ;;
esac
