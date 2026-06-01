import { escape } from '/engine/core.js';

export default (state, part) => `
    <section class="inbox-workspace" data-part-id="${escape(part.id)}">
        <div data-ref="body">${workspace(state)}</div>
    </section>
`;

export function workspace(state) {
    return `
        <div class="inbox-topbar">
            <h1>${escape(text(state, 'inbox.heading', 'Inbox'))}</h1>
        </div>
        ${message(state)}
        <div class="inbox-layout">
            <section class="inbox-list" aria-label="${escape(text(state, 'inbox.heading', 'Inbox'))}">
                ${taskList(state)}
            </section>
            <aside class="inbox-editor">
                ${taskForm(state)}
            </aside>
        </div>
    `;
}

function taskList(state) {
    const tasks = state.tasks ?? [];

    if (tasks.length === 0) {
        return `<p class="calendar-empty">${escape(text(state, 'inbox.empty', 'No inbox tasks'))}</p>`;
    }

    return tasks.map((task) => `
        <article class="inbox-task">
            <div class="inbox-task-main">
                <strong>${escape(task.title)}</strong>
                ${task.body_md ? `<p>${escape(task.body_md)}</p>` : ''}
            </div>
            <label>
                ${escape(text(state, 'inbox.status_field', 'Status'))}
                <select data-action="inbox-task-status" data-task-id="${escape(task.id)}">
                    ${statusOptions(state, task.status)}
                </select>
            </label>
            <form class="inbox-schedule-form" data-action="inbox-task-schedule" data-task-id="${escape(task.id)}">
                <label>
                    ${escape(text(state, 'inbox.start_date', 'Start date'))}
                    <input type="date" name="start_date" required>
                </label>
                <label>
                    ${escape(text(state, 'inbox.end_date', 'End date'))}
                    <input type="date" name="end_date" required>
                </label>
                <button type="submit">${escape(text(state, 'inbox.schedule_task', 'Schedule'))}</button>
            </form>
            <div class="inbox-row-actions">
                <button type="button" data-action="inbox-task-edit" data-task-id="${escape(task.id)}">${escape(text(state, 'inbox.edit', 'Edit'))}</button>
                <button type="button" data-action="inbox-task-delete" data-task-id="${escape(task.id)}">${escape(text(state, 'inbox.delete_task', 'Delete'))}</button>
            </div>
        </article>
    `).join('');
}

function taskForm(state) {
    const task = (state.tasks ?? []).find((candidate) => Number(candidate.id) === Number(state.editingTaskId));

    return `
        <form class="inbox-form" data-action="inbox-task-form">
            <input type="hidden" name="id" value="${escape(task?.id ?? '')}">
            <label>
                ${escape(text(state, 'inbox.title_field', 'Title'))}
                <input name="title" value="${escape(task?.title ?? '')}" required>
            </label>
            <label>
                ${escape(text(state, 'inbox.details_field', 'Details'))}
                <textarea name="body_md">${escape(task?.body_md ?? '')}</textarea>
            </label>
            <label>
                ${escape(text(state, 'inbox.status_field', 'Status'))}
                <select name="status">
                    ${statusOptions(state, task?.status ?? 'will_do')}
                </select>
            </label>
            <div class="inbox-row-actions">
                <button type="submit">${escape(task ? text(state, 'inbox.save_task', 'Save task') : text(state, 'inbox.add_task', 'Add task'))}</button>
                ${task ? `<button type="button" data-action="inbox-task-cancel">${escape(text(state, 'inbox.cancel', 'Cancel'))}</button>` : ''}
            </div>
        </form>
    `;
}

function statusOptions(state, selected) {
    return ['will_do', 'ongoing', 'done', 'stale'].map((status) => `
        <option value="${escape(status)}"${selected === status ? ' selected' : ''}>${escape(text(state, `calendar.status.${status}`, status))}</option>
    `).join('');
}

function message(state) {
    if (!state.message && !state.error) {
        return '';
    }

    return `<p class="${state.error ? 'error' : 'form-message'}">${escape(state.error ?? state.message)}</p>`;
}

function text(state, key, fallback) {
    return state.strings?.[key] ?? fallback;
}
