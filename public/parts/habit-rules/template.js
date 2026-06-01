import { escape } from '/engine/core.js';

export default (state, part) => `
    <section class="habit-workspace" data-part-id="${escape(part.id)}">
        <div data-ref="body">${workspace(state)}</div>
    </section>
`;

export function workspace(state) {
    return `
        <div class="habit-topbar">
            <h1>${escape(text(state, 'habits.heading', 'Habits'))}</h1>
        </div>
        ${message(state)}
        <div class="habit-layout">
            <section class="habit-list" aria-label="${escape(text(state, 'habits.active', 'Active habits'))}">
                <h2>${escape(text(state, 'habits.active', 'Active'))}</h2>
                ${habitList(activeHabits(state), state, true)}
                <h2>${escape(text(state, 'habits.archive', 'Archive'))}</h2>
                ${habitList(archivedHabits(state), state, false)}
            </section>
            <aside class="habit-editor">
                ${habitForm(state)}
            </aside>
        </div>
    `;
}

function habitList(habits, state, editable) {
    if (habits.length === 0) {
        return `<p class="calendar-empty">${escape(text(state, editable ? 'habits.empty_active' : 'habits.empty_archive', 'No habits'))}</p>`;
    }

    return habits.map((habit) => `
        <article class="habit-rule${habit.active ? '' : ' is-archived'}" data-habit-id="${escape(habit.id)}">
            <div class="habit-rule-main">
                <strong>${escape(habit.name)}</strong>
                <span>${escape(text(state, 'habits.frequency_field', 'Every N days'))}: ${escape(habit.frequency_days)}</span>
                <span>${escape(text(state, 'habits.mode_field', 'Mode'))}: ${escape(modeLabel(state, habit.mode))}</span>
                <span>${escape(text(state, 'habits.period', 'Period'))}: ${escape(periodLabel(habit))}</span>
            </div>
            <div class="habit-row-actions">
                ${editable ? `
                    <button type="button" data-action="habit-rule-edit" data-habit-id="${escape(habit.id)}">${escape(text(state, 'habits.edit', 'Edit'))}</button>
                    <button type="button" data-action="habit-rule-archive" data-habit-id="${escape(habit.id)}">${escape(text(state, 'habits.archive_habit', 'Archive'))}</button>
                ` : `
                    <button type="button" data-action="habit-rule-resume" data-habit-id="${escape(habit.id)}">${escape(text(state, 'habits.resume_habit', 'Resume'))}</button>
                `}
            </div>
        </article>
    `).join('');
}

function habitForm(state) {
    const editingHabit = (state.habits ?? []).find((habit) => Number(habit.id) === Number(state.editingHabitId));
    const draft = state.draftHabit ?? {};
    const habit = editingHabit ?? draft;
    const isEditing = Boolean(editingHabit);
    const isResuming = Boolean(draft.resume_habit_id);

    return `
        <form class="habit-form" data-action="habit-rule-form">
            <input type="hidden" name="id" value="${escape(isEditing ? editingHabit.id : '')}">
            <input type="hidden" name="resume_habit_id" value="${escape(isResuming ? draft.resume_habit_id : '')}">
            <label>
                ${escape(text(state, 'habits.name_field', 'Name'))}
                <input name="name" value="${escape(habit.name ?? '')}" required>
            </label>
            <label>
                ${escape(text(state, 'habits.frequency_field', 'Every N days'))}
                <input type="number" min="1" name="frequency_days" value="${escape(habit.frequency_days ?? 1)}" required>
            </label>
            <label>
                ${escape(text(state, 'habits.mode_field', 'Mode'))}
                <select name="mode">
                    <option value="strict"${(habit.mode ?? 'strict') === 'strict' ? ' selected' : ''}>${escape(modeLabel(state, 'strict'))}</option>
                    <option value="sliding"${habit.mode === 'sliding' ? ' selected' : ''}>${escape(modeLabel(state, 'sliding'))}</option>
                </select>
            </label>
            <label>
                ${escape(text(state, 'habits.start_date', 'Start date'))}
                <input type="date" name="start_date" value="${escape(habit.start_date ?? state.today ?? today())}" required>
            </label>
            <div class="habit-row-actions">
                <button type="submit">${escape(isEditing ? text(state, 'habits.save_habit', 'Save habit') : text(state, 'habits.add_habit', 'Add habit'))}</button>
                ${(isEditing || state.draftHabit) ? `<button type="button" data-action="habit-rule-cancel">${escape(text(state, 'habits.cancel', 'Cancel'))}</button>` : ''}
            </div>
        </form>
    `;
}

function activeHabits(state) {
    return (state.habits ?? []).filter((habit) => habit.active);
}

function archivedHabits(state) {
    return (state.habits ?? []).filter((habit) => !habit.active);
}

function modeLabel(state, mode) {
    return mode === 'sliding'
        ? text(state, 'calendar.workspace.sliding', 'Sliding')
        : text(state, 'calendar.workspace.strict', 'Strict');
}

function periodLabel(habit) {
    return habit.end_date ? `${habit.start_date} - ${habit.end_date}` : `${habit.start_date} -`;
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

function today() {
    return new Date().toISOString().slice(0, 10);
}
