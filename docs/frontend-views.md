# Frontend Views

GSD uses server-rendered PHP templates as the default frontend model. For bounded interactive pages, PHP may emit a page shell, baked JSON, mount metadata, and browser parts rendered by the small engine in `public/engine/`.

## Boundary

The frontend boundary is split into three simple layers:

1. Controllers prepare data, choose status codes, and choose templates.
2. Templates render HTML from already-prepared data.
3. CSS, optional JavaScript, and optional browser parts enhance or render bounded interactive regions.

Templates must not query the database, perform authorization, mutate session state, or decide response status codes.

## Template Rules

- Escape every dynamic value with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` unless the value is a deliberately trusted HTML fragment.
- Keep PHP logic in templates limited to display branching and loops.
- Use semantic HTML and ordinary forms before adding JavaScript.
- Keep navigation visibility aligned with user role, but never treat hidden UI as authorization.
- Do not include inline event handler attributes such as `onclick`.

The controller should pass complete data to the template. If the template needs a value that requires SQL or service logic, move that work into a repository/service and pass the result in.

## Forms

Unsafe forms must include a CSRF token:

```php
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
```

Controllers that render forms should pass `csrfToken` from `CsrfToken::get()`.

Validation errors should be prepared by the controller and rendered by the template. The template may display errors, but it should not validate request data itself.

## Browser Parts

Use ordinary PHP views for static pages, simple forms, and tables that do not need client-side state.

Use browser parts when a page needs explicit local state, delegated DOM events, targeted DOM updates, or coordination between a bounded number of widgets.

PHP remains the backend boundary. It prepares baked state, emits `__BAKED__` and `__MOUNTS__`, and serves the external bootstrap module. Browser parts never call PHP state builders directly; they use normal API routes for later network work.

See `docs/frontend-parts.md` for the runtime contract.

## JavaScript Policy

JavaScript is allowed when it keeps the project explicit and backend-safe.

Good uses:

- Browser parts for bounded interactive pages.
- Small interactions that improve an already-working server-rendered page.
- Optional client-side validation that mirrors server validation.
- UI behavior that does not replace server authorization or CSRF checks.

Avoid by default:

- Moving backend authorization, validation, or persistence rules into the browser.
- Moving form submission exclusively to `fetch` when an ordinary form is enough.
- Adding a framework, bundler, or package manager for isolated UI behavior.
- Creating hidden client state that the server cannot validate.

## Styling

CSS lives in `public/static/app.css` until the project has a concrete need for more structure.

Keep styling boring and maintainable:

- Prefer ordinary class names over generated styles.
- Avoid a build step unless static CSS becomes a real blocker.
- Keep layout rules close to the UI patterns already present.

## Security

Templates are the last line before HTML reaches the browser. Review them for:

- Unescaped dynamic text.
- User-controlled URLs in `href` or `src`.
- Missing CSRF fields in unsafe forms.
- Admin-only links shown to non-admin users.
- Sensitive data rendered into the page unnecessarily.

Escaping does not validate URL schemes. Any user-controlled URL needs separate validation before it is rendered.
