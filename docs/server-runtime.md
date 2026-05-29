# Server Runtime

This document describes how GSD requests are served in production and how nginx, PHP-FPM, Slim, middleware, controllers, templates, and repositories split responsibility.

## Runtime Boundaries

- nginx is the public HTTP entrypoint.
- PHP-FPM executes `public/index.php`.
- Slim owns application routing after nginx forwards a request to `index.php`.
- nginx serves public files from `public/`, including `/static/*`, `/engine/*`, and `/parts/*`.
- PostgreSQL is the application database.
- Runtime logs and sessions live under `storage/`.

The default UI is rendered on the server through PHP templates. Bounded interactive pages may use PHP-backed browser parts: PHP emits a page shell, baked JSON, mount metadata, and static browser modules render the interactive region.

## Public URL Ownership

nginx owns direct public file delivery under the configured document root:

- `/static/app.css` and future files under `public/static/`.
- `/engine/core.js`, `/engine/bootstrap.js`, and `/engine/http.js` when a page uses browser parts.
- `/parts/<name>/index.js`, `/parts/<name>/template.js`, and `/parts/<name>/handlers.js` for public browser parts.
- `/index.php` as the only PHP script that should be executed.

Slim owns application routes after the request reaches `public/index.php`:

- HTML pages such as `/`, `/login`, `/dashboard`, and `/admin/users`.
- API routes such as `/api/health`, `/api/me`, and `/api/admin/users`.
- Fallback 404 handling for unknown routes.

nginx must not expose arbitrary PHP files. The current nginx config returns `404` for `location ~ \.php$` except the exact `/index.php` location.

## Request Flow

HTML page request:

```text
browser -> nginx -> PHP-FPM -> public/index.php
public/index.php -> Bootstrap::loadEnv()
public/index.php -> Bootstrap::services()
Slim -> middleware stack -> route controller
controller -> repository/service as needed
controller -> TemplateRenderer -> templates/*.php
response -> PHP-FPM -> nginx -> browser
```

Browser-parts page request:

```text
browser -> nginx -> PHP-FPM -> public/index.php
controller -> repository/service as needed
controller -> PHP template shell + __BAKED__ JSON + __MOUNTS__ JSON
browser -> /engine/bootstrap.js -> /engine/core.js
bootstrap -> /parts/<name>/index.js -> mount(part, params)
part template -> DOM
```

API request:

```text
browser/client -> nginx -> PHP-FPM -> public/index.php
Slim -> middleware stack -> route controller
controller -> repository/service as needed
controller -> JSON response
```

Controllers set the response shape: HTML pages return `Content-Type: text/html; charset=utf-8`; JSON APIs return `Content-Type: application/json`.

## Bootstrap And Services

`public/index.php` is intentionally small:

1. Defines `BASE_PATH`.
2. Loads Composer autoloading.
3. Loads `.env`.
4. Creates the Slim app.
5. Builds `AppServices`.
6. Registers middleware.
7. Registers routes.
8. Runs the app.

`src/App/Bootstrap.php` creates shared services from environment configuration. `src/App/AppServices.php` is a simple typed service bundle. Keep service creation explicit; do not hide runtime wiring behind reflection or auto-discovery.

## Middleware Order

`config/middleware.php` declares the request policy stack. Keep cross-cutting behavior here instead of scattering it through controllers.

Current responsibilities:

- `SecurityHeadersMiddleware` adds security headers.
- Slim error middleware delegates to `ErrorHandler`.
- `SessionMiddleware` starts hardened PHP sessions.
- Slim body parsing middleware parses request bodies.
- `CsrfMiddleware` protects unsafe methods.
- `LoginRateLimitMiddleware` throttles login attempts.
- `AccessPolicyMiddleware` enforces default-deny access policy.
- Slim routing middleware resolves the route.

When adding middleware, document why it belongs globally and how it interacts with authentication, CSRF, sessions, and error handling.

## Authorization

`AccessPolicyMiddleware` is authoritative.

Public routes are listed explicitly in `PUBLIC_ROUTES`. Any route not listed there requires an authenticated user. Routes under `/admin/*` and `/api/admin/*` require the `admin` role.

HTML unauthorized responses redirect to `/login`. API unauthorized responses return JSON `401`. Forbidden admin access returns `403`, with HTML or JSON based on the URL prefix.

Templates may hide admin navigation, but that is only presentation. Console requests, direct URLs, and API clients must still be blocked by backend policy.

## CSRF

Unsafe methods (`POST`, `PUT`, `PATCH`, `DELETE`) must pass `CsrfMiddleware`.

Accepted token locations:

- Form field named `_csrf`.
- Header named `X-CSRF-Token`.

HTML forms should receive the token from `CsrfToken::get()` through the controller and render it as a hidden `_csrf` input. JSON clients should send the token through `X-CSRF-Token` when an unsafe API route exists.

## Static Assets

Public browser assets live under `public/static/`, `public/engine/`, and `public/parts/`. The current app uses `public/static/app.css` plus the optional frontend parts runtime.

Rules:

- Do not place secrets, PHP source, private uploads, logs, sessions, or database files under `public/`.
- Keep static files simple unless a real need appears for an asset build step.
- Do not place server-only state builders in `public/parts`; parts are browser modules only.
- If cache policy changes, document it together with the nginx config change.

## Error And Not Found Behavior

Unknown `/api/*` routes return JSON `404` with an `error` field.

Unknown non-API routes return a small HTML `404` page.

Application exceptions are handled by `src/Http/ErrorHandler.php` through Slim error middleware. `APP_DEBUG` controls debug behavior; production should keep `APP_DEBUG=false`.

## Runtime Files

Persistent runtime files and state:

- `.env`
- `storage/attachments`
- `storage/logs`
- `storage/sessions`
- PostgreSQL data

Deploys must not overwrite or delete those files. Source files can be replaced; runtime secrets and mutable state must survive application deploys.
