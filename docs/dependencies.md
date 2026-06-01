# Dependency Policy

GSD should keep runtime dependencies small and explicit.

## Runtime

Current runtime dependencies:

- PHP 8.3 or newer (distribution default via `php-fpm`; 8.3 on Ubuntu 24.04, 8.5 on Ubuntu 26.04).
- Slim 4 and Slim PSR-7 through Composer.
- PDO with the PostgreSQL driver.
- PostgreSQL.
- nginx and PHP-FPM.
- Browser-native HTML, CSS, ES modules, and optional browser parts.

Prefer PHP standard library, Slim, PDO, PostgreSQL, nginx, and browser APIs before adding a new package.

## Composer

`composer.json` is the PHP dependency manifest and PSR-4 autoload map.

Current expected shape:

- `slim/slim` and `slim/psr7` are application runtime dependencies.
- `App\\` maps to `src/`.
- New runtime packages require a concrete reason.

Do not add a Composer package for small helpers that can be written clearly in local code.

## System Packages

System-level tools belong outside Composer:

- nginx
- php-fpm
- php-pgsql
- postgresql
- rsync

Document new system package requirements in deployment docs and README-facing setup instructions when they become part of the runtime.

## Adding Dependencies

Before adding a dependency, check these options in order:

1. Can PHP or the browser solve it directly?
2. Can Slim, PDO, PostgreSQL, or nginx solve it within their existing role?
3. Can existing project code be extended with a small, readable change?
4. Is the package development-only, or will production need it?

If a dependency is still necessary, document:

- why the existing stack is insufficient;
- where the dependency is used;
- whether it is runtime or development-only;
- what deploy or server package changes it requires;
- how it will be tested and maintained.

Default answer for convenience libraries is no.

## Browser Dependencies

The current frontend does not require browser packages, bundlers, or a package manager. Static assets live in `public/static/`, `public/engine/`, and `public/parts/`.

Do not introduce a browser dependency manager unless a feature has a concrete need that cannot be handled by server-rendered HTML, CSS, browser parts, and small plain JavaScript.

## Deployment

Deployments must include Composer-installed runtime code or run `composer install` on the target host according to the chosen deploy process.

Do not deploy local-only caches or unrelated development artifacts. Do preserve `.env`, `storage/attachments`, `storage/logs`, `storage/sessions`, and PostgreSQL data.
