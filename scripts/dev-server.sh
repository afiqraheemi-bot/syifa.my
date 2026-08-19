#!/usr/bin/env bash

set -u

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
server_host="${SYIFA_DEV_HOST:-0.0.0.0}"
server_port="${SYIFA_DEV_PORT:-8000}"
upload_max_filesize="${SYIFA_UPLOAD_MAX_FILESIZE:-10M}"
post_max_size="${SYIFA_POST_MAX_SIZE:-12M}"

cd "$project_dir"

stop_requested=0
trap 'stop_requested=1' INT TERM

while (( stop_requested == 0 )); do
    php \
        -d "upload_max_filesize=$upload_max_filesize" \
        -d "post_max_size=$post_max_size" \
        artisan serve --host="$server_host" --port="$server_port"
    exit_code=$?

    if (( stop_requested != 0 )); then
        break
    fi

    printf 'SYIFA development server stopped (exit %s). Restarting in 1 second...\n' "$exit_code" >&2
    sleep 1
done
