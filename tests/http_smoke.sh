#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STORAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/notemod-http-test.XXXXXX")"
SERVER_LOG="$STORAGE_DIR/php-server.log"
PORT="${NOTEMOD_TEST_PORT:-18769}"
BASE_URL="http://127.0.0.1:$PORT"
SERVER_PID=""
READY=false

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$STORAGE_DIR"
}
trap cleanup EXIT

NM_STORAGE_ROOT="$STORAGE_DIR" php -S "127.0.0.1:$PORT" -t "$ROOT_DIR" >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!

for _ in {1..50}; do
  if curl --silent --output /dev/null "$BASE_URL/login.php" 2>/dev/null; then
    READY=true
    break
  fi
  sleep 0.1
done

if [[ "$READY" != true ]] || ! kill -0 "$SERVER_PID" 2>/dev/null; then
  cat "$SERVER_LOG" >&2
  exit 1
fi

assert_header() {
  local headers="$1"
  local expected="$2"
  if ! grep -Fqi "$expected" <<<"$headers"; then
    printf 'Missing header: %s\n%s\n' "$expected" "$headers" >&2
    exit 1
  fi
}

html_headers="$(curl --silent --show-error --dump-header - --output /dev/null "$BASE_URL/login.php")"
assert_header "$html_headers" 'X-Frame-Options: SAMEORIGIN'
assert_header "$html_headers" "Content-Security-Policy: default-src 'self'"
assert_header "$html_headers" 'Permissions-Policy:'

api_headers="$(curl --silent --show-error --dump-header - --output /dev/null "$BASE_URL/api/api.php?user=test")"
assert_header "$api_headers" 'X-Frame-Options: DENY'
assert_header "$api_headers" "Content-Security-Policy: default-src 'none'"
assert_header "$api_headers" 'Content-Type: application/json'

image_headers="$(curl --silent --show-error --dump-header - --output /dev/null "$BASE_URL/api/image_api.php?user=test&file=test.png")"
assert_header "$image_headers" '401 Unauthorized'
assert_header "$image_headers" 'X-Frame-Options: DENY'
assert_header "$image_headers" 'Cache-Control: private, no-store'

logout_headers="$(curl --silent --show-error --dump-header - --output /dev/null "$BASE_URL/logout.php")"
assert_header "$logout_headers" 'X-Frame-Options: SAMEORIGIN'
assert_header "$logout_headers" "Content-Security-Policy: default-src 'self'"

echo 'HTTP security-header smoke tests: PASS'
