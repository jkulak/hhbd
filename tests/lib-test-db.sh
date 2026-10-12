# shellcheck shell=bash
# Shared by the tests that read or write the database (#234): sql runs SQL in this compose
# project's database, MariaDB's or PostgreSQL's as DB_ENGINE says (mariadb by default), as the
# engine's superuser inside its container, rows out as tab-separated lines with no header and
# NULL printed as NULL on both. Sourced, never run.
#
# What the engines write differently is a fragment from here, so a test's SQL reads the same
# for both: json_get for a JSON field, agg for GROUP_CONCAT / string_agg. A test that checks
# what one engine alone has (MariaDB's storage engines, PostgreSQL's collations) asks is_pg.

DB_ENGINE=${DB_ENGINE:-mariadb}
case "$DB_ENGINE" in
    mariadb | postgresql) ;;
    *) echo "x DB_ENGINE is mariadb or postgresql, not '$DB_ENGINE'" >&2; exit 2 ;;
esac

is_pg() { [ "$DB_ENGINE" = postgresql ]; }

# sql <statement>: in this project's database
sql() {
    if is_pg; then
        # shellcheck disable=SC2016
        docker compose exec -T postgres sh -c 'exec psql -X -q -A -t -v ON_ERROR_STOP=1 -P null=NULL -F "$(printf "\t")" -U "${POSTGRES_USER:?}" -d "${POSTGRES_DB:?}" -c "$1"' -- "$1"
    else
        # shellcheck disable=SC2016
        docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}" -e "$1"' -- "$1"
    fi
}

# sql_in: the SQL on stdin, as one session
sql_in() {
    if is_pg; then
        # shellcheck disable=SC2016
        docker compose exec -T postgres sh -c 'exec psql -X -q -A -t -v ON_ERROR_STOP=1 -P null=NULL -F "$(printf "\t")" -U "${POSTGRES_USER:?}" -d "${POSTGRES_DB:?}"'
    else
        # shellcheck disable=SC2016
        docker compose exec -T db sh -c 'MYSQL_PWD="${MYSQL_ROOT_PASSWORD:?}" exec mariadb -uroot -N -B --default-character-set=utf8mb4 "${MYSQL_DATABASE:?}"'
    fi
}

# json_get <column> <key>: the text of a top-level field of a JSON column, NULL when absent
json_get() {
    if is_pg; then printf "(%s->>'%s')" "$1" "$2"; else printf "JSON_VALUE(%s, '\$.%s')" "$1" "$2"; fi
}

# agg <expression> <order by> [separator]: the expression's values joined, in that order, ","
# by default
agg() {
    local sep=${3:-,}
    if is_pg; then
        printf "string_agg(CAST(%s AS text), '%s' ORDER BY %s)" "$1" "$sep" "$2"
    else
        printf "GROUP_CONCAT(%s ORDER BY %s SEPARATOR '%s')" "$1" "$2" "$sep"
    fi
}
