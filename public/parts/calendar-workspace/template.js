import { escape } from '/engine/core.js';
import { slidePanel } from '/parts/shared/slide-panel.js';
import { buildDayMap } from './domain.js';

export default (state, part) => `
    <section class="calendar-workspace" data-part-id="${escape(part.id)}">
        <div data-ref="body">${workspace(state)}</div>
    </section>
`;

export function workspace(state) {
    const strings = state.strings ?? {};

    return `
        <div class="calendar-topbar">
            <div>
                <h1>${escape(strings['calendar.heading'] ?? 'Calendar')}</h1>
                <p>${escape(state.range?.label ?? '')}</p>
            </div>
            <nav class="calendar-controls" aria-label="${escape(strings['calendar.controls'] ?? 'Calendar controls')}">
                <a href="${escape(state.range?.previousUrl ?? '#')}">${escape(strings['calendar.previous'] ?? 'Earlier')}</a>
                <a href="/calendar">${escape(strings['calendar.today'] ?? 'Today')}</a>
                <a href="${escape(state.range?.nextUrl ?? '#')}">${escape(strings['calendar.next'] ?? 'Later')}</a>
            </nav>
        </div>
        ${message(state)}
        <div class="calendar-workspace-layout">
            <div class="calendar-workspace-calendar">
                <div class="calendar-weeks">
                    ${weeks(state)}
                </div>
            </div>
            <div class="calendar-workspace-side">
                ${dayPanel(state)}
            </div>
        </div>
    `;
}

function weeks(state) {
    const dayMap = dayMapFor(state);
    const weekLabel = state.strings?.['calendar.week_number'] ?? 'Week';

    return (state.weeks ?? []).map((week) => `
        <section class="calendar-week" aria-label="${escape(`${weekLabel} ${week.number}`)}">
            <div class="calendar-week-number" aria-hidden="true">${escape(week.number)}</div>
            <div class="calendar-week-grid">
                ${(week.days ?? []).map((day) => dayCell(day, dayMap.get(day.isoDate), state)).join('')}
            </div>
        </section>
    `).join('');
}

function dayCell(day, content, state) {
    const tasks = content?.tasks ?? [];
    const slots = content?.habitSlots ?? [];
    const note = content?.note ?? null;
    const classes = [
        'calendar-day',
        day.monthClass,
        day.isToday ? 'is-today' : '',
        day.isWeekend ? 'is-weekend' : '',
        selectedDate(state) === day.isoDate ? 'is-selected' : '',
    ].filter(Boolean).join(' ');

    return `
        <button type="button" class="${escape(classes)}" data-action="select-day" data-date="${escape(day.isoDate)}">
            <span class="calendar-day-header">
                <span>${escape(day.weekday)}</span>
                <strong>${escape(day.dayNumber)}</strong>
                <small>${escape(day.month)}</small>
            </span>
            <span class="calendar-day-body">
                ${tasks.slice(0, 3).map(taskChip).join('')}
                ${tasks.length > 3 ? `<span class="calendar-overflow">+${tasks.length - 3}</span>` : ''}
                ${slots.slice(0, 4).map(habitChip).join('')}
                ${slots.length > 4 ? `<span class="calendar-overflow">+${slots.length - 4}</span>` : ''}
                ${note && String(note.body_md ?? '').trim() !== '' ? '<span class="calendar-note-marker">Note</span>' : ''}
                ${tasks.length === 0 && slots.length === 0 && (!note || String(note.body_md ?? '').trim() === '') ? `<span class="calendar-empty">${escape(state.strings?.['calendar.empty_day'] ?? 'No entries')}</span>` : ''}
            </span>
        </button>
    `;
}

function taskChip(task) {
    return `
        <span class="calendar-task calendar-task-${escape(task.status)}">
            <span class="calendar-task-line">
                ${task.isLong ? `<span class="calendar-marker">${task.isStart ? '[' : (task.isEnd ? ']' : '-')}</span>` : ''}
                <span class="calendar-task-title">${escape(task.title)}</span>
            </span>
        </span>
    `;
}

function habitChip(slot) {
    return `
        <span class="calendar-habit calendar-habit-${escape(slot.status)}">
            <span class="calendar-habit-dot" aria-hidden="true"></span>
            <span>${escape(slot.habit.name)}</span>
        </span>
    `;
}

function dayPanel(state) {
    const date = selectedDate(state);
    const day = dayMapFor(state).get(date);

    if (!day) {
        return '';
    }

    return slidePanel({
        id: 'calendar-day-panel',
        title: date,
        open: Boolean(state.dayPanelOpen),
        closeLabel: state.strings?.['calendar.workspace.close'] ?? 'Close',
        content: dayWorkspaceContent(day, state, date),
    });
}

function dayWorkspaceContent(day, state, date) {
    return `
        <section class="calendar-day-workspace" aria-label="${escape(date)}">
            <section class="calendar-panel">
                <h3>${escape(state.strings?.['calendar.workspace.tasks'] ?? 'Tasks')}</h3>
                ${taskList(day.tasks, state)}
                ${spoiler(
                    state.strings?.['calendar.workspace.add_task_spoiler'] ?? 'Add or edit task',
                    taskForm(state, date),
                    Boolean(state.editingTaskId)
                )}
                ${inboxSpoiler(state, date)}
            </section>
            <section class="calendar-panel">
                <h3>${escape(state.strings?.['calendar.workspace.habits'] ?? 'Habits')}</h3>
                ${habitList(day.habitSlots, state)}
                ${spoiler(
                    state.strings?.['calendar.workspace.add_habit_spoiler'] ?? 'Add or edit habit',
                    habitForm(state, date),
                    Boolean(state.editingHabitId)
                )}
            </section>
            <section class="calendar-panel">
                <h3>${escape(state.strings?.['calendar.workspace.note'] ?? 'Note')}</h3>
                ${noteForm(day.note, state, date)}
            </section>
        </section>
    `;
}

function spoiler(label, content, open = false) {
    return `
        <details class="calendar-spoiler"${open ? ' open' : ''}>
            <summary>${escape(label)}</summary>
            ${content}
        </details>
    `;
}

function taskList(tasks, state) {
    if (tasks.length === 0) {
        return `<p class="calendar-empty">${escape(state.strings?.['calendar.empty_day'] ?? 'No entries')}</p>`;
    }

    return `
        <div class="calendar-drawer-list">
            ${tasks.map((task) => `
                <article class="calendar-drawer-item">
                    <div>
                        <strong>${escape(task.title)}</strong>
                        ${task.body_md ? `<p>${escape(task.body_md)}</p>` : ''}
                    </div>
                    <select data-action="task-status" data-task-id="${escape(task.id)}" aria-label="${escape(task.title)}">
                        ${statusOptions(state, task.status)}
                    </select>
                    <div class="calendar-row-actions">
                        <button type="button" data-action="edit-task" data-task-id="${escape(task.id)}">${escape(state.strings?.['calendar.workspace.save_task'] ?? 'Edit')}</button>
                        <button type="button" data-action="delete-task" data-task-id="${escape(task.id)}">${escape(state.strings?.['calendar.workspace.delete_task'] ?? 'Delete')}</button>
                    </div>
                    ${attachments(task.attachments, state)}
                </article>
            `).join('')}
        </div>
    `;
}

function taskForm(state, date) {
    const task = (state.tasks ?? []).find((candidate) => Number(candidate.id) === Number(state.editingTaskId));

    return `
        <form class="calendar-form" data-action="task-form">
            <input type="hidden" name="id" value="${escape(task?.id ?? '')}">
            <label>
                ${escape(state.strings?.['calendar.workspace.title'] ?? 'Title')}
                <input name="title" value="${escape(task?.title ?? '')}" required>
            </label>
            <label>
                ${escape(state.strings?.['calendar.workspace.details'] ?? 'Details')}
                <textarea name="body_md">${escape(task?.body_md ?? '')}</textarea>
            </label>
            <div class="calendar-form-row">
                <label>
                    Start
                    <input type="date" name="start_date" value="${escape(task?.start_date ?? date)}" required>
                </label>
                <label>
                    End
                    <input type="date" name="end_date" value="${escape(task?.end_date ?? date)}" required>
                </label>
            </div>
            <label>
                Status
                <select name="status">${statusOptions(state, task?.status ?? 'will_do')}</select>
            </label>
            <button type="submit">${escape(task ? (state.strings?.['calendar.workspace.save_task'] ?? 'Save task') : (state.strings?.['calendar.workspace.add_task'] ?? 'Add task'))}</button>
        </form>
    `;
}

function habitList(slots, state) {
    const activeHabits = (state.habits ?? []).filter((habit) => habit.active);

    if (activeHabits.length === 0) {
        return `<p class="calendar-empty">${escape(state.strings?.['calendar.workspace.no_habits'] ?? 'No habits')}</p>`;
    }

    return `
        <div class="calendar-drawer-list">
            ${slots.map((slot) => `
                <article class="calendar-drawer-item calendar-habit-row calendar-habit-${escape(slot.status)}">
                    <div>
                        <strong>${escape(slot.habit.name)}</strong>
                        <p>${escape(slot.status)}${slot.early ? ' · early' : ''}</p>
                    </div>
                    <div class="calendar-segmented" role="group" aria-label="${escape(slot.habit.name)}">
                        ${habitStateButton(state, slot, 'scheduled')}
                        ${habitStateButton(state, slot, 'done')}
                        ${habitStateButton(state, slot, 'skipped')}
                    </div>
                    <div class="calendar-row-actions">
                        <button type="button" data-action="edit-habit" data-habit-id="${escape(slot.habit.id)}">${escape(state.strings?.['calendar.workspace.save_habit'] ?? 'Edit')}</button>
                    </div>
                </article>
            `).join('')}
        </div>
    `;
}

function habitStateButton(state, slot, status) {
    const activeStatus = slot.entry?.status ?? 'scheduled';
    const labels = {
        scheduled: state.strings?.['calendar.workspace.state_scheduled'] ?? 'Scheduled',
        done: state.strings?.['calendar.workspace.done'] ?? 'Done',
        skipped: state.strings?.['calendar.workspace.skipped'] ?? 'Skipped',
    };

    return `
        <button
            type="button"
            class="${activeStatus === status ? 'is-active' : ''}"
            data-action="habit-entry-state"
            data-habit-id="${escape(slot.habit.id)}"
            data-date="${escape(slot.entry?.performed_date ?? slot.date)}"
            data-status="${escape(status)}"
            aria-pressed="${activeStatus === status ? 'true' : 'false'}"
        >${escape(labels[status])}</button>
    `;
}

function inboxSpoiler(state, date) {
    const tasks = state.inboxTasks ?? [];

    return spoiler(
        state.strings?.['calendar.workspace.inbox'] ?? 'Inbox',
        tasks.length === 0
            ? `<p class="calendar-empty">${escape(state.strings?.['calendar.workspace.no_inbox_tasks'] ?? 'No inbox tasks')}</p>`
            : `
                <div class="calendar-drawer-list">
                    ${tasks.map((task) => `
                        <article class="calendar-drawer-item">
                            <div>
                                <strong>${escape(task.title)}</strong>
                                ${task.body_md ? `<p>${escape(task.body_md)}</p>` : ''}
                                <p>${escape(state.strings?.[`calendar.status.${task.status}`] ?? task.status)}</p>
                            </div>
                            <div class="calendar-row-actions">
                                <button type="button" data-action="schedule-inbox-to-day" data-task-id="${escape(task.id)}" data-date="${escape(date)}">${escape(state.strings?.['calendar.workspace.schedule_to_day'] ?? 'To this day')}</button>
                            </div>
                        </article>
                    `).join('')}
                </div>
            `
    );
}

function habitForm(state, date) {
    const habit = (state.habits ?? []).find((candidate) => Number(candidate.id) === Number(state.editingHabitId));

    return `
        <form class="calendar-form" data-action="habit-form">
            <input type="hidden" name="id" value="${escape(habit?.id ?? '')}">
            <label>
                ${escape(state.strings?.['calendar.workspace.title'] ?? 'Title')}
                <input name="name" value="${escape(habit?.name ?? '')}" required>
            </label>
            <div class="calendar-form-row">
                <label>
                    ${escape(state.strings?.['calendar.workspace.frequency'] ?? 'Frequency')}
                    <input type="number" min="1" name="frequency_days" value="${escape(habit?.frequency_days ?? 1)}" required>
                </label>
                <label>
                    ${escape(state.strings?.['calendar.workspace.mode'] ?? 'Mode')}
                    <select name="mode">
                        <option value="strict"${(habit?.mode ?? 'strict') === 'strict' ? ' selected' : ''}>${escape(state.strings?.['calendar.workspace.strict'] ?? 'Strict')}</option>
                        <option value="sliding"${habit?.mode === 'sliding' ? ' selected' : ''}>${escape(state.strings?.['calendar.workspace.sliding'] ?? 'Sliding')}</option>
                    </select>
                </label>
            </div>
            <label>
                ${escape(state.strings?.['calendar.workspace.start_date'] ?? 'Start date')}
                <input type="date" name="start_date" value="${escape(habit?.start_date ?? date)}" required>
            </label>
            <div class="calendar-row-actions">
                <button type="submit">${escape(habit ? (state.strings?.['calendar.workspace.save_habit'] ?? 'Save habit') : (state.strings?.['calendar.workspace.add_habit'] ?? 'Add habit'))}</button>
                ${habit ? `<button type="button" data-action="archive-habit" data-habit-id="${escape(habit.id)}">${escape(state.strings?.['calendar.workspace.archive_habit'] ?? 'Archive habit')}</button>` : ''}
            </div>
        </form>
    `;
}

function noteForm(note, state, date) {
    return `
        <form class="calendar-form" data-action="note-form">
            <input type="hidden" name="date" value="${escape(date)}">
            <textarea name="body_md" rows="10">${escape(note?.body_md ?? '')}</textarea>
            <button type="submit">${escape(state.strings?.['calendar.workspace.save_note'] ?? 'Save note')}</button>
        </form>
    `;
}

function statusOptions(state, selected) {
    return ['will_do', 'ongoing', 'done', 'stale'].map((status) => `
        <option value="${escape(status)}"${selected === status ? ' selected' : ''}>${escape(state.strings?.[`calendar.status.${status}`] ?? status)}</option>
    `).join('');
}

function attachments(items, state) {
    if (!items || items.length === 0) {
        return '';
    }

    return `
        <div class="calendar-attachments">
            ${items.map((attachment) => `
                <a href="/attachments/${escape(attachment.id)}">${escape(state.strings?.[`calendar.attachment.${attachment.media_type}`] ?? attachment.original_name)}</a>
            `).join('')}
        </div>
    `;
}

function message(state) {
    if (!state.message && !state.error) {
        return '';
    }

    return `<p class="${state.error ? 'error' : 'form-message'}">${escape(state.error ?? state.message)}</p>`;
}

function dayMapFor(state) {
    return buildDayMap(state.range, state.tasks ?? [], state.habits ?? [], state.entries ?? [], state.notes ?? [], state.today);
}

function selectedDate(state) {
    if (state.selectedDate) {
        return state.selectedDate;
    }

    if (state.today >= state.range.from && state.today <= state.range.to) {
        return state.today;
    }

    return state.range.from;
}
