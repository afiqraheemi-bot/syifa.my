#!/usr/bin/env bash
# Verify the production runner, deploy guardrails, and a fresh backup restore
# without changing the deployed application or the production database.

set -euo pipefail

checkout="${SYIFA_CHECKOUT:?SYIFA_CHECKOUT is required}"
expected_sha="${SYIFA_EXPECTED_MAIN_SHA:?SYIFA_EXPECTED_MAIN_SHA is required}"
drill_id="${SYIFA_DRILL_ID:?SYIFA_DRILL_ID is required}"
production_dir="${SYIFA_PRODUCTION_DIR:-/var/www/syifa}"
deploy_command="${SYIFA_DEPLOY_COMMAND:-/usr/local/bin/syifa-deploy}"
readiness_command="${SYIFA_READINESS_COMMAND:-/usr/local/bin/syifa-release-readiness}"

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

require_executable_file() {
    if [[ ! -f "$2" || ! -x "$2" ]]; then
        echo "Missing or non-executable required file: $1." >&2
        exit 1
    fi
}

require_directory 'workflow checkout repository' "$checkout/.git"
require_directory 'production repository' "$production_dir/.git"
require_executable_file 'reviewed readiness helper source' "$checkout/scripts/production/syifa-release-readiness"

if [[ ! -e "$deploy_command" ]]; then
    echo "Missing required file: production deploy command." >&2
    exit 1
fi

if ! sudo -n -l 2>/dev/null | grep -Fq "$deploy_command"; then
    echo "The runner has no non-interactive sudo rule for the production deploy command." >&2
    exit 1
fi

echo "Root-protected production deploy command and narrow sudo delegation verified."

if [[ ! -e "$readiness_command" ]]; then
    echo "Missing installed root readiness helper: $readiness_command." >&2
    exit 1
fi

if ! sudo -n -l 2>/dev/null | grep -Fq "$readiness_command"; then
    echo "The runner has no non-interactive sudo rule for the root readiness helper." >&2
    exit 1
fi

source_digest="$(sha256sum "$checkout/scripts/production/syifa-release-readiness" | cut -d ' ' -f 1)"
installed_digest="$(sudo -n "$readiness_command" --digest)"
if [[ "$source_digest" != "$installed_digest" ]]; then
    echo 'Installed root readiness helper does not match the reviewed repository source.' >&2
    exit 1
fi
echo "Reviewed root readiness helper and narrow sudo delegation verified."

tested_sha="$(git -C "$checkout" rev-parse HEAD)"
if [[ "$tested_sha" != "$expected_sha" ]]; then
    echo "Checked-out SHA does not match the workflow SHA." >&2
    exit 1
fi

deployed_sha="$(git -c safe.directory="$production_dir" -C "$production_dir" rev-parse HEAD)"
echo "Readiness drill source SHA: $tested_sha"
echo "Currently deployed SHA: $deployed_sha"

# The helper reads production credentials as root, retains the protected dump,
# restores only into its guarded disposable database, then removes that database.
sudo -n "$readiness_command" \
    --drill-id "$drill_id" \
    --expected-deployed-sha "$deployed_sha"

curl --fail --silent --show-error --max-time 15 https://syifa.my/operations/health >/dev/null
curl --fail --silent --show-error --max-time 15 https://syifa.my/operations/release
echo
echo "Production readiness drill passed without deploying or mutating production data."
