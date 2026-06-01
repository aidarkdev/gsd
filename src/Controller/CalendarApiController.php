<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Repository\AttachmentRepository;
use App\Repository\HabitRepository;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CalendarApiController
{
    private const TASK_STATUSES = ['ongoing', 'done', 'will_do', 'stale'];
    private const HABIT_MODES = ['strict', 'sliding'];
    private const ENTRY_STATUSES = ['done', 'skipped'];

    public function __construct(
        private AuthService $auth,
        private TaskRepository $tasks,
        private NoteRepository $notes,
        private HabitRepository $habits,
        private AttachmentRepository $attachments
    ) {
    }

    public function dayData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->userId();
        $query = $request->getQueryParams();
        $from = $this->date((string) ($query['from'] ?? ''));
        $to = $this->date((string) ($query['to'] ?? ''));

        if ($from === null || $to === null || $to < $from) {
            return $this->json($response, ['error' => 'Invalid date range'], 422);
        }

        return $this->json($response, [
            'data' => $this->loadData($userId, $from, $to),
        ]);
    }

    public function habits(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, [
            'habits' => $this->normalizeHabits($this->habits->findAllForUser($this->userId())),
            'today' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    public function createTask(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->userId();
        $body = $this->body($request);
        $task = $this->taskPayload($body);

        if (isset($task['error'])) {
            return $this->json($response, ['error' => $task['error']], 422);
        }

        $taskId = $this->tasks->createInstance(
            $userId,
            $task['title'],
            $task['body_md'],
            $task['start_date'],
            $task['end_date'],
            $task['status']
        );

        return $this->json($response, [
            'task' => $this->tasks->findInstance($userId, $taskId),
        ], 201);
    }

    public function updateTask(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $this->userId();
        $taskId = (int) ($args['id'] ?? 0);
        $body = $this->body($request);

        if (isset($body['status']) && count($body) === 1) {
            $status = (string) $body['status'];

            if (!in_array($status, self::TASK_STATUSES, true)) {
                return $this->json($response, ['error' => 'Invalid task status'], 422);
            }

            $task = $this->tasks->updateStatus($userId, $taskId, $status);

            return $task === null
                ? $this->json($response, ['error' => 'Task not found'], 404)
                : $this->json($response, ['task' => $task]);
        }

        $task = $this->taskPayload($body);

        if (isset($task['error'])) {
            return $this->json($response, ['error' => $task['error']], 422);
        }

        $updated = $this->tasks->updateInstance(
            $userId,
            $taskId,
            $task['title'],
            $task['body_md'],
            $task['start_date'],
            $task['end_date'],
            $task['status']
        );

        return $updated === null
            ? $this->json($response, ['error' => 'Task not found'], 404)
            : $this->json($response, ['task' => $updated]);
    }

    public function deleteTask(ServerRequestInterface $_request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->tasks->deleteInstance($this->userId(), (int) ($args['id'] ?? 0))
            ? $this->json($response, ['ok' => true])
            : $this->json($response, ['error' => 'Task not found'], 404);
    }

    public function upsertDayNote(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $date = $this->date((string) ($body['date'] ?? ''));

        if ($date === null) {
            return $this->json($response, ['error' => 'Invalid note date'], 422);
        }

        $note = $this->notes->upsertDayNote(
            $this->userId(),
            $date,
            (string) ($body['body_md'] ?? ''),
            trim((string) ($body['title'] ?? '')) ?: null
        );

        return $this->json($response, ['note' => $note]);
    }

    public function createHabit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $habit = $this->habitPayload($this->body($request), (new DateTimeImmutable('today'))->format('Y-m-d'));

        if (isset($habit['error'])) {
            return $this->json($response, ['error' => $habit['error']], 422);
        }

        return $this->json($response, [
            'habit' => $this->habits->createRule(
                $this->userId(),
                $habit['name'],
                $habit['frequency_days'],
                $habit['mode'],
                $habit['start_date']
            ),
        ], 201);
    }

    public function updateHabit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $this->userId();
        $habitId = (int) ($args['id'] ?? 0);
        $existing = $this->habits->findForUser($userId, $habitId);

        if ($existing === null) {
            return $this->json($response, ['error' => 'Habit not found'], 404);
        }

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $habit = $this->habitPayload($this->body($request), $today);

        if (isset($habit['error'])) {
            return $this->json($response, ['error' => $habit['error']], 422);
        }

        $updated = (string) $existing['start_date'] >= $today
            ? $this->habits->updateRuleInPlace(
                $userId,
                $habitId,
                $habit['name'],
                $habit['frequency_days'],
                $habit['mode'],
                $habit['start_date']
            )
            : $this->habits->versionRule(
                $userId,
                $habitId,
                $habit['name'],
                $habit['frequency_days'],
                $habit['mode'],
                $today
            );

        return $updated === null
            ? $this->json($response, ['error' => 'Habit not found'], 404)
            : $this->json($response, ['habit' => $updated]);
    }

    public function archiveHabit(ServerRequestInterface $_request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->habits->archiveRule($this->userId(), (int) ($args['id'] ?? 0), (new DateTimeImmutable('today'))->format('Y-m-d'))
            ? $this->json($response, ['ok' => true])
            : $this->json($response, ['error' => 'Habit not found'], 404);
    }

    public function resumeHabit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $payload = $this->habitPayload($this->body($request), $today);

        if (isset($payload['error'])) {
            return $this->json($response, ['error' => $payload['error']], 422);
        }

        $habit = $this->habits->resumeRule(
            $this->userId(),
            (int) ($args['id'] ?? 0),
            $payload['name'],
            $payload['frequency_days'],
            $payload['mode'],
            $payload['start_date']
        );

        return $habit === null
            ? $this->json($response, ['error' => 'Habit not found'], 404)
            : $this->json($response, ['habit' => $habit], 201);
    }

    public function upsertHabitEntry(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $date = $this->date((string) ($body['performed_date'] ?? ''));
        $status = (string) ($body['status'] ?? '');

        if ($date === null || !in_array($status, self::ENTRY_STATUSES, true)) {
            return $this->json($response, ['error' => 'Invalid habit entry'], 422);
        }

        $entry = $this->habits->upsertEntry($this->userId(), (int) ($body['habit_id'] ?? 0), $date, $status);

        return $entry === null
            ? $this->json($response, ['error' => 'Habit not found'], 404)
            : $this->json($response, ['entry' => $entry]);
    }

    public function deleteHabitEntry(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->body($request);
        $date = $this->date((string) ($body['performed_date'] ?? ''));

        if ($date === null) {
            return $this->json($response, ['error' => 'Invalid habit entry date'], 422);
        }

        $this->habits->deleteEntry($this->userId(), (int) ($body['habit_id'] ?? 0), $date);

        return $this->json($response, ['ok' => true]);
    }

    private function loadData(int $userId, string $from, string $to): array
    {
        $tasks = $this->tasks->findInstancesForRange($userId, $from, $to);
        $notes = $this->notes->findDayNotesForRange($userId, $from, $to);
        $habits = $this->habits->findRulesForRange($userId, $from, $to);
        $entries = $this->habits->findEntriesForRangeWithSlidingLookback($userId, $from, $to);

        return [
            'tasks' => $tasks,
            'notes' => $notes,
            'habits' => $habits,
            'entries' => $entries,
            'taskAttachments' => $this->attachments->findForTasks($userId, array_column($tasks, 'id')),
            'noteAttachments' => $this->attachments->findForNotes($userId, array_column($notes, 'id')),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $habits
     * @return array<int, array<string, mixed>>
     */
    private function normalizeHabits(array $habits): array
    {
        return array_map(static fn (array $habit): array => [
            'id' => (int) $habit['id'],
            'name' => (string) $habit['name'],
            'habit_series_uid' => (string) $habit['habit_series_uid'],
            'frequency_days' => (int) $habit['frequency_days'],
            'mode' => (string) $habit['mode'],
            'start_date' => (string) $habit['start_date'],
            'end_date' => $habit['end_date'] === null ? null : (string) $habit['end_date'],
            'active' => (bool) $habit['active'],
            'created_at' => (string) $habit['created_at'],
            'updated_at' => (string) $habit['updated_at'],
        ], $habits);
    }

    private function taskPayload(array $body): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        $startDate = $this->date((string) ($body['start_date'] ?? ''));
        $endDate = $this->date((string) ($body['end_date'] ?? ($body['start_date'] ?? '')));
        $status = (string) ($body['status'] ?? 'will_do');

        if ($title === '') {
            return ['error' => 'Task title is required'];
        }

        if ($startDate === null || $endDate === null || $endDate < $startDate) {
            return ['error' => 'Invalid task date range'];
        }

        if (!in_array($status, self::TASK_STATUSES, true)) {
            return ['error' => 'Invalid task status'];
        }

        return [
            'title' => $title,
            'body_md' => (string) ($body['body_md'] ?? ''),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ];
    }

    private function habitPayload(array $body, string $defaultStartDate): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        $frequencyDays = (int) ($body['frequency_days'] ?? 0);
        $mode = (string) ($body['mode'] ?? '');
        $startDate = $this->date((string) ($body['start_date'] ?? $defaultStartDate));

        if ($name === '') {
            return ['error' => 'Habit name is required'];
        }

        if ($frequencyDays < 1) {
            return ['error' => 'Habit frequency must be positive'];
        }

        if (!in_array($mode, self::HABIT_MODES, true)) {
            return ['error' => 'Invalid habit mode'];
        }

        if ($startDate === null) {
            return ['error' => 'Invalid habit start date'];
        }

        return [
            'name' => $name,
            'frequency_days' => $frequencyDays,
            'mode' => $mode,
            'start_date' => $startDate,
        ];
    }

    private function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    private function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function userId(): int
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw new \RuntimeException('Authenticated user is required');
        }

        return (int) $user['id'];
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
