<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\I18n\Translator;
use App\Repository\AttachmentRepository;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\View\TemplateRenderer;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CalendarController
{
    private const WEEKS_BEFORE = 4;
    private const WEEKS_AFTER = 8;

    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf,
        private Translator $translator,
        private TaskRepository $tasks,
        private NoteRepository $notes,
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
        $visibleStart = $selectedWeekStart->modify('-' . self::WEEKS_BEFORE . ' weeks');
        $visibleEnd = $selectedWeekStart->modify('+' . self::WEEKS_AFTER . ' weeks')->modify('+6 days');
        $rangeStartDate = $visibleStart->format('Y-m-d');
        $rangeEndDate = $visibleEnd->format('Y-m-d');

        $rawTasks = $this->tasks->findInstancesForRange((int) $user['id'], $rangeStartDate, $rangeEndDate);
        $rawNotes = $this->notes->findDayNotesForRange((int) $user['id'], $rangeStartDate, $rangeEndDate);
        $taskAttachments = $this->groupByNullableId(
            $this->attachments->findForTasks((int) $user['id'], array_column($rawTasks, 'id')),
            'task_id'
        );
        $noteAttachments = $this->groupByNullableId(
            $this->attachments->findForNotes((int) $user['id'], array_column($rawNotes, 'id')),
            'note_id'
        );

        $days = $this->emptyDays($visibleStart, $visibleEnd, $lang);
        $this->addNotes($days, $rawNotes, $noteAttachments);
        $this->addTasks($days, $rawTasks, $taskAttachments, $visibleStart, $visibleEnd);

        $response->getBody()->write($this->templates->render('calendar.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'lang' => $lang,
            'languageAction' => '/lang/' . $this->translator->oppositeLanguage($lang),
            'languageLabel' => $this->translator->translate($lang, 'language.switch_to'),
            't' => fn (string $key): string => $this->translator->translate($lang, $key),
            'weeks' => $this->weeksFromDays($days, $visibleStart, $visibleEnd, $lang),
            'rangeLabel' => $this->formatMonthDay($visibleStart, $lang)
                . ' - '
                . $this->formatMonthDay($visibleEnd, $lang),
            'previousUrl' => '/calendar?start=' . $selectedWeekStart->modify('-13 weeks')->format('Y-m-d'),
            'nextUrl' => '/calendar?start=' . $selectedWeekStart->modify('+13 weeks')->format('Y-m-d'),
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
                'tasks' => [],
                'note' => null,
            ];
        }

        return $days;
    }

    private function addNotes(array &$days, array $notes, array $attachmentsByNote): void
    {
        foreach ($notes as $note) {
            $noteDate = (string) $note['note_date'];

            if (!isset($days[$noteDate])) {
                continue;
            }

            $days[$noteDate]['note'] = [
                'id' => (int) $note['id'],
                'title' => $note['title'],
                'preview' => $this->preview((string) ($note['title'] ?? ''), (string) $note['body_md']),
                'attachments' => $attachmentsByNote[(int) $note['id']] ?? [],
            ];
        }
    }

    private function addTasks(
        array &$days,
        array $tasks,
        array $attachmentsByTask,
        DateTimeImmutable $visibleStart,
        DateTimeImmutable $visibleEnd
    ): void {
        foreach ($tasks as $task) {
            $taskStart = new DateTimeImmutable((string) $task['start_date']);
            $taskEnd = new DateTimeImmutable((string) $task['end_date']);
            $start = $taskStart < $visibleStart ? $visibleStart : $taskStart;
            $end = $taskEnd > $visibleEnd ? $visibleEnd : $taskEnd;

            for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
                $isoDate = $date->format('Y-m-d');

                if (!isset($days[$isoDate])) {
                    continue;
                }

                $days[$isoDate]['tasks'][] = [
                    'id' => (int) $task['id'],
                    'title' => $task['title'],
                    'preview' => $this->preview('', (string) $task['body_md']),
                    'status' => $task['status'],
                    'isRecurring' => $task['series_id'] !== null,
                    'isLong' => (string) $task['start_date'] !== (string) $task['end_date'],
                    'isStart' => $isoDate === (string) $task['start_date'],
                    'isEnd' => $isoDate === (string) $task['end_date'],
                    'attachments' => $attachmentsByTask[(int) $task['id']] ?? [],
                ];
            }
        }
    }

    private function weeksFromDays(array $days, DateTimeImmutable $start, DateTimeImmutable $end, string $lang): array
    {
        $weeks = [];

        for ($weekStart = $start; $weekStart <= $end; $weekStart = $weekStart->modify('+1 week')) {
            $weekDays = [];

            for ($date = $weekStart; count($weekDays) < 7; $date = $date->modify('+1 day')) {
                $weekDays[] = $days[$date->format('Y-m-d')];
            }

            $weeks[] = [
                'label' => $this->formatMonthDay($weekStart, $lang)
                    . ' - '
                    . $this->formatMonthDay($weekStart->modify('+6 days'), $lang),
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
