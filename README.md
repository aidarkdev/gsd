# GSD

Minimal PHP REST API with Slim, PHP-FPM, Nginx, and PostgreSQL.

## Local setup (Ubuntu)

From the repo root:

```sh
bash scripts/setup-local.sh
```

The script installs system packages when needed, creates `.env` on first run, runs Composer, sets up PostgreSQL, deploys to `/var/www/gsd`, configures nginx and `/etc/hosts`, then verifies HTTP before it finishes.

Open **http://127.0.0.1/login** (or **http://gsd.local/login**). Sign-in uses `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.

Re-run after code changes:

```sh
bash scripts/deploy-local.sh
```

For local development only, make the deploy directory writable once if `sudo` is unavailable during deploy:

```sh
sudo install -d -m 0755 -o "$USER" -g "$USER" /var/www/gsd
sudo install -d -m 0775 -o "$USER" -g www-data /var/www/gsd/storage/attachments /var/www/gsd/storage/logs /var/www/gsd/storage/sessions
```

For VPS deployment, see `docs/deploy.md`; do not use local-dev ownership rules for production.

Reload nginx only after nginx config changes:

```sh
sudo systemctl reload nginx
```

Fedora (manual): `sudo dnf install nginx php-fpm php-pgsql postgresql-server rsync composer php-cli`, then run the same scripts except `setup-local.sh` apt steps.

## Security Defaults

The app uses centralized middleware for session hardening, CSRF checks, login throttling, security headers, and default-deny access policy. New non-public routes require login by default; `/admin/*` and `/api/admin/*` require the `admin` role.

For local HTTP keep:

```env
APP_COOKIE_SECURE=false
```

For HTTPS production use:

```env
APP_COOKIE_SECURE=true
APP_DEBUG=false
```

## Local Test

```sh
bash scripts/test.sh
```
