import { escape } from '/engine/core.js';

export const slidePanelActions = {
    close: 'slide-panel-close',
    backdrop: 'slide-panel-backdrop',
};

export function slidePanel({ id, title, open, content, closeLabel }) {
    const labelId = `${id}-title`;
    const rootClasses = ['slide-panel-root', open ? 'is-open' : ''].filter(Boolean).join(' ');

    return `
        <div class="${escape(rootClasses)}" data-slide-panel-id="${escape(id)}">
            <button
                class="slide-panel-backdrop"
                type="button"
                data-action="${slidePanelActions.backdrop}"
                aria-label="${escape(closeLabel)}"
                tabindex="-1"
            ></button>
            <aside class="slide-panel" aria-labelledby="${escape(labelId)}">
                <header class="slide-panel-header">
                    <h2 id="${escape(labelId)}">${escape(title)}</h2>
                    <button
                        class="slide-panel-close"
                        type="button"
                        data-action="${slidePanelActions.close}"
                        aria-label="${escape(closeLabel)}"
                        title="${escape(closeLabel)}"
                    >x</button>
                </header>
                <div class="slide-panel-body">
                    ${content}
                </div>
            </aside>
        </div>
    `;
}
