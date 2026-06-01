#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LOCAL_HOST="${GSD_LOCAL_HOST:-gsd.local}"
LOCAL_IP="${GSD_LOCAL_IP:-127.0.0.1}"
DEPLOY_DIR="${GSD_DEPLOY_DIR:-/var/www/gsd}"
APT_PACKAGES=(nginx php-fpm php-pgsql postgresql rsync composer)

die() {
    echo "error: $*" >&2
    exit 1
}

need_sudo() {
    sudo -v || die "sudo is required for local setup"
}

install_apt() {
    local missing=()
    local pkg

    for pkg in "${APT_PACKAGES[@]}"; do
        dpkg -s "$pkg" >/dev/null 2>&1 || missing+=("$pkg")
    done

    if ((${#missing[@]} == 0)); then
        return
    fi

    sudo apt update
    sudo DEBIAN_FRONTEND=noninteractive apt install -y "${APT_PACKAGES[@]}"
}

ensure_env() {
    if [[ -f .env ]]; then
        if grep -qE '^ADMIN_PASSWORD=(|change-me-admin)$' .env; then
            die "set ADMIN_PASSWORD in .env, or remove .env and run setup again"
        fi
        return
    fi

    cp .env.example .env
    local db_pass admin_pass
    db_pass="$(openssl rand -hex 16)"
    admin_pass="$(openssl rand -hex 16)"
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${db_pass}/" .env
    sed -i "s/^ADMIN_PASSWORD=.*/ADMIN_PASSWORD=${admin_pass}/" .env
}

php_fpm_unit() {
    local unit path
    for path in /lib/systemd/system/php*-fpm.service; do
        [[ -e "$path" ]] || continue
        unit="$(basename "$path")"
        echo "$unit"
        return
    done

    die "php-fpm systemd unit not found; install the php-fpm package"
}

ensure_php_socket() {
    if [[ -S /run/php/php-fpm.sock ]]; then
        return
    fi

    local sock
    sock="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
    [[ -n "$sock" ]] || die "PHP-FPM socket missing under /run/php; is php-fpm running?"
}

ensure_hosts() {
    if grep -qE "[[:space:]]${LOCAL_HOST}([[:space:]]|$)" /etc/hosts; then
        return
    fi

    printf '%s %s\n' "$LOCAL_IP" "$LOCAL_HOST" | sudo tee -a /etc/hosts >/dev/null
}

install_nginx_site() {
    sudo cp config/nginx/gsd.conf /etc/nginx/sites-available/gsd
    sudo ln -sf /etc/nginx/sites-available/gsd /etc/nginx/sites-enabled/gsd

    if [[ -e /etc/nginx/sites-enabled/default ]]; then
        sudo rm /etc/nginx/sites-enabled/default
    fi

    sudo nginx -t
    sudo systemctl reload nginx
}

verify_site() {
    local health html_ct

    health="$(curl -sf "http://${LOCAL_HOST}/api/health")"
    [[ "$health" == *'"status":"ok"'* ]] || die "health check failed: ${health}"

    html_ct="$(
        curl -sfI "http://${LOCAL_HOST}/login" \
            | awk -F': ' 'tolower($1) == "content-type" { print $2; exit }' \
            | tr -d '\r'
    )"
    [[ "$html_ct" == text/html* ]] || die "login page has unexpected content-type: ${html_ct}"
}

main() {
    need_sudo
    install_apt
    ensure_env

    command -v composer >/dev/null || die "composer not found after apt install"
    composer install --no-interaction --no-progress

    local fpm_unit
    fpm_unit="$(php_fpm_unit)"
    sudo systemctl enable --now postgresql nginx "$fpm_unit"
    ensure_php_socket

    bash scripts/setup-db.sh
    bash scripts/deploy-local.sh "$DEPLOY_DIR"
    ensure_hosts
    install_nginx_site
    verify_site
    bash scripts/test.sh

    local admin_email
    admin_email="$(grep '^ADMIN_EMAIL=' .env | cut -d= -f2-)"

    echo
    echo "Local site is ready."
    echo "  http://${LOCAL_IP}/login"
    echo "  http://${LOCAL_HOST}/login"
    echo "Admin email: ${admin_email}"
    echo "Admin password: see ADMIN_PASSWORD in ${ROOT_DIR}/.env"
}

main "$@"
