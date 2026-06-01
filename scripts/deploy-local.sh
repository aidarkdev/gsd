#!/usr/bin/env bash
set -euo pipefail

SOURCE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
DEST_DIR="${1:-/var/www/gsd}"
DEPLOY_OWNER="${DEPLOY_OWNER:-$(id -un)}"
PHP_GROUP="${PHP_GROUP:-www-data}"

if ! command -v rsync >/dev/null 2>&1; then
    echo "rsync is required. Install it with: sudo apt install rsync" >&2
    exit 1
fi

if [ -d "$DEST_DIR" ]; then
    DEST_PARENT="$DEST_DIR"
else
    DEST_PARENT="$(dirname -- "$DEST_DIR")"
fi

if [ -w "$DEST_PARENT" ]; then
    SUDO=()
else
    if ! command -v sudo >/dev/null 2>&1; then
        echo "$DEST_DIR is not writable and sudo is not available." >&2
        exit 1
    fi

    SUDO=(sudo)
fi

"${SUDO[@]}" install -d -m 0755 "$DEST_DIR"

"${SUDO[@]}" rsync -a --delete \
    --exclude '/.env' \
    --exclude '/.git/' \
    --exclude '/.agents/' \
    --exclude '/.codex/' \
    --exclude '/composer.phar' \
    --exclude '/composer-setup.php' \
    --exclude '/storage/attachments/***' \
    --exclude '/storage/logs/***' \
    --exclude '/storage/sessions/***' \
    --exclude '/*.log' \
    "$SOURCE_DIR"/ "$DEST_DIR"/

if [ ! -f "$DEST_DIR/.env" ]; then
    if [ -f "$SOURCE_DIR/.env" ]; then
        if [ "${#SUDO[@]}" -eq 0 ]; then
            install -m 0640 "$SOURCE_DIR/.env" "$DEST_DIR/.env"
        else
            sudo install -m 0640 -o "$DEPLOY_OWNER" -g "$PHP_GROUP" "$SOURCE_DIR/.env" "$DEST_DIR/.env"
        fi
    else
        if [ "${#SUDO[@]}" -eq 0 ]; then
            install -m 0640 "$SOURCE_DIR/.env.example" "$DEST_DIR/.env"
        else
            sudo install -m 0640 -o "$DEPLOY_OWNER" -g "$PHP_GROUP" "$SOURCE_DIR/.env.example" "$DEST_DIR/.env"
        fi
    fi
fi

"${SUDO[@]}" find "$DEST_DIR" \( -path "$DEST_DIR/storage/attachments" -o -path "$DEST_DIR/storage/logs" -o -path "$DEST_DIR/storage/sessions" \) -prune -o -type d -exec chmod 0755 {} +
"${SUDO[@]}" find "$DEST_DIR" \( -path "$DEST_DIR/storage/attachments" -o -path "$DEST_DIR/storage/logs" -o -path "$DEST_DIR/storage/sessions" \) -prune -o -type f ! -name '.env' -exec chmod 0644 {} +
"${SUDO[@]}" chmod 0640 "$DEST_DIR/.env"

if [ "${#SUDO[@]}" -eq 0 ]; then
    install -d -m 0775 "$DEST_DIR/storage/attachments"
    install -d -m 0775 "$DEST_DIR/storage/logs"
    install -d -m 0775 "$DEST_DIR/storage/sessions"
else
    sudo install -d -m 0775 -o "$DEPLOY_OWNER" -g "$PHP_GROUP" "$DEST_DIR/storage/attachments"
    sudo install -d -m 0775 -o "$DEPLOY_OWNER" -g "$PHP_GROUP" "$DEST_DIR/storage/logs"
    sudo install -d -m 0775 -o "$DEPLOY_OWNER" -g "$PHP_GROUP" "$DEST_DIR/storage/sessions"
fi

echo "Deployed $SOURCE_DIR to $DEST_DIR"
