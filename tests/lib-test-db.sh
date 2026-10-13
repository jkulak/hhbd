# shellcheck shell=bash
# Shared by the tests that read or write the database (#234): sql runs SQL in this compose
# project's PostgreSQL, as the image's superuser inside its container, rows out as tab-separated
# lines with no header and NULL printed as NULL. Sourced, never run.
#
# A few fragments shorten the tests' SQL: json_get for a JSON field, json_len for a JSON array's
# length, agg for string_agg, txt for a number as text, SCHEMA_SQL for information_schema's
# table_schema.

# sql <statement>: in this project's database
sql() {
    # shellcheck disable=SC2016
    docker compose exec -T postgres sh -c 'exec psql -X -q -A -t -v ON_ERROR_STOP=1 -P null=NULL -F "$(printf "\t")" -U "${POSTGRES_USER:?}" -d "${POSTGRES_DB:?}" -c "$1"' -- "$1"
}

# sql_in: the SQL on stdin, as one session
sql_in() {
    # shellcheck disable=SC2016
    docker compose exec -T postgres sh -c 'exec psql -X -q -A -t -v ON_ERROR_STOP=1 -P null=NULL -F "$(printf "\t")" -U "${POSTGRES_USER:?}" -d "${POSTGRES_DB:?}"'
}

# json_get <column> <key>: the text of a top-level field of a JSON column, NULL when absent
json_get() {
    printf "(%s->>'%s')" "$1" "$2"
}

# agg <expression> <order by> [separator]: the expression's values joined, in that order, ","
# by default
agg() {
    printf "string_agg(CAST(%s AS text), '%s' ORDER BY %s)" "$1" "${3:-,}" "$2"
}

# json_len <column> <key>: how many elements a top-level array field of a JSON column holds
json_len() {
    printf "json_array_length(%s->'%s')" "$1" "$2"
}

# txt <expression>: the expression as text, for a COALESCE or a CASE that gives a number a
# text stand-in, such as '-' for NULL: every branch of one has one type
txt() {
    printf 'CAST(%s AS text)' "$1"
}

# The schema information_schema's table_schema names this database by
# shellcheck disable=SC2034 # read by the tests that source this file
SCHEMA_SQL='current_schema()'
