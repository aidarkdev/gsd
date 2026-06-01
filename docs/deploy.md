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

## Local Setup

For a new Ubuntu checkout, run from the repo root:

```sh
bash scripts/setup-local.sh
```

That script installs missing packages, configures PostgreSQL and nginx, deploys to `/var/www/gsd`, and verifies HTTP. Use `scripts/deploy-local.sh` alone when only the application files changed.

## Local Deploy Script

`scripts/deploy-local.sh` syncs the current working tree to `/var/www/gsd` by default.

The script:

- creates the destination directory;
- runs `rsync -a --delete`;
- uses the current user when the destination is writable, and falls back to `sudo` only when needed;
- excludes `.env`, `.git`, local agent folders, Composer installer files, and logs;
- creates `.env` on first deploy from the local `.env` or `.env.example`;
- sets readable source permissions;
- creates writable `storage/attachments`, `storage/logs`, and `storage/sessions`.

Use a custom destination by passing it as the first argument:

```sh
bash scripts/deploy-local.sh /var/www/gsd
```

For local development only, make the deploy directory writable once so later deploys do not need `sudo`:

```sh
sudo install -d -m 0755 -o "$USER" -g "$USER" /var/www/gsd
sudo install -d -m 0775 -o "$USER" -g www-data /var/www/gsd/storage/attachments /var/www/gsd/storage/logs /var/www/gsd/storage/sessions
```

Do not use deploys to rotate secrets or reset runtime state. Edit `.env` and database state intentionally.

## VPS Deploy

Use a dedicated deploy user for source files, and keep mutable runtime directories writable by PHP-FPM:

```sh
sudo adduser deploy
sudo install -d -m 0755 -o deploy -g deploy /var/www/gsd
sudo install -d -m 0775 -o deploy -g www-data /var/www/gsd/storage/attachments /var/www/gsd/storage/logs /var/www/gsd/storage/sessions
sudo install -m 0640 -o deploy -g www-data .env /var/www/gsd/.env
```

Deploy source files from a trusted checkout or CI host:

```sh
rsync -a --delete \
  --exclude /.env \
  --exclude /.git/ \
  --exclude /storage/attachments/*** \
  --exclude /storage/logs/*** \
  --exclude /storage/sessions/*** \
  ./ deploy@your-vps:/var/www/gsd/
```

Run Composer with the locked dependency set on the target host or deploy a prepared `vendor/` directory:

```sh
cd /var/www/gsd
composer install --no-dev --prefer-dist --no-interaction
```

Reload nginx only after nginx config changes. Reload PHP-FPM only when the server uses an opcache policy that requires it.

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

Habit tracking adds `habits` and `habit_entries`. Deploys that include this feature need the schema applied before the calendar workspace is used:

```sh
bash scripts/setup-db.sh
```

The application stores habit rules and habit entries only. Computed habit slots are not stored in PostgreSQL.

Habit rule versions are linked by `habits.habit_series_uid` for analytics across edits and resumes. Existing habit rows receive a generated per-row series uid when `database/schema.sql` is applied; historical versions created before this column existed are not merged automatically.

Inbox tasks reuse `task_instances` with nullable `start_date` and `end_date`. Deploys that include inbox support also need `database/schema.sql` applied so existing databases drop the old `NOT NULL` date requirement and receive the inbox index.

If deploying over an existing database created before tag support was removed, drop the unused tag tables deliberately after backup or verification:

```sql
DROP TABLE IF EXISTS note_tags, task_tags, tags CASCADE;
```

Fresh installs do not need this step because `database/schema.sql` no longer creates those tables.

## nginx

The nginx site config lives at `config/nginx/gsd.conf`.

Current responsibilities:

- serve from `root /var/www/gsd/public`;
- route application requests through `/index.php`;
- execute only the exact `/index.php` PHP entrypoint;
- return `404` for other `.php` paths;
- set baseline security headers;
- use the PHP-FPM socket from the distro `snippets/fastcgi-php.conf` (not a version pinned in this repo).

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
