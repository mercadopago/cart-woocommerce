#!/bin/bash
set -euo pipefail

SECRET_FILE="${1:-./secrets/mlb.env}"
REQUIRED_KEYS=(
    WP_ADMIN_USER
    WP_ADMIN_PASSWORD
    WP_ADMIN_EMAIL
    MP_ACCESS_TOKEN_TEST
    MP_PUBLIC_KEY_TEST
)

fail() {
    printf '[e2e-secrets] ERROR: %s\n' "$1" >&2
    exit 1
}

case "$SECRET_FILE" in
    -*|*$'\n'*|*$'\r'*) fail 'secret file path is invalid.' ;;
esac

file_mode() {
    if stat -c %a "$1" >/dev/null 2>&1; then
        stat -c %a "$1"
    else
        stat -f %Lp "$1"
    fi
}

file_owner() {
    if stat -c %U "$1" >/dev/null 2>&1; then
        stat -c %U "$1"
    else
        stat -f %Su "$1"
    fi
}

[ -f "$SECRET_FILE" ] && [ ! -L "$SECRET_FILE" ] || fail 'expected a regular, non-symlink secret file.'
[ "$(file_mode "$SECRET_FILE")" = '600' ] || fail 'secret file mode must be 0600.'
[ "$(file_mode "$(dirname "$SECRET_FILE")")" = '700' ] || fail 'secret directory mode must be 0700.'
[ "$(file_owner "$SECRET_FILE")" = "$(id -un)" ] || fail 'secret file must belong to the current user.'

if LC_ALL=C grep -q $'\r' "$SECRET_FILE"; then
    fail 'secret file must use Unix line endings.'
fi

if ! awk '
    /^[[:space:]]*($|#)/ { next }
    /^(WP_ADMIN_USER|WP_ADMIN_PASSWORD|WP_ADMIN_EMAIL|MP_ACCESS_TOKEN_TEST|MP_PUBLIC_KEY_TEST)=/ { next }
    { invalid=1 }
    END { exit invalid ? 1 : 0 }
' "$SECRET_FILE"; then
    fail 'secret file contains an unsupported or malformed key.'
fi

for key in "${REQUIRED_KEYS[@]}"; do
    if ! awk -v expected="$key" '
        function normalized(value) {
            if ((value ~ /^".*"$/) || (value ~ /^\047.*\047$/)) {
                return substr(value, 2, length(value) - 2)
            }
            return value
        }
        BEGIN { count=0; valid=0 }
        index($0, expected "=") == 1 {
            count++
            value=normalized(substr($0, length(expected) + 2))
            if (length(value) > 0) valid=1
        }
        END { exit !(count == 1 && valid == 1) }
    ' "$SECRET_FILE"; then
        fail "$key must appear exactly once with a non-empty value."
    fi
done

if ! awk '
    function normalized(value) {
        if ((value ~ /^".*"$/) || (value ~ /^\047.*\047$/)) {
            return substr(value, 2, length(value) - 2)
        }
        return value
    }
    index($0, "WP_ADMIN_USER=") == 1 {
        found=1
        value=normalized(substr($0, 15))
        valid=(value != "admin" && length(value) > 0)
    }
    END { exit !(found && valid) }
' "$SECRET_FILE"; then
    fail 'WP_ADMIN_USER must be non-default.'
fi

if ! awk '
    function normalized(value) {
        if ((value ~ /^".*"$/) || (value ~ /^\047.*\047$/)) {
            return substr(value, 2, length(value) - 2)
        }
        return value
    }
    index($0, "WP_ADMIN_PASSWORD=") == 1 {
        found=1
        value=normalized(substr($0, 19))
        valid=(length(value) >= 20)
    }
    END { exit !(found && valid) }
' "$SECRET_FILE"; then
    fail 'WP_ADMIN_PASSWORD must contain at least 20 characters.'
fi

printf '[e2e-secrets] Valid: permissions and required keys are correct; values were not displayed.\n'
