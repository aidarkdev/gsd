# GSD

Minimal PHP REST API with Slim, PHP-FPM, Nginx, and PostgreSQL.

## Ubuntu packages

Install these on Ubuntu 24.04:

```sh
sudo apt update
sudo apt install nginx php8.3-fpm php8.3-pgsql postgresql rsync
```

## PHP dependencies

This project uses a local Composer binary:

```sh
php composer.phar install
```

## PostgreSQL

Create the local database, schema, and first admin user:

```sh
bash scripts/setup-db.sh
```

The setup script reads `.env`, creates the PostgreSQL role/database, applies `database/schema.sql`, and ensures the admin user from `ADMIN_EMAIL`/`ADMIN_PASSWORD`. `ADMIN_PASSWORD` is required and cannot use the example value.

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

## Nginx

Deploy the current source tree to `/var/www/gsd`:

```sh
bash scripts/deploy-local.sh
```

The deploy command syncs source files and keeps `/var/www/gsd/.env` separate after the first deploy.

Copy `config/nginx/gsd.conf` to `/etc/nginx/sites-available/gsd`, enable it, then reload Nginx:

```sh
sudo cp config/nginx/gsd.conf /etc/nginx/sites-available/gsd
sudo ln -s /etc/nginx/sites-available/gsd /etc/nginx/sites-enabled/gsd
sudo nginx -t
sudo systemctl reload nginx
```

For local DNS, add this to `/etc/hosts`:

```text
127.0.0.1 gsd.local
```

## Checks

```sh
curl http://gsd.local/
curl http://gsd.local/api/health
curl http://gsd.local/api/missing
curl http://gsd.local/static/app.css
```

## Local Test

```sh
bash scripts/test.sh
```
