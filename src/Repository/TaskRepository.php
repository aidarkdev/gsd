<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class TaskRepository
{
    public function __construct(private Database $database)
    {
    }

    public function createSeries(
        int $userId,
        string $title,
        string $bodyMd,
        string $startsOn,
        ?string $endsOn,
        int $intervalCount,
        string $intervalUnit,
        int $durationCount = 1,
        string $durationUnit = 'day'
    ): int {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO task_series (
                user_id, title, body_md, starts_on, ends_on,
                interval_count, interval_unit, duration_count, duration_unit
             )
             VALUES (
                :user_id, :title, :body_md, :starts_on, :ends_on,
                :interval_count, :interval_unit, :duration_count, :duration_unit
             )
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'body_md' => $bodyMd,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'interval_count' => $intervalCount,
            'interval_unit' => $intervalUnit,
            'duration_count' => $durationCount,
            'duration_unit' => $durationUnit,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function createInstance(
        int $userId,
        string $title,
        string $bodyMd,
        string $startDate,
        string $endDate,
        string $status = 'will_do',
        ?int $seriesId = null,
        ?int $parentTaskId = null
    ): int {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO task_instances (
                user_id, series_id, parent_task_id, title, body_md, start_date, end_date, status
             )
             VALUES (
                :user_id, :series_id, :parent_task_id, :title, :body_md, :start_date, :end_date, :status
             )
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'series_id' => $seriesId,
            'parent_task_id' => $parentTaskId,
            'title' => $title,
            'body_md' => $bodyMd,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findInstance(int $userId, int $taskId): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, series_id, parent_task_id, title, body_md, start_date, end_date,
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
            'SELECT id, user_id, series_id, parent_task_id, title, body_md, start_date, end_date,
                    status, created_at, updated_at
             FROM task_instances
             WHERE user_id = :user_id
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
