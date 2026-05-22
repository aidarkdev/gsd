# Feature Development

This checklist describes how to add features while keeping GSD aligned with its current architecture.

## Before Writing Code

- Find the closest existing route, controller, repository, template, middleware, and script.
- Decide whether the feature is plain PHP-rendered HTML, browser-parts HTML, API, or a combination.
- Decide whether it is public, authenticated, or admin-only.
- Confirm whether it needs database schema changes.
- Confirm whether unsafe requests need forms, JSON APIs, or both.

Prefer extending the existing path over creating a new layer.

## Route And Access

Add routes in `config/routes.php`.

Access rules:

- Public routes must be explicitly listed in `AccessPolicyMiddleware::PUBLIC_ROUTES`.
- Routes not listed as public require login by default.
- `/admin/*` and `/api/admin/*` require the `admin` role.
- UI hiding is not access control.

Choose paths so the middleware policy is obvious. Admin pages and admin APIs should live under the existing admin prefixes.

## Controller

Controllers own HTTP behavior:

- read request data;
- call validation;
- call services or repositories;
- choose redirect, HTML, JSON, or error response;
- set status codes;
- set content type;
- pass prepared data to templates.

Controllers should not contain raw SQL when a repository is the natural boundary. Controllers should not render large HTML strings inline.

## Database And Repositories

Repositories own SQL.

Use prepared statements for input values. Keep returned rows shaped for controller/service use, not for direct template coupling when avoidable.

Schema changes belong in `database/schema.sql` unless the project gains a migration system. Any one-time production data step must be documented in `docs/deploy.md`.

## Templates

Templates render HTML from data passed by controllers.

Rules:

- Escape dynamic values with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- Keep logic to display decisions and loops.
- Include `_csrf` in unsafe forms.
- Do not query databases.
- Do not perform authorization decisions.

If a template starts needing complex preparation, move that work back to the controller or a service.

## Browser Parts

Use browser parts only for bounded interactive regions.

When adding a part:

- put public modules under `public/parts/<name>/`;
- prepare baked state in PHP before rendering;
- emit `__BAKED__`, `__MOUNTS__`, and `data-mount-id` anchors from the PHP template;
- keep DOM writes inside `handlers.state`;
- keep backend mutations behind normal Slim API routes with CSRF and access policy.

## APIs

API responses should be JSON and should set `Content-Type: application/json`.

Use consistent status codes:

- `200` for successful reads or completed actions.
- `201` when a new resource is created and the distinction matters.
- `400` or `422` for invalid input.
- `401` for unauthenticated API access.
- `403` for forbidden API access.
- `404` for missing resources.

Unsafe API requests must send `X-CSRF-Token` unless a route is intentionally designed and documented as public.

## Validation And Security

Validate user input before persistence or sensitive decisions.

Review every feature for:

- authentication and role requirements;
- CSRF on unsafe methods;
- login throttling impact if auth-related;
- escaped HTML output;
- safe SQL parameters;
- sensitive fields excluded from JSON responses;
- production-safe error messages.

## Tests And Checks

At minimum, run:

```sh
bash scripts/test.sh
```

Add or adjust checks when a feature changes:

- routing behavior;
- access policy;
- CSRF behavior;
- database schema or repository behavior;
- HTML rendering for required data;
- JSON response shape.

Manual curl checks are acceptable for small route additions when automated coverage does not exist yet, but they should be written down in the change notes.

## Deployment Notes

Update `docs/deploy.md` when a feature adds or changes:

- system packages;
- Composer dependencies;
- environment variables;
- storage directories;
- nginx config;
- database setup or migration steps;
- required post-deploy checks.
