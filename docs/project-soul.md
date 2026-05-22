# Project Soul

GSD is a small PHP application built around explicit server boundaries, conservative dependencies, and secure defaults. The project should stay easy to inspect: a developer should be able to follow a request from nginx to PHP-FPM, Slim, middleware, controller, repository, template, and response without hidden framework behavior.

## Core Philosophy

- Less code is better when it preserves clarity. Prefer extending the current shape over introducing a new abstraction.
- Every boundary has an owner. Controllers own HTTP behavior, repositories own SQL, templates own markup, middleware owns cross-cutting request policy.
- Security is not a UI feature. Buttons and links may be hidden in templates, but backend middleware and controllers are authoritative.
- Server-rendered PHP views are the baseline. PHP-backed browser parts are allowed for bounded interactive pages when a plain template becomes awkward.
- Runtime state is explicit. Secrets and mutable state live in `.env`, PostgreSQL, `storage/logs`, and `storage/sessions`, not in source-controlled code.

## Development Rules

Before adding a feature, inspect the existing implementation and reuse its path unless there is a concrete reason not to:

1. Add or adjust a route in `config/routes.php`.
2. Put request parsing, status codes, redirects, and response content type in a controller.
3. Put database reads and writes in a repository.
4. Pass prepared data into a PHP template.
5. Let middleware enforce authentication, admin access, sessions, CSRF, login throttling, security headers, and error handling.

Do not add a new framework, client runtime, service container pattern, or template system for convenience. A new abstraction must remove real repeated complexity and must fit the project shape already present.

## Public Contracts

The stable development contracts are:

- `public/index.php` is the PHP entrypoint.
- `config/middleware.php` declares the middleware stack.
- `config/routes.php` declares route ownership.
- `src/Controller/*` classes are HTTP boundaries.
- `src/Repository/*` classes are database boundaries.
- `templates/*` files render HTML only.
- `public/static/*` contains public CSS and simple browser assets.
- `public/engine/*` and `public/parts/*` contain the optional browser parts runtime.

Changing one of these contracts should be treated as an architectural change and documented in `docs/`.

## What Not To Add By Default

- No client-side application shell when a server-rendered PHP page is enough.
- No browser part for static content that a PHP template can render clearly.
- No ORM unless repositories and PDO become a proven maintenance problem.
- No build step for browser assets unless static CSS/JS is no longer enough.
- No broad helper layer that hides request, response, session, CSRF, or authorization behavior.
- No package for small tasks that PHP, Slim, PDO, PostgreSQL, nginx, or browser APIs already handle clearly.

## Code Review Standard

A change is aligned with the project when:

- The request path is obvious.
- Authorization is enforced server-side.
- Unsafe requests carry CSRF protection.
- Dynamic HTML output is escaped.
- SQL stays out of templates and controllers where a repository is appropriate.
- Runtime files and secrets are not overwritten by deploys.
- Tests or manual checks cover the behavior that changed.
