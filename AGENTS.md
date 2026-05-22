# Agent Instructions

Do not invent architecture or add code before inspecting the current implementation. Less code is better: fewer moving parts means fewer bugs. If a requested change is ambiguous, inspect first and ask a concrete question before writing code.

Before changing project behavior, read the relevant docs. Start with `docs/project-soul.md` for project direction and `docs/feature-development.md` for feature flow. For frontend changes, read `docs/frontend-views.md` and `docs/frontend-ui-guidelines.md`; when browser parts are involved, also read `docs/frontend-parts.md`. For runtime and deployment behavior, read `docs/server-runtime.md`, `docs/deploy.md`, and `docs/dependencies.md`.

Dependency policy:

- Do not add Composer or browser dependencies by default.
- Prefer PHP 8.3, Slim, PDO, PostgreSQL, nginx, PHP-FPM, browser APIs, and existing local code.
- Do not add a frontend framework, bundler, ORM, service container, template system, or hidden auto-discovery layer unless there is a concrete documented need.
- `composer.json` is the PHP dependency and autoload manifest. Runtime packages must be explicitly justified.
- Public browser assets do not use npm, a package manager, or a build step unless static assets and browser parts are no longer sufficient.

Current backend boundaries:

- `public/index.php` is the only PHP HTTP entrypoint.
- `src/App/Bootstrap.php` and `src/App/AppServices.php` own explicit runtime service wiring.
- `config/routes.php` owns route registration and controller assignment.
- `config/middleware.php` owns the global middleware stack.
- `src/Controller/*` is the HTTP boundary: request data, validation calls, redirects, status codes, content types, templates, and JSON responses.
- `src/Repository/*` owns SQL. Use prepared statements for input values.
- `src/Database.php` owns PDO connection creation and settings.
- `src/Auth/*` owns authentication and CSRF token helpers.
- `src/Middleware/*` owns cross-cutting request policy.
- `src/View/TemplateRenderer.php` loads templates only.

Current frontend boundaries:

- `templates/*` is the default frontend. Templates render server-prepared data and ordinary forms.
- Templates must not query the database, mutate sessions, decide authorization, or choose response status codes.
- Dynamic template output must be escaped with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` unless it is deliberately trusted HTML.
- Unsafe forms must include `_csrf`.
- `public/static/app.css` owns plain CSS.
- `public/engine/*` owns the optional browser parts runtime.
- `public/parts/*` owns public browser part modules only. No PHP, secrets, SQL, or server-only state builders belong there.
- Browser parts are for bounded interactive regions. Do not use them for static content, simple forms, or tables that PHP can render clearly.
- New UI should follow `docs/frontend-ui-guidelines.md`: restrained operational layout, semantic HTML, explicit controls, stable dimensions, `data-action` for delegated events, and `data-ref` only for nodes that handlers update.

Security policy:

- Backend middleware and controllers are authoritative. Hidden links or buttons are presentation only.
- `AccessPolicyMiddleware` enforces default-deny access, authenticated routes, and admin-only `/admin/*` and `/api/admin/*` routes.
- `CsrfMiddleware` protects unsafe methods. Forms use `_csrf`; JSON clients use `X-CSRF-Token`.
- `SessionMiddleware`, `LoginRateLimitMiddleware`, `SecurityHeadersMiddleware`, and `ErrorHandler` keep their current centralized responsibilities.
- Do not move authorization, persistence, CSRF, or sensitive validation rules into browser code.
- Do not place secrets, private source, logs, sessions, uploads, or database files under `public/`.

For feature changes:

- Prefer extending the closest existing route, controller, repository, template, middleware, script, or browser part.
- Add routes in `config/routes.php`.
- Add public routes explicitly in `AccessPolicyMiddleware::PUBLIC_ROUTES`; all other routes require login by default.
- Keep SQL out of controllers and templates when a repository is the natural boundary.
- Keep large HTML strings out of controllers; render through templates.
- Schema changes belong in `database/schema.sql` unless the project gains a documented migration path.
- Runtime state and secrets live in `.env`, PostgreSQL, `storage/logs`, and `storage/sessions`, not in source-controlled code.

Checks:

- Run `bash scripts/test.sh` after behavior changes when possible.
- For deployment, environment, system package, nginx, dependency, or schema changes, update the relevant docs.
