# Frontend UI Guidelines

GSD uses server-rendered PHP templates as the default UI. The `vidium/` example shows a stronger UI discipline that can be reused here because the important contracts are backend-language-neutral: PHP can prepare baked JSON and HTML shells just as well as Node.

This guide describes how to build UI in GSD without turning it into a separate frontend application.

## UI Direction

Build operational screens, not marketing pages.

- Prefer dense, readable layouts for repeated work: navigation, forms, tables, lists, admin pages, dashboards, and bounded widgets.
- Keep visual style restrained: clear hierarchy, stable spacing, legible type, obvious actions, and predictable navigation.
- Do not add a landing-page hero, decorative illustrations, gradient backgrounds, or animation-heavy UI unless the product requirement specifically asks for that.
- Use plain server-rendered pages for static pages, simple forms, simple tables, login, and ordinary CRUD screens.
- Use browser parts only when a region needs local state, delegated events, targeted DOM updates, polling, explicit pagination, or coordination between widgets.

## Page Structure

A normal server-rendered page should be shaped like this:

- Controller prepares all page data, current user state, CSRF token, validation errors, and status decisions.
- Template renders a complete HTML document or a complete page body, depending on the local pattern.
- Template includes `/static/app.css`.
- Navigation is ordinary semantic HTML and forms.
- Unsafe actions use forms with `_csrf` unless they are browser-part API calls using `X-CSRF-Token`.

For pages with browser parts:

- PHP still owns authorization, validation, data loading, baked state, and API responses.
- The PHP template emits `<meta name="csrf-token">`, `__BAKED__`, `__MOUNTS__`, mount anchors, and `/engine/bootstrap.js`.
- Baked JSON is flat by stable part instance id.
- Use JSON hex flags when embedding JSON in PHP templates.
- Keep fallback content inside mount anchors useful enough for no-JS or pre-mount display.

## Layout Patterns

Use a small set of stable layout primitives before inventing new page shapes.

- `.main` or equivalent page container should define max width, horizontal centering, and page padding.
- Use sticky top navigation only when repeated page work benefits from persistent controls.
- For admin and dashboard pages, prefer a topbar with a compact title and immediate actions.
- For large lists, prefer explicit pagination over infinite scroll.
- For side navigation or filters, use a sidebar that is fixed/off-canvas on small screens and persistent only when the viewport has enough width.
- Use tables for dense admin data. Wrap wide tables in a scroll container instead of squeezing columns into unreadable cards.
- Use cards for repeated visual items where image/title/actions matter; do not use cards as generic page sections.

CSS should define reusable tokens when a value appears as a layout contract:

- page background and text colors;
- content max width;
- nav height;
- sidebar width;
- control height;
- common gaps;
- active state colors.

Do not scale font size with viewport width. Use stable rem sizes and responsive layout changes.

## Forms And Controls

Forms should be ordinary HTML first.

- Use `<form method="post">` for normal unsafe actions.
- Include `_csrf` in unsafe PHP-rendered forms.
- Put validation and persistence rules in controllers/services, not templates or browser code.
- Render validation errors near the relevant form or in a compact error block.
- Preserve submitted values after validation errors when safe.
- Use native input types: `email`, `url`, `password`, `checkbox`, and `number` where appropriate.
- Use `<button type="submit">` for submit actions and `<button type="button">` for local UI actions.
- Disabled and pending states must be visible and must prevent duplicate actions.

For browser parts, controls should use:

- `data-action` for delegated event targets;
- `data-ref` only for nodes that handlers must update directly;
- `aria-label` and `title` for compact icon/symbol buttons;
- state fields for open/closed, loading, pending id, message, and error values.

Avoid inline event attributes such as `onclick`.

## Tables, Lists, And Collections

Choose the collection update style deliberately.

- For plain PHP pages, render the full collection in the template.
- For browser parts with small collections, replacing the whole collection and re-rendering the region is acceptable.
- For browser parts with focused updates, use explicit patch-trigger state fields such as `patchUserRole` or `patchCardStatusUpdates`.
- Patch-trigger handlers must update the backing collection in `part.state`, then update only the affected DOM nodes.
- Do not use MacroState, one-shot booleans, or hidden DOM reads as an event bus.

Collection UIs should include:

- empty states when no rows/items exist;
- explicit loading or disabled states for async changes;
- stable row/item ids in `data-*` attributes;
- pagination or bounded result counts for long lists.

## Browser Part Authoring

A part is a bounded UI unit, not a general application shell.

Part files under `public/parts/<name>/` follow this browser-only split:

- `index.js` imports `template.js` and `handlers.js`, then exports `{ template, templates, handlers }`.
- `template.js` returns one root element and named sub-templates when region re-rendering is needed.
- `handlers.js` owns delegated DOM events, state handlers, lifecycle listeners, async calls, and cleanup.

State flow must stay explicit:

```text
DOM event / async result / MacroState update -> part.set(...) -> handlers.state[field] -> DOM update
```

Rules:

- Event handlers should call `part.set(...)`; they should not scatter direct DOM writes.
- DOM writes belong in `handlers.state`.
- Full region re-renders should rebuild refs that changed.
- `onMount` and `onDestroy` are for global listeners, timers, polling, abort controllers, media APIs, and cleanup.
- Store timers, abort controllers, and listener references in `part.private`.
- Abort pending fetches and clear timers in `onDestroy`.
- Replace array/object references when a state handler must run.
- Do not write to mirror fields fed by MacroState.

## MacroState

Use MacroState only for coordination between independent parts.

- Owners expose fields as `{id}.{field}`.
- Subscribers map a local state field to a remote MacroState path.
- Part code should use local state names; page composition decides the remote path.
- Add a short MacroState contract comment near page composition when a page wires `expose` or `subscribe`.
- Do not put bulk collections, caches, server data, or one-shot commands into MacroState.

If only one part needs the data, keep it in that part's microState.

## CSS Rules

Keep CSS boring and durable.

- `public/static/app.css` is the default CSS home until there is a concrete need to split it.
- Use semantic class names based on UI role, not implementation accidents.
- Keep layout CSS close to established patterns: nav, main, topbar, forms, tables, cards, buttons, messages.
- Use `box-sizing: border-box` globally.
- Define dimensions for controls that must not resize on hover, loading, or text changes.
- Keep text from overflowing buttons, cards, sidebars, table cells, and topbars; use wrapping or ellipsis based on the component's purpose.
- Use media queries for layout changes, not viewport-based font scaling.
- Preserve keyboard and touch usability: controls need enough hit area and visible disabled/focus/hover states.

Do not introduce a CSS framework, preprocessor, utility generator, or build step unless static CSS has become a documented maintenance problem.

## Accessibility And Semantics

Use semantic HTML before adding JavaScript behavior.

- Use real links for navigation.
- Use real forms for submissions.
- Use buttons for actions.
- Give icon-only or symbol-only buttons an `aria-label`.
- Keep heading order meaningful.
- Use table markup for tabular data.
- Use `aria-expanded` for dropdowns/details-like controls when custom state is mirrored.
- Do not hide security-sensitive controls as the only protection; backend policy remains authoritative.

## What Not To Build By Default

- No SPA shell for ordinary pages.
- No infinite scroll; use explicit pagination.
- No client-side-only authorization or validation.
- No browser dependency manager for isolated interactions.
- No generic global event bus.
- No broad component library before repeated UI pressure proves the need.
- No server-only PHP, secrets, SQL, or state builders under `public/parts`.

The preferred path is still: controller prepares data, repository owns SQL, template renders HTML, browser parts enhance only bounded interactive regions.
