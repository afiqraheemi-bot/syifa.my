#!/usr/bin/env bash

set -u

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
server_host="${SYIFA_DEV_HOST:-0.0.0.0}"
server_port="${SYIFA_DEV_PORT:-8000}"

cd "$project_dir"

stop_requested=0
trap 'stop_requested=1' INT TERM

while (( stop_requested == 0 )); do
    php artisan serve --host="$server_host" --port="$server_port"
    exit_code=$?

    if (( stop_requested != 0 )); then
        break
    fi

    printf 'SYIFA development server stopped (exit %s). Restarting in 1 second...\n' "$exit_code" >&2
    sleep 1
done
