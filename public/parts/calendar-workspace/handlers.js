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
        next.push(item);
    } else {
        next[index] = item;
    }

    return next;
}

function formData(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function applyData(part, payload) {
    const data = payload.data ?? payload;
    const taskAttachments = groupBy(data.taskAttachments ?? [], 'task_id');
    const noteAttachments = groupBy(data.noteAttachments ?? [], 'note_id');

    refresh(part, {
        tasks: (data.tasks ?? []).map((task) => ({
            ...task,
            id: Number(task.id),
            attachments: taskAttachments.get(Number(task.id)) ?? task.attachments ?? [],
        })),
        notes: (data.notes ?? []).map((note) => ({
            ...note,
            id: Number(note.id),
            attachments: noteAttachments.get(Number(note.id)) ?? note.attachments ?? [],
        })),
        habits: (data.habits ?? []).map((habit) => ({
            ...habit,
            id: Number(habit.id),
            frequency_days: Number(habit.frequency_days),
        })),
        entries: (data.entries ?? []).map((entry) => ({
            ...entry,
            id: Number(entry.id),
            habit_id: Number(entry.habit_id),
        })),
    });
}

async function reloadData(part) {
    const params = new URLSearchParams({
        from: part.state.range.from,
        to: part.state.range.to,
    });
    const response = await fetch(`/api/day-data?${params.toString()}`);
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || `HTTP ${response.status}`);
    }

    applyData(part, data);
}

function groupBy(items, key) {
    const grouped = new Map();

    for (const item of items) {
        const id = Number(item[key]);

        if (!grouped.has(id)) {
            grouped.set(id, []);
        }

        grouped.get(id).push(item);
    }

    return grouped;
}

export default {
    events: {
        'click [data-action="select-day"]': (part, event) => {
            const date = event.target.closest('[data-action="select-day"]')?.dataset.date;

            if (date) {
                refresh(part, {
                    selectedDate: date,
                    dayPanelOpen: true,
                    editingTaskId: null,
                    editingHabitId: null,
                });
            }
        },

        'click [data-action="slide-panel-close"]': (part) => {
            refresh(part, { dayPanelOpen: false });
        },

        'click [data-action="slide-panel-backdrop"]': (part) => {
            refresh(part, { dayPanelOpen: false });
        },

        'click [data-action="edit-task"]': (part, event) => {
            refresh(part, {
                editingTaskId: Number(event.target.closest('[data-task-id]')?.dataset.taskId ?? 0),
            });
        },

        'click [data-action="delete-task"]': async (part, event) => {
            const id = Number(event.target.closest('[data-task-id]')?.dataset.taskId ?? 0);

            try {
                await postJsonOk(`/api/tasks/${id}/delete`, {});
                refresh(part, {
                    tasks: (part.state.tasks ?? []).filter((task) => Number(task.id) !== id),
                    editingTaskId: null,
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'change [data-action="task-status"]': async (part, event) => {
            const target = event.target;
            const id = Number(target.closest('[data-task-id]')?.dataset.taskId ?? 0);
            const previous = part.state.tasks ?? [];
            const tasks = previous.map((task) => Number(task.id) === id ? { ...task, status: target.value } : task);

            refresh(part, { tasks });

            try {
                const data = await postJsonOk(`/api/tasks/${id}`, { status: target.value });
                refresh(part, { tasks: replaceById(part.state.tasks ?? [], data.task) });
            } catch (error) {
                refresh(part, { tasks: previous, error: error.message });
            }
        },

        'submit [data-action="task-form"]': async (part, event) => {
            event.preventDefault();
            const payload = formData(event.target);
            const id = Number(payload.id || 0);

            delete payload.id;

            try {
                const data = await postJsonOk(id ? `/api/tasks/${id}` : '/api/tasks', payload);
                refresh(part, {
                    tasks: replaceById(part.state.tasks ?? [], { ...data.task, attachments: data.task.attachments ?? [] }),
                    editingTaskId: null,
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'submit [data-action="note-form"]': async (part, event) => {
            event.preventDefault();

            try {
                const data = await postJsonOk('/api/day-notes', formData(event.target));
                refresh(part, {
                    notes: replaceById(part.state.notes ?? [], { ...data.note, attachments: data.note.attachments ?? [] }),
                    message: 'Saved',
                });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'click [data-action="habit-entry"]': async (part, event) => {
            const node = event.target.closest('[data-habit-id]');
            const payload = {
                habit_id: Number(node?.dataset.habitId ?? 0),
                performed_date: node?.dataset.date ?? '',
                status: node?.dataset.status ?? '',
            };
            const previous = part.state.entries ?? [];
            const optimistic = replaceHabitEntry(previous, payload);

            refresh(part, { entries: optimistic });

            try {
                const data = await postJsonOk('/api/habit-entries', payload);
                refresh(part, { entries: replaceHabitEntry(part.state.entries ?? [], data.entry) });
            } catch (error) {
                refresh(part, { entries: previous, error: error.message });
            }
        },

        'click [data-action="habit-entry-clear"]': async (part, event) => {
            const node = event.target.closest('[data-habit-id]');
            const payload = {
                habit_id: Number(node?.dataset.habitId ?? 0),
                performed_date: node?.dataset.date ?? '',
            };
            const previous = part.state.entries ?? [];

            refresh(part, {
                entries: previous.filter((entry) => !(Number(entry.habit_id) === payload.habit_id && entry.performed_date === payload.performed_date)),
            });

            try {
                await postJsonOk('/api/habit-entries/delete', payload);
            } catch (error) {
                refresh(part, { entries: previous, error: error.message });
            }
        },

        'click [data-action="edit-habit"]': (part, event) => {
            refresh(part, {
                editingHabitId: Number(event.target.closest('[data-habit-id]')?.dataset.habitId ?? 0),
            });
        },

        'click [data-action="archive-habit"]': async (part, event) => {
            const id = Number(event.target.closest('[data-habit-id]')?.dataset.habitId ?? 0);

            try {
                await postJsonOk(`/api/habits/${id}/archive`, {});
                await reloadData(part);
                refresh(part, { editingHabitId: null });
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'submit [data-action="habit-form"]': async (part, event) => {
            event.preventDefault();
            const payload = formData(event.target);
            const id = Number(payload.id || 0);

            delete payload.id;
            payload.frequency_days = Number(payload.frequency_days);

            try {
                await postJsonOk(id ? `/api/habits/${id}` : '/api/habits', payload);
                await reloadData(part);
                refresh(part, { editingHabitId: null });
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

    onMount: (part) => {
        part.private.escapeListener = (event) => {
            if (event.key === 'Escape' && part.state.dayPanelOpen) {
                refresh(part, { dayPanelOpen: false });
            }
        };
        document.addEventListener('keydown', part.private.escapeListener);
    },

    onDestroy: (part) => {
        if (part.private.escapeListener) {
            document.removeEventListener('keydown', part.private.escapeListener);
        }
    },
};

function replaceHabitEntry(entries, payload) {
    const next = entries.filter((entry) => !(Number(entry.habit_id) === payload.habit_id && entry.performed_date === payload.performed_date));

    next.push({
        id: payload.id ?? `pending-${payload.habit_id}-${payload.performed_date}`,
        habit_id: Number(payload.habit_id),
        performed_date: payload.performed_date,
        status: payload.status,
    });

    return next;
}
