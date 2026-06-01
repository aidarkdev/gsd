<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Repository\TaskRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InboxApiController
{
    private const TASK_STATUSES = ['ongoing', 'done', 'will_do', 'stale'];

    public function __construct(
        private AuthService $auth,
        private TaskRepository $tasks
    ) {
    }

    public function index(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, [
            'tasks' => $this->tasks->findInboxTasks($this->userId()),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $this->inboxPayload($this->body($request));

        if (isset($payload['error'])) {
            return $this->json($response, ['error' => $payload['error']], 422);
        }

        $taskId = $this->tasks->createInboxTask(
            $this->userId(),
            $payload['title'],
            $payload['body_md'],
            $payload['status']
        );

        return $this->json($response, [
            'task' => $this->tasks->findInstance($this->userId(), $taskId),
        ], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $this->body($request);
        $taskId = (int) ($args['id'] ?? 0);

        if (isset($body['status']) && count($body) === 1) {
            $status = (string) $body['status'];

            if (!in_array($status, self::TASK_STATUSES, true)) {
                return $this->json($response, ['error' => 'Invalid task status'], 422);
            }

            $task = $this->tasks->updateInboxStatus($this->userId(), $taskId, $status);

            return $task === null
                ? $this->json($response, ['error' => 'Task not found'], 404)
                : $this->json($response, ['task' => $task]);
        }

        $payload = $this->inboxPayload($body);

        if (isset($payload['error'])) {
            return $this->json($response, ['error' => $payload['error']], 422);
        }

        $task = $this->tasks->updateInboxTask(
            $this->userId(),
            $taskId,
            $payload['title'],
            $payload['body_md'],
            $payload['status']
        );

        return $task === null
            ? $this->json($response, ['error' => 'Task not found'], 404)
            : $this->json($response, ['task' => $task]);
    }

    public function delete(ServerRequestInterface $_request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->tasks->deleteInboxTask($this->userId(), (int) ($args['id'] ?? 0))
            ? $this->json($response, ['ok' => true])
            : $this->json($response, ['error' => 'Task not found'], 404);
    }

    public function schedule(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = $this->body($request);
        $startDate = $this->date((string) ($body['start_date'] ?? ''));
        $endDate = $this->date((string) ($body['end_date'] ?? ($body['start_date'] ?? '')));

        if ($startDate === null || $endDate === null || $endDate < $startDate) {
            return $this->json($response, ['error' => 'Invalid task date range'], 422);
        }

        $task = $this->tasks->scheduleTask($this->userId(), (int) ($args['id'] ?? 0), $startDate, $endDate);

        return $task === null
            ? $this->json($response, ['error' => 'Task not found'], 404)
            : $this->json($response, ['task' => $task]);
    }

    private function inboxPayload(array $body): array
    {
        $title = trim((string) ($body['title'] ?? ''));
        $status = (string) ($body['status'] ?? 'will_do');

        if ($title === '') {
            return ['error' => 'Task title is required'];
        }

        if (!in_array($status, self::TASK_STATUSES, true)) {
            return ['error' => 'Invalid task status'];
        }

        return [
            'title' => $title,
            'body_md' => (string) ($body['body_md'] ?? ''),
            'status' => $status,
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
