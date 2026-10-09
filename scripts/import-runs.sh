#!/usr/bin/env bash
#
# The last import runs (#52), newest first: when each started and how long it took, its mode
# and batch file, what it did to the documents it read, and how many provenance rows it wrote.
# A run with no time taken never finished; a crash or a refusal stopped it.
#
# Usage: scripts/import-runs.sh [<how many>, 20 by default]
#
# Where it reads, DB_TARGET: local (this checkout's compose project, the default) or ovh
# (production's database over ssh, OVH_HOST from gcloud-ovh-migrate's .env), as scripts/migrate.sh reaches them.
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
# shellcheck source=scripts/lib-db.sh
. scripts/lib-db.sh
REFUSE_AS=import-runs

limit=${1:-20}
case "$limit" in ''|*[!0-9]*|0) echo "usage: $0 [<how many>, a positive number]" >&2; exit 2 ;; esac

use_db_target "${DB_TARGET:-local}"
query() { printf '%s\n' "$1" | db_sql; }

[ "$(query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'import_runs'")" = 1 ] \
    || refuse "$where has no import_runs table; it comes with migration 0007"

rows=$(query "SELECT r.id, r.started, IFNULL(TIMEDIFF(r.finished, r.started), '-'), r.mode, r.batch,
                     LEFT(r.batch_sha256, 12), r.created_count, r.updated_count, r.unchanged_count,
                     r.skipped_count, r.refused_count,
                     (SELECT COUNT(*) FROM import_provenance p WHERE p.run_id = r.id)
                FROM import_runs r ORDER BY r.id DESC LIMIT $limit")

echo "import runs on $where, newest first"
if [ -z "$rows" ]; then
    echo "  none yet"
    exit 0
fi
# Columns as wide as their widest value, so the table reads without `column`, which not every
# machine this runs on has.
{ printf 'run\tstarted\ttook\tmode\tbatch\tsha256\tcreated\tupdated\tunchanged\tskipped\trefused\tprovenance\n'
  printf '%s\n' "$rows"; } \
    | awk -F'\t' '{ for (i = 1; i <= NF; i++) { cell[NR, i] = $i; if (length($i) > w[i]) w[i] = length($i) } n = NR; f = NF }
                  END { for (r = 1; r <= n; r++) { line = " "; for (i = 1; i <= f; i++) line = line sprintf(" %-" w[i] "s", cell[r, i]); sub(/ +$/, "", line); print line } }'
