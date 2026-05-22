#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${1:-$ROOT_DIR/.env}"
TMP_DIR="$(mktemp -d)"

cleanup() {
    rm -rf "$TMP_DIR"
}
trap cleanup EXIT

cp "$ROOT_DIR/scripts/setup-db.php" "$TMP_DIR/setup-db.php"
cp "$ROOT_DIR/database/schema.sql" "$TMP_DIR/schema.sql"
cp "$ENV_FILE" "$TMP_DIR/.env"
chmod -R a+rX "$TMP_DIR"

sudo -u postgres php "$TMP_DIR/setup-db.php" "$TMP_DIR/.env" "$TMP_DIR/schema.sql"
