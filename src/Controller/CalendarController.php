<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\Repository\AttachmentRepository;
use App\Repository\HabitRepository;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\View\TemplateRenderer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CalendarController
{
    private const WEEKS_BEFORE = 2;
    private const WEEKS_AFTER = 3;

    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator,
        private TaskRepository $tasks,
        private NoteRepository $notes,
        private HabitRepository $habits,
        private AttachmentRepository $attachments
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $lang = $this->translator->currentLanguage();
        $query = $request->getQueryParams();
        $selectedWeekStart = $this->weekStart((string) ($query['start'] ?? ''));
        $weeksPerPage = self::WEEKS_BEFORE + self::WEEKS_AFTER + 1;
        $visibleStart = $selectedWeekStart->modify('-' . self::WEEKS_BEFORE . ' weeks');
        $visibleEnd = $selectedWeekStart->modify('+' . self::WEEKS_AFTER . ' weeks')->modify('+6 days');
        $rangeStartDate = $visibleStart->format('Y-m-d');
        $rangeEndDate = $visibleEnd->format('Y-m-d');

        $rawTasks = $this->tasks->findInstancesForRange((int) $user['id'], $rangeStartDate, $rangeEndDate);
        $rawNotes = $this->notes->findDayNotesForRange((int) $user['id'], $rangeStartDate, $rangeEndDate);
        $rawHabits = $this->habits->findRulesForRange((int) $user['id'], $rangeStartDate, $rangeEndDate);
        $rawEntries = $this->habits->findEntriesForRangeWithSlidingLookback(
            (int) $user['id'],
            $rangeStartDate,
            $rangeEndDate
        );
        $taskAttachments = $this->groupByNullableId(
            $this->attachments->findForTasks((int) $user['id'], array_column($rawTasks, 'id')),
            'task_id'
        );
        $noteAttachments = $this->groupByNullableId(
            $this->attachments->findForNotes((int) $user['id'], array_column($rawNotes, 'id')),
            'note_id'
        );

        $weeks = $this->weeksFromDays($this->emptyDays($visibleStart, $visibleEnd, $lang), $visibleStart, $visibleEnd);

        $response->getBody()->write($this->templates->render('calendar.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
            'rangeLabel' => $this->formatMonthDay($visibleStart, $lang)
                . ' - '
                . $this->formatMonthDay($visibleEnd, $lang),
            'previousUrl' => '/calendar?start=' . $selectedWeekStart->modify('-' . $weeksPerPage . ' weeks')->format('Y-m-d'),
            'nextUrl' => '/calendar?start=' . $selectedWeekStart->modify('+' . $weeksPerPage . ' weeks')->format('Y-m-d'),
            'partsBaked' => [
                'calendar-workspace' => [
                    'range' => [
                        'from' => $rangeStartDate,
                        'to' => $rangeEndDate,
                        'label' => $this->formatMonthDay($visibleStart, $lang)
                            . ' - '
                            . $this->formatMonthDay($visibleEnd, $lang),
                        'previousUrl' => '/calendar?start=' . $selectedWeekStart->modify('-' . $weeksPerPage . ' weeks')->format('Y-m-d'),
                        'nextUrl' => '/calendar?start=' . $selectedWeekStart->modify('+' . $weeksPerPage . ' weeks')->format('Y-m-d'),
                    ],
                    'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
                    'weeks' => $weeks,
                    'tasks' => $this->normalizeTasks($rawTasks, $taskAttachments),
                    'notes' => $this->normalizeNotes($rawNotes, $noteAttachments),
                    'habits' => $this->normalizeRows($rawHabits),
                    'entries' => $this->normalizeRows($rawEntries),
                    'strings' => $this->calendarStrings($lang),
                ],
            ],
            'partsMounts' => [
                'instances' => [
                    [
                        'id' => 'calendar-workspace',
                        'part' => '/parts/calendar-workspace/index.js',
                    ],
                ],
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function weekStart(string $rawDate): DateTimeImmutable
    {
        $date = new DateTimeImmutable('today');

        if ($rawDate !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate);
            $errors = DateTimeImmutable::getLastErrors();

            if ($parsed !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $date = $parsed;
            }
        }

        return $date->modify('-' . ((int) $date->format('N') - 1) . ' days');
    }

    private function emptyDays(DateTimeImmutable $start, DateTimeImmutable $end, string $lang): array
    {
        $days = [];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $isoDate = $date->format('Y-m-d');
            $weekdayNumber = (int) $date->format('N');
            $monthNumber = (int) $date->format('n');

            $days[$isoDate] = [
                'isoDate' => $isoDate,
                'dayNumber' => $date->format('j'),
                'weekday' => $this->translator->translate($lang, 'calendar.weekday.' . $weekdayNumber),
                'month' => $this->translator->translate($lang, 'calendar.month.' . $monthNumber),
                'monthClass' => 'calendar-month-' . $monthNumber,
                'isToday' => $isoDate === $today,
                'isWeekend' => $weekdayNumber >= 6,
            ];
        }

        return $days;
    }

    private function weeksFromDays(array $days, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $weeks = [];

        for ($weekStart = $start; $weekStart <= $end; $weekStart = $weekStart->modify('+1 week')) {
            $weekDays = [];

            for ($date = $weekStart; count($weekDays) < 7; $date = $date->modify('+1 day')) {
                $weekDays[] = $days[$date->format('Y-m-d')];
            }

            $weeks[] = [
                'number' => $weekStart->format('W'),
                'days' => $weekDays,
            ];
        }

        return $weeks;
    }

    private function groupByNullableId(array $rows, string $field): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            if ($row[$field] === null) {
                continue;
            }

            $grouped[(int) $row[$field]][] = $row;
        }

        return $grouped;
    }

    private function normalizeTasks(array $tasks, array $attachmentsByTask): array
    {
        return array_map(function (array $task) use ($attachmentsByTask): array {
            return [
                'id' => (int) $task['id'],
                'parent_task_id' => $task['parent_task_id'] === null ? null : (int) $task['parent_task_id'],
                'title' => (string) $task['title'],
                'body_md' => (string) $task['body_md'],
                'start_date' => (string) $task['start_date'],
                'end_date' => (string) $task['end_date'],
                'status' => (string) $task['status'],
                'attachments' => $this->normalizeRows($attachmentsByTask[(int) $task['id']] ?? []),
            ];
        }, $tasks);
    }

    private function normalizeNotes(array $notes, array $attachmentsByNote): array
    {
        return array_map(function (array $note) use ($attachmentsByNote): array {
            return [
                'id' => (int) $note['id'],
                'note_date' => (string) $note['note_date'],
                'title' => $note['title'] === null ? null : (string) $note['title'],
                'body_md' => (string) $note['body_md'],
                'attachments' => $this->normalizeRows($attachmentsByNote[(int) $note['id']] ?? []),
            ];
        }, $notes);
    }

    private function normalizeRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            foreach ($row as $key => $value) {
                if (is_bool($value) || $value === null) {
                    continue;
                }

                if (is_numeric($value) && in_array($key, [
                    'id',
                    'user_id',
                    'habit_id',
                    'frequency_days',
                    'task_id',
                    'note_id',
                    'size_bytes',
                ], true)) {
                    $row[$key] = (int) $value;
                }
            }

            return $row;
        }, $rows);
    }

    private function calendarStrings(string $lang): array
    {
        $keys = [
            'calendar.previous',
            'calendar.heading',
            'calendar.controls',
            'calendar.today',
            'calendar.next',
            'calendar.empty_day',
            'calendar.day_note',
            'calendar.marker.long',
            'calendar.attachment.photo',
            'calendar.attachment.audio',
            'calendar.status.ongoing',
            'calendar.status.done',
            'calendar.status.will_do',
            'calendar.status.stale',
            'calendar.week_number',
            'calendar.workspace.tasks',
            'calendar.workspace.habits',
            'calendar.workspace.note',
            'calendar.workspace.add_task',
            'calendar.workspace.save_task',
            'calendar.workspace.delete_task',
            'calendar.workspace.add_habit',
            'calendar.workspace.save_habit',
            'calendar.workspace.archive_habit',
            'calendar.workspace.save_note',
            'calendar.workspace.done',
            'calendar.workspace.skipped',
            'calendar.workspace.clear',
            'calendar.workspace.close',
            'calendar.workspace.title',
            'calendar.workspace.details',
            'calendar.workspace.frequency',
            'calendar.workspace.mode',
            'calendar.workspace.strict',
            'calendar.workspace.sliding',
            'calendar.workspace.start_date',
            'calendar.workspace.no_habits',
            'calendar.workspace.loading',
        ];
        $strings = [];

        foreach ($keys as $key) {
            $strings[$key] = $this->translator->translate($lang, $key);
        }

        return $strings;
    }

    private function preview(string $title, string $bodyMd): string
    {
        $title = trim($title);

        if ($title !== '') {
            return $title;
        }

        foreach (preg_split('/\R/', trim($bodyMd)) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    private function formatMonthDay(DateTimeImmutable $date, string $lang): string
    {
        return $this->translator->translate($lang, 'calendar.month.' . (int) $date->format('n'))
            . ' '
            . $date->format('j');
    }
}
