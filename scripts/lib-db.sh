# shellcheck shell=bash
# Shared by the scripts that reach a database (reset-db.sh, migrate.sh): how to find this
# checkout's local PostgreSQL container, what to refuse first, and how to run psql inside a
# container. Sourced, never run. The caller sets REFUSE_AS to its own name.

refuse() { echo "x ${REFUSE_AS:-db}: $1; nothing was touched" >&2; exit 1; }

# The compose service of the database, here and on the OVH host
DB_SERVICE=postgres

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

# local_db_container: prints the id of this checkout's running postgres container, once healthy.
# Refuses a project with none running, and one created from another directory (a second
# checkout using this one's project name) or from deploy/ovh/compose.yaml, which is
# production's compose file and must never be reset or migrated by accident.
local_db_container() {
    require_local_engine
    local project cid
    project=$(docker compose config 2>/dev/null | sed -n 's/^name: //p' | head -1)
    [ -n "$project" ] || refuse "docker compose finds no project in $PWD"

    cid=$(docker compose ps -q --status running "$DB_SERVICE" 2>/dev/null | head -1)
    [ -n "$cid" ] || refuse "no running $DB_SERVICE container in the compose project '$project'; start it with docker compose up -d"

    label() { docker inspect --format "{{index .Config.Labels \"$1\"}}" "$cid"; }
    [ "$(label com.docker.compose.project.working_dir)" = "$PWD" ] \
        || refuse "the db container of '$project' was created from $(label com.docker.compose.project.working_dir), not from this checkout"
    case ",$(label com.docker.compose.project.config_files)," in
        *",$PWD/compose.yaml,"*) ;;
        *) refuse "the db container of '$project' was not created from this checkout's compose.yaml" ;;
    esac
    case "$(label com.docker.compose.project.config_files)" in
        */deploy/ovh/compose.yaml*) refuse "the db container of '$project' runs production's compose file" ;;
    esac

    wait_healthy "$cid" "the db container of '$project'"
    printf '%s\n' "$cid"
}

# psql as the image's superuser, the service's own role, over the container's socket, where
# the image trusts it: no password passes. Rows out as tab-separated lines with no header, and
# the first error stops it.
#   sh -c "$PSQL_ROOT -d postgres"                   no database of the service's (CREATE DATABASE)
#   sh -c "$PSQL_ROOT -d \"\${POSTGRES_DB:?}\""       the container's database
# shellcheck disable=SC2016
PSQL_ROOT='exec psql -X -q -A -t -v ON_ERROR_STOP=1 -F "$(printf "\t")" -U "${POSTGRES_USER:?}"'

# The client command, in that container's database ("db") or in postgres, which is none of the
# service's (CREATE DATABASE)
client_command() {
    if [ "${1:-}" = db ]; then printf '%s' "$PSQL_ROOT -d \"\${POSTGRES_DB:?}\""; else printf '%s' "$PSQL_ROOT -d postgres"; fi
}

# container_sql <container> [db]: run the SQL on stdin in that container, in its database when
# the second argument is "db".
container_sql() {
    docker exec -i "$1" sh -c "$(client_command "${2:-}")"
}

# The SQL that names the current database's schema, for information_schema
# shellcheck disable=SC2034 # used by the scripts that source this file
SCHEMA_SQL='current_schema()'

# use_db_target <local|container|ovh>: defines db_sql, which runs the SQL on stdin in that
# database, and sets `where` to name it in messages. The variables it reads are named after
# migrate.sh, which had them first:
#   local       this checkout's compose project, with local_db_container's refusals
#   container   the container MIGRATE_CONTAINER names, on the local engine (tests, other projects)
#   ovh         production's postgres container (OVH_DB_CONTAINER, hhbd-postgres-1) on OVH_HOST
#               over ssh; MIGRATE_SSH replaces the ssh command and OVH_SUDO the sudo, for tests
use_db_target() {
    case "$1" in
        local)
            # Global, not local: db_sql reads it each time it runs, after this has returned.
            db_cid=$(local_db_container) || exit 1
            db_sql() { container_sql "$db_cid" db; }
            where="$(docker inspect --format '{{.Name}}' "$db_cid" | sed 's|^/||') (local)"
            ;;
        container)
            require_local_engine
            [ -n "${MIGRATE_CONTAINER:-}" ] || refuse "MIGRATE_TARGET=container needs MIGRATE_CONTAINER"
            docker inspect "$MIGRATE_CONTAINER" >/dev/null 2>&1 || refuse "no container '$MIGRATE_CONTAINER' on the local engine"
            wait_healthy "$MIGRATE_CONTAINER" "the container $MIGRATE_CONTAINER"
            db_sql() { container_sql "$MIGRATE_CONTAINER" db; }
            where="$(docker inspect --format '{{.Name}}' "$MIGRATE_CONTAINER" | sed 's|^/||') (local)"
            ;;
        ovh)
            [ -n "${OVH_HOST:-}" ] || refuse "OVH_HOST is not set: it lives in gcloud-ovh-migrate's .env, which make loads"
            db_ovh_container=${OVH_DB_CONTAINER:-hhbd-$DB_SERVICE-1}
            # One static command over ssh, SQL on stdin: nothing from this side is quoted for the
            # far side, and no password is needed over the container's socket.
            db_sql() {
                # shellcheck disable=SC2086
                ${MIGRATE_SSH:-ssh -o BatchMode=yes} "${OVH_SSH_USER:-ubuntu}@$OVH_HOST" \
                    "${OVH_SUDO-sudo} docker exec -i $db_ovh_container sh -c '$(client_command db)'"
            }
            where="$db_ovh_container on $OVH_HOST"
            ;;
        *) refuse "unknown target '$1'; one of local, container, ovh" ;;
    esac
}
