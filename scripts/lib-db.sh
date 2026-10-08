# shellcheck shell=bash
# Shared by the scripts that reach a database (reset-db.sh, migrate.sh): how to find this
# checkout's local db container, what to refuse first, and how to run mariadb inside a
# container. Sourced, never run. The caller sets REFUSE_AS to its own name.

refuse() { echo "x ${REFUSE_AS:-db}: $1; nothing was touched" >&2; exit 1; }

# The engine must be a local socket. A context or DOCKER_HOST pointing elsewhere would have
# `docker compose` act on a database on another machine.
require_local_engine() {
    local engine
    engine=${DOCKER_HOST:-$(docker context inspect --format '{{.Endpoints.docker.Host}}' 2>/dev/null || true)}
    case "$engine" in
        unix://*|npipe://*) ;;
        *) refuse "Docker points at '${engine:-nothing}', not a local socket" ;;
    esac
}

# wait_healthy <container> <what it is called in a message>: a freshly started server may not
# accept connections yet. A container without a healthcheck counts as healthy.
wait_healthy() {
    local i
    for i in $(seq 1 60); do
        [ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}healthy{{end}}' "$1")" = healthy ] && return 0
        [ "$i" -eq 60 ] && refuse "$2 is not healthy after 60 seconds"
        sleep 1
    done
}

# local_db_container: prints the id of this checkout's running db container, once healthy.
# Refuses a project with no running db, and a db created from another directory (a second
# checkout using this one's project name) or from deploy/compose.ovh.yaml, which is
# production's compose file and must never be reset or migrated by accident.
local_db_container() {
    require_local_engine
    local project cid
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

    wait_healthy "$cid" "the db container of '$project'"
    printf '%s\n' "$cid"
}

# mariadb as root inside a db container, with the container's own password, SQL on stdin, rows
# out as tab-separated lines. utf8mb4 is stated because the client's default in the image is
# utf8mb3, which cannot hold every character the data carries. No credential passes through the
# scripts: the shell inside the container reads it from its own environment.
#   sh -c "$MARIADB_ROOT"                           no database selected (CREATE DATABASE)
#   sh -c "$MARIADB_ROOT \"\${MYSQL_DATABASE:?}\""   the container's database
# shellcheck disable=SC2016
MARIADB_ROOT='MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4'

# container_sql <container> [db]: run the SQL on stdin in that container, in its database when
# the second argument is "db".
container_sql() {
    if [ "${2:-}" = db ]; then
        docker exec -i "$1" sh -c "$MARIADB_ROOT \"\${MYSQL_DATABASE:?}\""
    else
        docker exec -i "$1" sh -c "$MARIADB_ROOT"
    fi
}
