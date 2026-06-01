<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class TaskRepository
{
    public function __construct(private Database $database)
    {
    }

    public function createInstance(
        int $userId,
        string $title,
        string $bodyMd,
        ?string $startDate,
        ?string $endDate,
        string $status = 'will_do',
        ?int $parentTaskId = null
    ): int {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO task_instances (
                user_id, parent_task_id, title, body_md, start_date, end_date, status
             )
             VALUES (
                :user_id, :parent_task_id, :title, :body_md, :start_date, :end_date, :status
             )
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'parent_task_id' => $parentTaskId,
            'title' => $title,
            'body_md' => $bodyMd,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function createInboxTask(
        int $userId,
        string $title,
        string $bodyMd,
        string $status = 'will_do'
    ): int {
        return $this->createInstance($userId, $title, $bodyMd, null, null, $status);
    }

    public function findInstance(int $userId, int $taskId): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, parent_task_id, title, body_md, start_date, end_date,
                    status, created_at, updated_at
             FROM task_instances
             WHERE user_id = :user_id AND id = :id'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function findInstancesForRange(int $userId, string $startDate, string $endDate): array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, parent_task_id, title, body_md, start_date, end_date,
                    status, created_at, updated_at
             FROM task_instances
             WHERE user_id = :user_id
                AND start_date IS NOT NULL
                AND end_date IS NOT NULL
                AND start_date <= :end_date
                AND end_date >= :start_date
             ORDER BY start_date, id'
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }

    public function findInboxTasks(int $userId): array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, parent_task_id, title, body_md, start_date, end_date,
                    status, created_at, updated_at
             FROM task_instances
             WHERE user_id = :user_id
                AND start_date IS NULL
                AND end_date IS NULL
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function updateInboxTask(
        int $userId,
        int $taskId,
        string $title,
        string $bodyMd,
        string $status
    ): ?array {
        $statement = $this->database->connect()->prepare(
            'UPDATE task_instances
             SET title = :title,
                 body_md = :body_md,
                 status = :status
             WHERE user_id = :user_id
                AND id = :id
                AND start_date IS NULL
                AND end_date IS NULL
             RETURNING id, user_id, parent_task_id, title, body_md, start_date, end_date,
                       status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
            'title' => $title,
            'body_md' => $bodyMd,
            'status' => $status,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function updateInboxStatus(int $userId, int $taskId, string $status): ?array
    {
        $statement = $this->database->connect()->prepare(
            'UPDATE task_instances
             SET status = :status
             WHERE user_id = :user_id
                AND id = :id
                AND start_date IS NULL
                AND end_date IS NULL
             RETURNING id, user_id, parent_task_id, title, body_md, start_date, end_date,
                       status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
            'status' => $status,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function scheduleTask(
        int $userId,
        int $taskId,
        string $startDate,
        string $endDate
    ): ?array {
        $statement = $this->database->connect()->prepare(
            'UPDATE task_instances
             SET start_date = :start_date,
                 end_date = :end_date
             WHERE user_id = :user_id
                AND id = :id
                AND start_date IS NULL
                AND end_date IS NULL
             RETURNING id, user_id, parent_task_id, title, body_md, start_date, end_date,
                       status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function deleteInboxTask(int $userId, int $taskId): bool
    {
        $statement = $this->database->connect()->prepare(
            'DELETE FROM task_instances
             WHERE user_id = :user_id
                AND id = :id
                AND start_date IS NULL
                AND end_date IS NULL'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateInstance(
        int $userId,
        int $taskId,
        string $title,
        string $bodyMd,
        ?string $startDate,
        ?string $endDate,
        string $status
    ): ?array {
        $statement = $this->database->connect()->prepare(
            'UPDATE task_instances
             SET title = :title,
                 body_md = :body_md,
                 start_date = :start_date,
                 end_date = :end_date,
                 status = :status
             WHERE user_id = :user_id AND id = :id
             RETURNING id, user_id, parent_task_id, title, body_md, start_date, end_date,
                       status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
            'title' => $title,
            'body_md' => $bodyMd,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function updateStatus(int $userId, int $taskId, string $status): ?array
    {
        $statement = $this->database->connect()->prepare(
            'UPDATE task_instances
             SET status = :status
             WHERE user_id = :user_id AND id = :id
             RETURNING id, user_id, parent_task_id, title, body_md, start_date, end_date,
                       status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
            'status' => $status,
        ]);
        $task = $statement->fetch();

        return $task === false ? null : $task;
    }

    public function deleteInstance(int $userId, int $taskId): bool
    {
        $statement = $this->database->connect()->prepare(
            'DELETE FROM task_instances
             WHERE user_id = :user_id AND id = :id'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $taskId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function linkInstances(int $userId, int $sourceTaskId, int $targetTaskId, string $linkType = 'related'): void
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO task_links (source_task_id, target_task_id, link_type)
             SELECT source.id, target.id, :link_type
             FROM task_instances source
             JOIN task_instances target ON target.id = :target_task_id
             WHERE source.id = :source_task_id
                AND source.user_id = :user_id
                AND target.user_id = :user_id
             ON CONFLICT DO NOTHING'
        );
        $statement->execute([
            'user_id' => $userId,
            'source_task_id' => $sourceTaskId,
            'target_task_id' => $targetTaskId,
            'link_type' => $linkType,
        ]);
    }
}
