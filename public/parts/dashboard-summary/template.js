import { escape } from '/engine/core.js';

export default (state, part) => `
    <section class="part-demo" data-part-id="${escape(part.id)}">
        <p>
            <span data-ref="name">${escape(state.name)}</span>
            ·
            <span data-ref="role">${escape(state.role)}</span>
        </p>
        <p>Client clicks: <strong data-ref="clicks">${escape(state.clicks ?? 0)}</strong></p>
        <button type="button" data-action="increment-clicks">Count</button>
    </section>
`;
