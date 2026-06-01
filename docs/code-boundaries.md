# Code Boundaries

This document describes how GSD separates frontend code, backend code, support code, and runtime responsibilities. It reflects the current PHP/Slim/PostgreSQL implementation.

## Request Flow

Production requests follow this path:

```text
browser -> nginx -> PHP-FPM -> public/index.php -> Slim
Slim -> middleware -> controller -> repository/service -> template or JSON response
```

nginx owns public file delivery from `public/` and forwards application requests to the exact `/index.php` PHP entrypoint. Slim owns application routing after `public/index.php` creates the app, builds services, registers middleware, registers routes, and runs the request.

HTML responses are server-rendered through PHP templates. JSON responses are built by controllers. Browser parts are optional and mount only where a PHP template emits baked state and mount metadata.

## Backend Layers

Bootstrap and service wiring live in `src/App/`.

- `Bootstrap::loadEnv()` loads runtime configuration from `.env`.
- `Bootstrap::services()` creates explicit shared services.
- `AppServices` is a typed bundle passed into route and middleware registration.
- This layer should stay explicit. Do not replace it with reflection, auto-discovery, or a hidden service container.

HTTP routing lives in `config/routes.php`.

- Routes are registered in one place.
- Controllers are created with the services they need.
- Fallback handling returns JSON `404` for `/api/*` paths and HTML `404` for other paths.

Global request policy lives in `config/middleware.php` and `src/Middleware/`.

- `SessionMiddleware` starts hardened PHP sessions.
- `CsrfMiddleware` protects unsafe methods.
- `LoginRateLimitMiddleware` throttles login attempts.
- `AccessPolicyMiddleware` enforces public, authenticated, and admin-only access.
- `SecurityHeadersMiddleware` adds baseline browser security headers.
- Middleware is the authoritative security layer; UI visibility is not authorization.

Controllers live in `src/Controller/`.

- Controllers own request parsing, validation calls, status codes, redirects, response content type, and response shape.
- Controllers may call repositories, auth helpers, validators, and the template renderer.
- Controllers should pass prepared data into templates.
- Controllers should not contain raw SQL when a repository is the natural boundary.
- Controllers should not render large HTML strings inline.

Data access lives in `src/Repository/` and `src/Database.php`.

- `Database` owns PDO connection creation and PDO settings.
- Repositories own SQL and prepared statements.
- Repositories return arrays shaped for controller or service use.
- Templates and browser code must not query the database.

Auth and security helpers live in `src/Auth/`.

- `AuthService` owns login, logout, current-user lookup, and role checks.
- `CsrfToken` owns session-backed CSRF token generation and validation.
- These helpers support middleware and controllers; they are not frontend APIs.

Rendering lives in `src/View/TemplateRenderer.php` and `templates/`.

- `TemplateRenderer` resolves and includes PHP templates.
- Templates render HTML from data already prepared by controllers.
- Templates may branch and loop for display, but they should not perform database, authorization, session, or response-status work.

Support utilities live outside the HTTP path unless called by controllers or middleware.

- `src/Validation/Validator.php` provides small local validation rules.
- `src/Log/FileLogger.php` writes application logs.
- `scripts/*` contains setup, deploy, and test scripts.
- `database/schema.sql` owns the current database schema.

## Frontend Layers

The primary frontend is server-rendered PHP templates in `templates/`.

- `templates/auth/login.php`, `templates/inbox.php`, `templates/habits.php`, and `templates/calendar.php` render complete HTML pages.
- Dynamic text is escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Unsafe forms include `_csrf`.
- Templates may hide links based on role for presentation, but backend middleware remains authoritative.

Styling lives in `public/static/app.css`.

- CSS is plain static CSS.
- There is no CSS build step or browser package manager.
- Styling should follow the simple existing structure until there is a concrete reason to split it.

Optional browser-parts runtime lives in `public/engine/`.

- `core.js` mounts parts, owns local part state, state handlers, refs, delegated events, and MacroState.
- `bootstrap.js` reads `__MOUNTS__` and imports public part modules.
- `http.js` provides small same-origin JSON helpers that attach the CSRF token.
- The engine is browser-only and must not know PHP internals beyond baked JSON and public API routes.

Browser part modules live in `public/parts/<name>/`.

- `index.js` exports the part surface.
- `template.js` returns one-root HTML strings and escapes user-derived values with `/engine/core.js`.
- `handlers.js` owns DOM events and state-driven DOM updates.
- Browser parts should call normal Slim API routes for backend mutations.
- No PHP, secrets, SQL, or server-only state builders belong under `public/parts`.

## Coupling Rules

Allowed dependencies should point inward toward stable backend services and outward only through public response contracts.

- `public/index.php` may depend on Composer autoloading, `Bootstrap`, config files, and Slim.
- `config/routes.php` may instantiate controllers and wire services into them.
- `config/middleware.php` may wire middleware from `AppServices`.
- Controllers may depend on repositories, auth helpers, validators, CSRF helpers, and template rendering.
- Repositories should depend only on `Database` and PHP/PDO primitives.
- Middleware may depend on narrow services needed for request policy.
- Templates should depend only on variables passed by controllers.
- Browser parts should depend only on browser APIs, `public/engine/*`, baked JSON, mount metadata, and public HTTP APIs.

Avoid reverse coupling:

- Repositories must not call controllers, templates, middleware, or browser code.
- Templates must not call repositories or make authorization decisions.
- Browser modules must not import or depend on PHP source.
- Middleware should not contain page-specific rendering logic beyond small policy responses.
- Public assets must not contain secrets or server-only runtime state.

## Extension Points

For a new server-rendered page:

1. Add or adjust a route in `config/routes.php`.
2. Put HTTP behavior in a controller.
3. Add repository methods for database reads or writes.
4. Render prepared data through a PHP template.
5. Let middleware enforce access, sessions, CSRF, throttling, headers, and errors.

For a new API route:

1. Add the route in `config/routes.php`.
2. Keep request parsing, validation, status codes, and JSON shape in a controller.
3. Use repositories for SQL.
4. Keep unsafe methods behind CSRF unless the route is intentionally public and documented.
5. Return JSON with `Content-Type: application/json`.

For a bounded interactive frontend region:

1. Prefer a plain PHP template first.
2. Use a browser part only when local state, delegated DOM events, targeted DOM updates, or part coordination is needed.
3. Prepare baked state in PHP before rendering.
4. Emit `__BAKED__`, `__MOUNTS__`, and matching `data-mount-id` anchors from the template.
5. Keep backend mutations behind normal Slim API routes.

For schema or runtime changes:

- Update `database/schema.sql` for schema changes.
- Document one-time production data steps in `docs/deploy.md`.
- Keep `.env`, `storage/attachments`, `storage/logs`, `storage/sessions`, and PostgreSQL data persistent across deploys.
- Document new system packages, Composer dependencies, environment variables, storage directories, or nginx changes.
