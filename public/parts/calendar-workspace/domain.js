export function buildDayMap(range, tasks, habits, entries, notes, today) {
    const days = new Map();

    for (let date = range.from; date <= range.to; date = addDays(date, 1)) {
        days.set(date, {
            date,
            tasks: [],
            habitSlots: [],
            note: null,
        });
    }

    for (const task of tasks ?? []) {
        const start = task.start_date > range.from ? task.start_date : range.from;
        const end = task.end_date < range.to ? task.end_date : range.to;

        for (let date = start; date <= end; date = addDays(date, 1)) {
            days.get(date)?.tasks.push({
                ...task,
                isLong: task.start_date !== task.end_date,
                isStart: date === task.start_date,
                isEnd: date === task.end_date,
            });
        }
    }

    for (const note of notes ?? []) {
        if (days.has(note.note_date)) {
            days.get(note.note_date).note = note;
        }
    }

    for (const habit of habits ?? []) {
        for (const slot of getSlots(habit, entries ?? [], range.from, range.to, today)) {
            days.get(slot.date)?.habitSlots.push(slot);
        }
    }

    for (const day of days.values()) {
        day.tasks.sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)) || Number(a.id) - Number(b.id));
        day.habitSlots.sort((a, b) => String(a.habit.name).localeCompare(String(b.habit.name)) || Number(a.habit.id) - Number(b.habit.id));
    }

    return days;
}

export function getSlots(habit, entries, from, to, today) {
    const scoped = (entries ?? [])
        .filter((entry) => Number(entry.habit_id) === Number(habit.id))
        .sort((a, b) => String(a.performed_date).localeCompare(String(b.performed_date)) || Number(a.id) - Number(b.id));

    if (habit.mode === 'sliding') {
        return slidingSlots(habit, scoped, from, to, today);
    }

    return strictSlots(habit, scoped, from, to, today);
}

export function addDays(date, days) {
    const parsed = parseDate(date);

    parsed.setUTCDate(parsed.getUTCDate() + days);

    return formatDate(parsed);
}

function strictSlots(habit, entries, from, to, today) {
    const slots = [];
    let slotDate = habit.start_date;

    while (slotDate < from) {
        slotDate = addDays(slotDate, Number(habit.frequency_days));
    }

    for (; slotDate <= to; slotDate = addDays(slotDate, Number(habit.frequency_days))) {
        if (habit.end_date && slotDate > habit.end_date) {
            break;
        }

        const previousSlot = addDays(slotDate, -Number(habit.frequency_days));
        const entry = entries.find((candidate) => candidate.performed_date > previousSlot && candidate.performed_date <= slotDate);

        slots.push({
            date: slotDate,
            habit,
            status: entry ? entry.status : (slotDate < today ? 'missed' : 'scheduled'),
            entry,
            early: entry ? entry.performed_date !== slotDate : false,
        });
    }

    return slots;
}

function slidingSlots(habit, entries, from, to, today) {
    const slots = entries
        .filter((entry) => entry.performed_date >= from && entry.performed_date <= to)
        .map((entry) => ({
            date: entry.performed_date,
            habit,
            status: entry.status,
            entry,
            early: false,
        }));
    const lastEntry = [...entries]
        .filter((entry) => entry.performed_date <= to)
        .sort((a, b) => String(b.performed_date).localeCompare(String(a.performed_date)) || Number(b.id) - Number(a.id))[0];
    let nextDate = lastEntry ? addDays(lastEntry.performed_date, Number(habit.frequency_days)) : habit.start_date;

    while (nextDate < from) {
        nextDate = addDays(nextDate, Number(habit.frequency_days));
    }

    if (nextDate >= from && nextDate <= to && (!habit.end_date || nextDate <= habit.end_date)) {
        const hasEntryOnNextDate = entries.some((entry) => entry.performed_date === nextDate);

        if (!hasEntryOnNextDate) {
            slots.push({
                date: nextDate,
                habit,
                status: nextDate < today ? 'overdue' : 'scheduled',
                entry: null,
                early: false,
            });
        }
    }

    return slots.sort((a, b) => String(a.date).localeCompare(String(b.date)));
}

function parseDate(date) {
    const [year, month, day] = String(date).split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day));
}

function formatDate(date) {
    return [
        String(date.getUTCFullYear()).padStart(4, '0'),
        String(date.getUTCMonth() + 1).padStart(2, '0'),
        String(date.getUTCDate()).padStart(2, '0'),
    ].join('-');
}
