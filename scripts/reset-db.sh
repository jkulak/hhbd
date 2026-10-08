#!/usr/bin/env bash
#
# Drop the local hhbd database and load it again from database/tests/: the schema and the
# fixtures the smoke test runs on. Run before and after a piece of work, so the database never
# carries what the last one left in it.
#
# It acts on one thing only: the running db container of this checkout's compose project, on
# the local Docker engine. Before a single statement reaches a database it refuses
#   - a Docker engine that is not a local socket: a context or DOCKER_HOST pointing elsewhere
#     would have `docker compose` drop a database on another machine;
#   - a project with no running db container;
#   - a db container created from another directory, or from deploy/compose.ovh.yaml, which is
#     production's compose file and must never be reset.
#
# Credentials never pass through here: the commands run inside the container and take the root
# password from its own environment.
#
# Usage: make reset-db      (with the local stack up: docker compose up -d)
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

FIXTURES=(database/tests/01-schema.sql database/tests/02-test-fixtures.sql)
started=$(date +%s)
refuse() { echo "x reset-db: $1; nothing was touched" >&2; exit 1; }

engine=${DOCKER_HOST:-$(docker context inspect --format '{{.Endpoints.docker.Host}}' 2>/dev/null || true)}
case "$engine" in
    unix://*|npipe://*) ;;
    *) refuse "Docker points at '${engine:-nothing}', not a local socket" ;;
esac

project=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
[ -n "$project" ] || refuse "docker compose finds no project in $PWD"

cid=$(docker compose ps -q --status running db 2>/dev/null | head -1)
[ -n "$cid" ] || refuse "no running db container in the compose project '$project'; start it with docker compose up -d"

label() { docker inspect --format "{{index .Config.Labels \"$1\"}}" "$cid"; }
[ "$(label com.docker.compose.project.working_dir)" = "$PWD" ] \
    || refuse "the db container of '$project' was created from $(label com.docker.compose.project.working_dir), not from this checkout"
case ",$(label com.docker.compose.project.config_files)," in
    *",$PWD/compose.yaml,"*) ;;
    *) refuse "the db container of '$project' was not created from this checkout's compose.yaml" ;;
esac
case "$(label com.docker.compose.project.config_files)" in
    *compose.ovh.yaml*) refuse "the db container of '$project' runs production's compose file" ;;
esac

# Freshly started, the server may not accept connections yet.
for i in $(seq 1 60); do
    [ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}healthy{{end}}' "$cid")" = healthy ] && break
    [ "$i" -eq 60 ] && refuse "the db container of '$project' is not healthy after 60 seconds"
    sleep 1
done

in_db() { # in_db <mariadb arguments...>: as root, with the container's own password
    docker exec -i "$cid" sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot "$@"' -- "$@"
}
database=$(docker exec "$cid" sh -c 'printf %s "${MYSQL_DATABASE:?}"')

echo "> 1/3 dropping and creating $database in $project"
# The hhbd user's grants are on hhbd.*, not on the database object, so they survive the drop.
in_db -e "DROP DATABASE IF EXISTS \`$database\`; CREATE DATABASE \`$database\`"

echo "> 2/3 loading ${FIXTURES[*]}"
for f in "${FIXTURES[@]}"; do in_db "$database" <"$f"; done

echo "> 3/3 counting"
tables=$(in_db -N -B "$database" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'" </dev/null)
rows=0
for t in $(in_db -N -B "$database" -e "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'" </dev/null | cut -f1); do
    rows=$((rows + $(in_db -N -B "$database" -e "SELECT COUNT(*) FROM \`$t\`" </dev/null)))
done
echo "ok $database in $project: $tables tables, $rows rows, from database/tests/ in $(( $(date +%s) - started ))s"
