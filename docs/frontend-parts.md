# Frontend Parts

This document defines the GSD-specific browser parts contract. It adapts the frontend-parts model to PHP without bundlers, npm, or a separate server-side JavaScript runtime.

## Purpose

Browser parts are optional. Use them for bounded interactive pages where plain PHP templates would scatter client-side state and DOM updates.

Do not use them for static content, simple forms, or tables that PHP can render clearly.

## Runtime Boundary

PHP remains the server runtime:

- controllers authorize, validate, call repositories/services, and choose responses;
- repositories own SQL;
- API routes stay in Slim;
- CSRF and access policy stay server-side.

The browser engine only mounts parts, stores page-local state, dispatches local DOM events, synchronizes DOM through state handlers, and coordinates parts through MacroState.

## Public Files

Engine files live in `public/engine/`:

- `core.js` exports `mount(partModule, params)`, `destroy(instance)`, and `escape(value)`.
- `bootstrap.js` reads page metadata and mounts instances.
- `http.js` provides small same-origin JSON helpers that attach the CSRF token from `<meta name="csrf-token">`.

Part files live in `public/parts/<name>/`:

- `index.js` imports and exports the part surface.
- `template.js` returns HTML strings.
- `handlers.js` defines event, state, mount, and destroy handlers.

No server-only PHP, secrets, SQL, or state-builder files belong in `public/parts`.

## PHP Page Contract

A PHP template that mounts parts emits:

```html
<meta name="csrf-token" content="...">
<script type="application/json" id="__BAKED__">{}</script>
<script type="application/json" id="__MOUNTS__">{"instances":[]}</script>
<div data-mount-id="example-part"></div>
<script type="module" src="/engine/bootstrap.js"></script>
```

`__BAKED__` is a flat object keyed by stable part instance id.

`__MOUNTS__` contains an `instances` array. Each instance has:

- `id`: required, unique on the page;
- `part`: required browser module URL, for example `/parts/dashboard-summary/index.js`;
- `microState`: optional fallback if no baked state exists;
- `expose`: optional list of local state fields published to MacroState;
- `subscribe`: optional map of local field name to MacroState path.

The bootstrap module imports each `part` module in order and calls `mount(partModule, params)`. Mount order matters when subscribers depend on MacroState owners.

## Backend State Builders

In this paradigm, a baker is backend code that prepares data for a client template. In GSD, that role is usually handled by the controller. If preparation grows beyond the controller's HTTP boundary, extract a small PHP state builder that the controller calls before rendering the page shell.

Rules:

- state must be JSON-serializable;
- instance ids must be stable and domain-based;
- bulk page data belongs in the owning part's microState, not MacroState;
- user-derived strings must still be escaped in browser templates with `escape(value)`.

When embedding JSON in templates, use JSON hex flags so user data cannot break out of the JSON script tag.

## Part Contract

`index.js` follows this shape:

```js
import handlers from './handlers.js';
import * as templates from './template.js';

export default {
    template: templates.default,
    templates,
    handlers,
};
```

Templates return one root element as a string:

```js
import { escape } from '/engine/core.js';

export default (state, part) => `
    <section data-part-id="${escape(part.id)}">
        <button type="button" data-action="increment">Count</button>
        <span data-ref="count">${escape(state.count)}</span>
    </section>
`;
```

Handlers keep a strict two-stage flow:

```text
DOM event / async / MacroState -> microState -> state handler -> DOM
```

Event handlers call `part.set(...)`. DOM updates belong in `handlers.state`.

## MacroState

MacroState is a page-local coordination bus.

- Owners expose fields as `{id}.{field}`.
- Subscribers mirror owner fields into local state fields.
- There is no external `get`.
- It is not for bulk data, caches, or one-shot commands.

Use MacroState only when independent parts need to coordinate. Otherwise keep data in local microState or pass it through baked state.

## Security

- Backend authorization is authoritative.
- Unsafe API calls from parts must send `X-CSRF-Token`. Prefer `csrfHeaders`, `postJson`, or `postJsonOk` from `/engine/http.js`.
- Browser templates must call `escape(value)` for user-derived values.
- `escape(value)` does not validate URL schemes.
- No inline executable scripts are required; current CSP can keep same-origin external modules.

## Current Demo

`/dashboard` mounts `dashboard-summary` as a minimal proof:

- PHP prepares baked state from the authenticated user.
- `bootstrap.js` imports `/parts/dashboard-summary/index.js`.
- The part renders name, role, and a local click counter.
- The logout form remains an ordinary CSRF-protected PHP form.
