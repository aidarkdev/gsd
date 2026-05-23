import { escape } from '/engine/core.js';

export default (state, part) => {
    const strings = state.strings ?? {};

    return `
        <section class="part-demo" data-part-id="${escape(part.id)}">
            <p>
                <span data-ref="name">${escape(state.name)}</span>
                ·
                <span data-ref="role">${escape(state.role)}</span>
            </p>
            <p>${escape(strings.clientClicks ?? '')}: <strong data-ref="clicks">${escape(state.clicks ?? 0)}</strong></p>
            <button type="button" data-action="increment-clicks">${escape(strings.count ?? '')}</button>
        </section>
    `;
};
