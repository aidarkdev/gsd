import { postJsonOk } from '/engine/http.js';

function refresh(part, values = {}) {
    part.set({
        error: null,
        message: null,
        ...values,
        renderVersion: Number(part.state.renderVersion ?? 0) + 1,
    });
}

function formData(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function normalizeHabits(habits) {
    return (habits ?? []).map((habit) => ({
        ...habit,
        id: Number(habit.id),
        frequency_days: Number(habit.frequency_days),
        active: Boolean(habit.active),
    }));
}

async function loadHabits(part) {
    const response = await fetch('/api/habits', {
        headers: { Accept: 'application/json' },
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.error || `HTTP ${response.status}`);
    }

    refresh(part, {
        habits: normalizeHabits(data.habits),
        today: data.today ?? part.state.today,
        editingHabitId: null,
        draftHabit: null,
    });
}

export default {
    events: {
        'submit [data-action="habit-rule-form"]': async (part, event) => {
            event.preventDefault();
            const payload = formData(event.target);
            const id = Number(payload.id || 0);
            const resumeHabitId = Number(payload.resume_habit_id || 0);

            delete payload.id;
            delete payload.resume_habit_id;

            try {
                const url = resumeHabitId ? `/api/habits/${resumeHabitId}/resume` : (id ? `/api/habits/${id}` : '/api/habits');
                await postJsonOk(url, payload);
                await loadHabits(part);
            } catch (error) {
                refresh(part, { error: error.message });
            }
        },

        'click [data-action="habit-rule-edit"]': (part, event) => {
            refresh(part, {
                editingHabitId: Number(event.target.closest('[data-habit-id]')?.dataset.habitId ?? 0),
                draftHabit: null,
            });
        },

        'click [data-action="habit-rule-resume"]': (part, event) => {
            const id = Number(event.target.closest('[data-habit-id]')?.dataset.habitId ?? 0);
            const habit = (part.state.habits ?? []).find((candidate) => Number(candidate.id) === id);

            if (!habit) {
                return;
            }

            refresh(part, {
                editingHabitId: null,
                draftHabit: {
                    resume_habit_id: habit.id,
                    name: habit.name,
                    frequency_days: habit.frequency_days,
                    mode: habit.mode,
                    start_date: part.state.today ?? new Date().toISOString().slice(0, 10),
                },
            });
        },

        'click [data-action="habit-rule-cancel"]': (part) => {
            refresh(part, {
                editingHabitId: null,
                draftHabit: null,
            });
        },

        'click [data-action="habit-rule-archive"]': async (part, event) => {
            const id = Number(event.target.closest('[data-habit-id]')?.dataset.habitId ?? 0);

            try {
                await postJsonOk(`/api/habits/${id}/archive`, {});
                await loadHabits(part);
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
