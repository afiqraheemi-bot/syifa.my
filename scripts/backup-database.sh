#!/usr/bin/env bash
#
# Full PostgreSQL backup for the SYIFA.my production database.
#
# Reads DB_* connection settings from the project's .env (or the process
# environment, which takes precedence — useful for cron where .env parsing
# is skipped by exporting the same variables in the crontab/systemd unit).
#
# Usage:
#   scripts/backup-database.sh
#
# Env overrides:
#   SYIFA_BACKUP_DIR              Where dumps are written (default: /var/backups/syifa)
#   SYIFA_BACKUP_RETENTION_DAYS   Delete dumps older than this many days (default: 14)

set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$project_dir/.env"

if [[ -f "$env_file" ]]; then
    # Only pull DB_* lines — never eval the whole .env (it may contain
    # values with characters unsafe to source directly).
    while IFS='=' read -r key value; do
        [[ -z "${!key:-}" ]] && export "$key=$value"
    done < <(grep -E '^DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD|SSLMODE)=' "$env_file")
fi

db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-5432}"
db_name="${DB_DATABASE:?DB_DATABASE is not set (check .env or the environment)}"
db_user="${DB_USERNAME:?DB_USERNAME is not set (check .env or the environment)}"
db_password="${DB_PASSWORD:-}"
db_sslmode="${DB_SSLMODE:-prefer}"

backup_dir="${SYIFA_BACKUP_DIR:-/var/backups/syifa}"
retention_days="${SYIFA_BACKUP_RETENTION_DAYS:-14}"
timestamp="$(date -u +%Y-%m-%dT%H%M%SZ)"
dump_file="$backup_dir/syifa_${db_name}_${timestamp}.dump"

mkdir -p "$backup_dir"
chmod 700 "$backup_dir"

echo "[backup-database] Dumping '$db_name' from $db_host:$db_port to $dump_file"

# Custom format (-Fc): compressed, and restorable with pg_restore --jobs for
# parallel restore and --schema/--table for a partial restore if only one
# table needs recovering — plain SQL dumps do not support either.
PGPASSWORD="$db_password" PGSSLMODE="$db_sslmode" pg_dump \
    --host="$db_host" \
    --port="$db_port" \
    --username="$db_user" \
    --dbname="$db_name" \
    --format=custom \
    --no-owner \
    --no-privileges \
    --file="$dump_file"

chmod 600 "$dump_file"

if [[ ! -s "$dump_file" ]]; then
    echo "[backup-database] ERROR: dump file is empty — treating as a failed backup." >&2
    rm -f "$dump_file"
    exit 1
fi

dump_size="$(du -h "$dump_file" | cut -f1)"
echo "[backup-database] OK — wrote $dump_size to $dump_file"

if (( retention_days > 0 )); then
    deleted_count=0
    while IFS= read -r -d '' old_file; do
        rm -f "$old_file"
        deleted_count=$((deleted_count + 1))
    done < <(find "$backup_dir" -maxdepth 1 -name 'syifa_*.dump' -mtime "+$retention_days" -print0)
    if (( deleted_count > 0 )); then
        echo "[backup-database] Pruned $deleted_count backup(s) older than $retention_days day(s)."
    fi
fi

echo "[backup-database] Restore with:"
echo "  pg_restore --host=<host> --username=<user> --dbname=<target-db> --clean --if-exists $dump_file"
