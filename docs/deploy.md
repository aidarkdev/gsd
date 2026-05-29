# Deployment

GSD deploys as a PHP-FPM application behind nginx with PostgreSQL as persistent data storage.

## Runtime Files

The deployed application directory is usually `/var/www/gsd`.

Runtime source and configuration:

- `public/`
- `src/`
- `templates/`
- `config/`
- `database/schema.sql`
- `scripts/`
- `composer.json`
- `composer.lock`
- `vendor/` or another Composer install result available to `public/index.php`

Persistent files and directories:

- `.env`
- `storage/attachments`
- `storage/logs`
- `storage/sessions`
- PostgreSQL data

Application deploys must not overwrite or delete persistent files.

## Local Deploy Script

`scripts/deploy-local.sh` syncs the current working tree to `/var/www/gsd` by default.

The script:

- creates the destination directory;
- runs `rsync -a --delete`;
- excludes `.env`, `.git`, local agent folders, Composer installer files, and logs;
- creates `.env` on first deploy from the local `.env` or `.env.example`;
- sets readable source permissions;
- creates writable `storage/attachments`, `storage/logs`, and `storage/sessions`.

Use a custom destination by passing it as the first argument:

```sh
bash scripts/deploy-local.sh /var/www/gsd
```

Do not use deploys to rotate secrets or reset runtime state. Edit `.env` and database state intentionally.

## Environment

Production should set:

```env
APP_DEBUG=false
APP_COOKIE_SECURE=true
APP_DEFAULT_LANG=en
```

Local HTTP development may use:

```env
APP_COOKIE_SECURE=false
APP_DEFAULT_LANG=en
```

Required database/admin values include:

- `DB_DSN`
- `DB_USER`
- `DB_PASSWORD`
- `ADMIN_EMAIL`
- `ADMIN_NAME`
- `ADMIN_PASSWORD`

`ADMIN_PASSWORD` must be set and must not use an example value.

## PostgreSQL Setup

Use:

```sh
bash scripts/setup-db.sh
```

The setup script reads `.env`, creates or updates the PostgreSQL role and database, applies `database/schema.sql`, and ensures the configured admin user.

Schema changes should be made deliberately. If a future change needs a migration separate from `database/schema.sql`, document the one-time deploy step here.

## nginx

The nginx site config lives at `config/nginx/gsd.conf`.

Current responsibilities:

- serve from `root /var/www/gsd/public`;
- route application requests through `/index.php`;
- execute only the exact `/index.php` PHP entrypoint;
- return `404` for other `.php` paths;
- set baseline security headers.

After nginx config changes:

```sh
sudo nginx -t
sudo systemctl reload nginx
```

PHP code changes usually require updating deployed files. Reload nginx only when nginx configuration changed.

## Composer

The application requires Composer dependencies from `composer.json` and `composer.lock`.

For a fresh checkout or target host, install the OS `composer` package, then:

```sh
composer install
```

Production deploys should use the locked dependency set from `composer.lock`.

## Checks After Deploy

Run the local test script before deploy when possible:

```sh
bash scripts/test.sh
```

Check HTTP behavior after deploy:

```sh
curl http://gsd.local/
curl http://gsd.local/api/health
curl http://gsd.local/api/missing
curl http://gsd.local/static/app.css
```

Check services and logs on the host using the system service names configured for that environment.
