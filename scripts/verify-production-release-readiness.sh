#!/usr/bin/env bash
# Verify the production runner, deploy guardrails, and a fresh backup restore
# without changing the deployed application or the production database.

set -euo pipefail

checkout="${SYIFA_CHECKOUT:?SYIFA_CHECKOUT is required}"
expected_sha="${SYIFA_EXPECTED_MAIN_SHA:?SYIFA_EXPECTED_MAIN_SHA is required}"
drill_id="${SYIFA_DRILL_ID:?SYIFA_DRILL_ID is required}"
production_dir="${SYIFA_PRODUCTION_DIR:-/var/www/syifa}"
production_env="${SYIFA_PRODUCTION_ENV:-$production_dir/.env}"
backup_dir="${SYIFA_BACKUP_DIR:-/var/backups/syifa}"
deploy_command="${SYIFA_DEPLOY_COMMAND:-/usr/local/bin/syifa-deploy}"

if [[ ! "$backup_dir" =~ /syifa_readiness_${drill_id}$ ]]; then
    echo "SYIFA_BACKUP_DIR must end with the run-scoped syifa_readiness_<drill-id> directory." >&2
    exit 1
fi

if [[ ! "$drill_id" =~ ^[0-9]+$ ]]; then
    echo "SYIFA_DRILL_ID must contain digits only." >&2
    exit 1
fi

require_directory() {
    if [[ ! -d "$2" ]]; then
        echo "Missing required directory: $1." >&2
        exit 1
    fi
}

require_readable_file() {
    if [[ ! -f "$2" || ! -r "$2" ]]; then
        echo "Missing or unreadable required file: $1." >&2
        exit 1
    fi
}

require_executable_file() {
    if [[ ! -f "$2" || ! -x "$2" ]]; then
        echo "Missing or non-executable required file: $1." >&2
        exit 1
    fi
}

require_directory 'workflow checkout repository' "$checkout/.git"
require_directory 'production repository' "$production_dir/.git"
require_readable_file 'production environment' "$production_env"
require_executable_file 'production deploy command' "$deploy_command"
require_executable_file 'backup command' "$checkout/scripts/backup-database.sh"
require_executable_file 'restore verifier' "$checkout/scripts/verify-backup-restore.sh"

mkdir -p "$backup_dir"
chmod 700 "$backup_dir"
cleanup_backup() {
    find "$backup_dir" -maxdepth 1 -type f -name 'syifa_*.dump' -delete
    rmdir "$backup_dir" 2>/dev/null || true
}
trap cleanup_backup EXIT

tested_sha="$(git -C "$checkout" rev-parse HEAD)"
if [[ "$tested_sha" != "$expected_sha" ]]; then
    echo "Checked-out SHA does not match the workflow SHA." >&2
    exit 1
fi

deployed_sha="$(git -c safe.directory="$production_dir" -C "$production_dir" rev-parse HEAD)"
echo "Readiness drill source SHA: $tested_sha"
echo "Currently deployed SHA: $deployed_sha"

bash -n "$deploy_command"
grep -q 'EXPECTED_COMMIT' "$deploy_command"
grep -Eiq 'rollback|previous|restore' "$deploy_command"
echo "Deploy command syntax and exact-SHA/rollback guard markers verified."

SYIFA_ENV_FILE="$production_env" \
SYIFA_BACKUP_DIR="$backup_dir" \
SYIFA_BACKUP_RETENTION_DAYS=14 \
    "$checkout/scripts/backup-database.sh"

shopt -s nullglob
dumps=("$backup_dir"/syifa_*.dump)
if (( ${#dumps[@]} == 0 )); then
    echo "No production-format backup was created." >&2
    exit 1
fi
latest_dump="$(ls -1t -- "${dumps[@]}" | head -n 1)"
test -s "$latest_dump"
echo "Fresh backup size: $(stat -c '%s' "$latest_dump") bytes"

declare -A database_env=()
while IFS='=' read -r key value; do
    value="${value%$'\r'}"
    if [[ "$value" =~ ^\"(.*)\"$ || "$value" =~ ^\'(.*)\'$ ]]; then
        value="${BASH_REMATCH[1]}"
    fi
    database_env["$key"]="$value"
done < <(grep -E '^DB_(HOST|PORT|USERNAME|PASSWORD|SSLMODE)=' "$production_env")

db_user="${database_env[DB_USERNAME]:-}"
if [[ -z "$db_user" ]]; then
    echo "DB_USERNAME is missing from the production environment." >&2
    exit 1
fi

SYIFA_ALLOW_RESTORE_DRILL=1 \
DB_HOST="${database_env[DB_HOST]:-127.0.0.1}" \
DB_PORT="${database_env[DB_PORT]:-5432}" \
DB_USERNAME="$db_user" \
DB_PASSWORD="${database_env[DB_PASSWORD]:-}" \
DB_SSLMODE="${database_env[DB_SSLMODE]:-prefer}" \
    "$checkout/scripts/verify-backup-restore.sh" \
    "$latest_dump" "syifa_restore_drill_release_${drill_id}"

curl --fail --silent --show-error --max-time 15 https://syifa.my/operations/health >/dev/null
curl --fail --silent --show-error --max-time 15 https://syifa.my/operations/release
echo
echo "Production readiness drill passed without deploying or mutating production data."
