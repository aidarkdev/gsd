#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_SESSION_PATH="$ROOT_DIR/storage/sessions"

while IFS= read -r file; do
    php -l "$file" >/dev/null
done < <(find "$ROOT_DIR" \
    -path "$ROOT_DIR/vendor" -prune -o \
    -path "$ROOT_DIR/.git" -prune -o \
    -name '*.php' -print)

env APP_SESSION_PATH="$TEST_SESSION_PATH" REQUEST_METHOD=GET REQUEST_URI=/ SCRIPT_NAME=/index.php SERVER_NAME=localhost SERVER_PORT=80 \
    php "$ROOT_DIR/public/index.php" >/dev/null

env APP_SESSION_PATH="$TEST_SESSION_PATH" REQUEST_METHOD=GET REQUEST_URI=/api/missing SCRIPT_NAME=/index.php SERVER_NAME=localhost SERVER_PORT=80 \
    php "$ROOT_DIR/public/index.php" >/dev/null

php "$ROOT_DIR/scripts/security-test.php"
php "$ROOT_DIR/scripts/domain-test.php"

echo "Tests passed"
