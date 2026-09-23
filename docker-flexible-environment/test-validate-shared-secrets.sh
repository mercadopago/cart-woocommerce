#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALIDATOR="$ROOT_DIR/deploy/validate-shared-secrets.sh"
FIXTURES="$ROOT_DIR/tests/fixtures"
CONTRACTS="$ROOT_DIR/deploy/examples/credentials"
TMP_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

chmod 700 "$TMP_DIR"

for site in mla mlb mlc mlm mco mlu mpe; do
    contract="$CONTRACTS/$site.env.example"
    [ -f "$contract" ] || {
        printf 'missing credential contract for %s\n' "$site" >&2
        exit 1
    }
    for key in WP_ADMIN_USER WP_ADMIN_PASSWORD WP_ADMIN_EMAIL MP_ACCESS_TOKEN_TEST MP_PUBLIC_KEY_TEST; do
        grep -q "^${key}=$" "$contract" || {
            printf 'invalid credential contract for %s\n' "$site" >&2
            exit 1
        }
    done
done

cp "$FIXTURES/valid-shared-secrets.env.example" "$TMP_DIR/mlb.env"
chmod 600 "$TMP_DIR/mlb.env"
"$VALIDATOR" "$TMP_DIR/mlb.env" >/dev/null

cp "$FIXTURES/short-password.env.example" "$TMP_DIR/mlb.env"
chmod 600 "$TMP_DIR/mlb.env"
if "$VALIDATOR" "$TMP_DIR/mlb.env" >/dev/null 2>&1; then
    printf 'short password fixture was accepted\n' >&2
    exit 1
fi

cp "$FIXTURES/quoted-policy-bypass.env.example" "$TMP_DIR/mlb.env"
chmod 600 "$TMP_DIR/mlb.env"
if "$VALIDATOR" "$TMP_DIR/mlb.env" >/dev/null 2>&1; then
    printf 'quoted policy bypass fixture was accepted\n' >&2
    exit 1
fi

printf 'validate-shared-secrets: ok\n'
