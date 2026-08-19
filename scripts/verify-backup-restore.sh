#!/usr/bin/env bash
# Restore a SYIFA PostgreSQL custom-format dump into an explicitly disposable
# database and verify that the restored schema is readable.

set -euo pipefail

dump_file="${1:-}"
target_database="${2:-}"

if [[ "${SYIFA_ALLOW_RESTORE_DRILL:-}" != "1" ]]; then
    echo "Set SYIFA_ALLOW_RESTORE_DRILL=1 to acknowledge creation and replacement of a disposable restore database." >&2
    exit 1
fi

if [[ ! -f "$dump_file" || ! -s "$dump_file" ]]; then
    echo "A non-empty PostgreSQL custom-format dump is required." >&2
    exit 1
fi

if [[ ! "$target_database" =~ ^syifa_restore_drill_[a-zA-Z0-9_]+$ ]]; then
    echo "Target database must start with syifa_restore_drill_ and contain only letters, numbers, or underscores." >&2
    exit 1
fi

db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-5432}"
db_user="${DB_USERNAME:?DB_USERNAME is required}"
db_password="${DB_PASSWORD:-}"
db_sslmode="${DB_SSLMODE:-prefer}"

export PGPASSWORD="$db_password"
export PGSSLMODE="$db_sslmode"

dropdb --if-exists --force --host="$db_host" --port="$db_port" --username="$db_user" "$target_database"
createdb --host="$db_host" --port="$db_port" --username="$db_user" "$target_database"

cleanup() {
    dropdb --if-exists --force --host="$db_host" --port="$db_port" --username="$db_user" "$target_database"
}
trap cleanup EXIT

pg_restore \
    --host="$db_host" \
    --port="$db_port" \
    --username="$db_user" \
    --dbname="$target_database" \
    --no-owner \
    --no-privileges \
    --exit-on-error \
    "$dump_file"

table_count=$(psql \
    --host="$db_host" \
    --port="$db_port" \
    --username="$db_user" \
    --dbname="$target_database" \
    --tuples-only \
    --no-align \
    --command="SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")

if [[ ! "$table_count" =~ ^[0-9]+$ ]] || (( table_count < 1 )); then
    echo "Restore verification failed: no public tables were restored." >&2
    exit 1
fi

echo "Restore drill passed: $table_count public tables restored into disposable database '$target_database'."
