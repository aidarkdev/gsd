import { postJsonOk } from '/engine/http.js';

function refresh(part, values = {}) {
    part.set({
        error: null,
        message: null,
        ...values,
        renderVersion: Number(part.state.renderVersion ?? 0) + 1,
    });
}

function replaceById(items, item) {
    const id = Number(item.id);
    const next = [...items];
    const index = next.findIndex((candidate) => Number(candidate.id) === id);

    if (index === -1) {
        next.unshift(item);
    } else {
        next[index] = item;
    }

    return next;
}

function formData(form) {
    return Object.fromEntries(new FormData(form).entries());
}

export default {
    events: {
        'submit [data-action="inbox-task-form"]': async (part, event) => {
            event.preventDefault();
            const payload = formData(event.target);
            const id = Number(payload.id || 0);

            delete payload.id;

            try {
                const data = await postJsonOk(id ? `/api/inbox-tasks/${id}` : '/api/inbox-tasks', payload);
                refresh(part, {
                    tasks: replaceById(part.state.tasks ?? [], data.task),
                    editingTaskId: null,
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'click [data-action="inbox-task-edit"]': (part, event) => {
            refresh(part, {
                editingTaskId: Number(event.target.closest('[data-task-id]')?.dataset.taskId ?? 0),
            });
        },

        'click [data-action="inbox-task-cancel"]': (part) => {
            refresh(part, { editingTaskId: null });
        },

        'click [data-action="inbox-task-delete"]': async (part, event) => {
            const id = Number(event.target.closest('[data-task-id]')?.dataset.taskId ?? 0);

            try {
                await postJsonOk(`/api/inbox-tasks/${id}/delete`, {});
                refresh(part, {
                    tasks: (part.state.tasks ?? []).filter((task) => Number(task.id) !== id),
                    editingTaskId: Number(part.state.editingTaskId) === id ? null : part.state.editingTaskId,
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'change [data-action="inbox-task-status"]': async (part, event) => {
            const target = event.target;
            const id = Number(target.closest('[data-task-id]')?.dataset.taskId ?? 0);
            const previous = part.state.tasks ?? [];
            const tasks = previous.map((task) => Number(task.id) === id ? { ...task, status: target.value } : task);

            refresh(part, { tasks });

            try {
                const data = await postJsonOk(`/api/inbox-tasks/${id}`, { status: target.value });
                refresh(part, { tasks: replaceById(part.state.tasks ?? [], data.task) });
            } catch (error) {
                refresh(part, { tasks: previous, error: error.message });
            }
        },

        'submit [data-action="inbox-task-schedule"]': async (part, event) => {
            event.preventDefault();
            const form = event.target;
            const id = Number(form.closest('[data-task-id]')?.dataset.taskId ?? 0);

            try {
                await postJsonOk(`/api/inbox-tasks/${id}/schedule`, formData(form));
                refresh(part, {
                    tasks: (part.state.tasks ?? []).filter((task) => Number(task.id) !== id),
                    editingTaskId: Number(part.state.editingTaskId) === id ? null : part.state.editingTaskId,
                    message: part.state.strings?.['inbox.scheduled'] ?? 'Scheduled',
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },
    },

    state: {
        renderVersion: (part) => {
            part.refs.body.innerHTML = part.templates.workspace(part.state, part);
        },
    },
};
