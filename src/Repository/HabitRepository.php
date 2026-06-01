<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class HabitRepository
{
    public function __construct(private Database $database)
    {
    }

    public function createRule(
        int $userId,
        string $name,
        int $frequencyDays,
        string $mode,
        string $startDate,
        bool $active = true,
        ?string $habitSeriesUid = null
    ): array {
        $habitSeriesUid ??= $this->seriesUid();
        $statement = $this->database->connect()->prepare(
            'INSERT INTO habits (user_id, name, habit_series_uid, frequency_days, mode, start_date, active)
             VALUES (:user_id, :name, :habit_series_uid, :frequency_days, :mode, :start_date, :active)
             RETURNING id, user_id, name, habit_series_uid, frequency_days, mode, start_date, end_date,
                       active, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'name' => $name,
            'habit_series_uid' => $habitSeriesUid,
            'frequency_days' => $frequencyDays,
            'mode' => $mode,
            'start_date' => $startDate,
            'active' => $active,
        ]);

        return $statement->fetch();
    }

    public function findForUser(int $userId, int $habitId): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, name, habit_series_uid, frequency_days, mode, start_date, end_date,
                    active, created_at, updated_at
             FROM habits
             WHERE user_id = :user_id AND id = :id'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $habitId,
        ]);
        $habit = $statement->fetch();

        return $habit === false ? null : $habit;
    }

    public function findAllForUser(int $userId): array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, name, habit_series_uid, frequency_days, mode, start_date, end_date,
                    active, created_at, updated_at
             FROM habits
             WHERE user_id = :user_id
             ORDER BY active DESC, lower(name), start_date DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll();
    }

    public function findRulesForRange(int $userId, string $startDate, string $endDate): array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, name, habit_series_uid, frequency_days, mode, start_date, end_date,
                    active, created_at, updated_at
             FROM habits
             WHERE user_id = :user_id
                AND start_date <= :end_date
                AND (end_date IS NULL OR end_date >= :start_date)
             ORDER BY start_date, id'
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }

    public function findEntriesForRangeWithSlidingLookback(int $userId, string $startDate, string $endDate): array
    {
        $statement = $this->database->connect()->prepare(
            'WITH range_entries AS (
                SELECT id, user_id, habit_id, performed_date, status, created_at, updated_at
                FROM habit_entries
                WHERE user_id = :user_id
                   AND performed_date BETWEEN :start_date AND :end_date
             ),
             lookback_entries AS (
                SELECT DISTINCT ON (habit_entries.habit_id)
                       habit_entries.id,
                       habit_entries.user_id,
                       habit_entries.habit_id,
                       habit_entries.performed_date,
                       habit_entries.status,
                       habit_entries.created_at,
                       habit_entries.updated_at
                FROM habit_entries
                JOIN habits ON habits.id = habit_entries.habit_id
                WHERE habit_entries.user_id = :user_id
                   AND habits.mode = \'sliding\'
                   AND habit_entries.performed_date < :start_date
                ORDER BY habit_entries.habit_id, habit_entries.performed_date DESC, habit_entries.id DESC
             )
             SELECT * FROM range_entries
             UNION
             SELECT * FROM lookback_entries'
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $entries = $statement->fetchAll();

        usort($entries, static fn (array $a, array $b): int => strcmp(
            (string) $a['performed_date'] . ':' . (string) $a['id'],
            (string) $b['performed_date'] . ':' . (string) $b['id']
        ));

        return $entries;
    }

    public function updateRuleInPlace(
        int $userId,
        int $habitId,
        string $name,
        int $frequencyDays,
        string $mode,
        string $startDate
    ): ?array
    {
        $statement = $this->database->connect()->prepare(
            'UPDATE habits
             SET name = :name,
                 frequency_days = :frequency_days,
                 mode = :mode,
                 start_date = :start_date
             WHERE user_id = :user_id AND id = :id
             RETURNING id, user_id, name, habit_series_uid, frequency_days, mode, start_date, end_date,
                       active, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $habitId,
            'name' => $name,
            'frequency_days' => $frequencyDays,
            'mode' => $mode,
            'start_date' => $startDate,
        ]);
        $habit = $statement->fetch();

        return $habit === false ? null : $habit;
    }

    public function versionRule(
        int $userId,
        int $habitId,
        string $name,
        int $frequencyDays,
        string $mode,
        string $today
    ): ?array {
        $pdo = $this->database->connect();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $closeDate = (new \DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
            $statement = $pdo->prepare(
                'UPDATE habits
                 SET end_date = :end_date, active = FALSE
                 WHERE user_id = :user_id AND id = :id
                 RETURNING id, habit_series_uid'
            );
            $statement->execute([
                'user_id' => $userId,
                'id' => $habitId,
                'end_date' => $closeDate,
            ]);

            $existing = $statement->fetch();

            if ($existing === false) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }

                return null;
            }

            $habit = $this->createRule(
                $userId,
                $name,
                $frequencyDays,
                $mode,
                $today,
                true,
                (string) $existing['habit_series_uid']
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $habit;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function resumeRule(
        int $userId,
        int $habitId,
        string $name,
        int $frequencyDays,
        string $mode,
        string $startDate
    ): ?array {
        $habit = $this->findForUser($userId, $habitId);

        if ($habit === null || (bool) $habit['active']) {
            return null;
        }

        return $this->createRule(
            $userId,
            $name,
            $frequencyDays,
            $mode,
            $startDate,
            true,
            (string) $habit['habit_series_uid']
        );
    }

    public function archiveRule(int $userId, int $habitId, string $endDate): bool
    {
        $statement = $this->database->connect()->prepare(
            'UPDATE habits
             SET end_date = :end_date, active = FALSE
             WHERE user_id = :user_id AND id = :id'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $habitId,
            'end_date' => $endDate,
        ]);

        return $statement->rowCount() > 0;
    }

    public function upsertEntry(int $userId, int $habitId, string $performedDate, string $status): ?array
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO habit_entries (user_id, habit_id, performed_date, status)
             SELECT :user_id, habits.id, :performed_date, :status
             FROM habits
             WHERE habits.id = :habit_id AND habits.user_id = :user_id
             ON CONFLICT (habit_id, performed_date)
             DO UPDATE SET status = EXCLUDED.status
             RETURNING id, user_id, habit_id, performed_date, status, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'habit_id' => $habitId,
            'performed_date' => $performedDate,
            'status' => $status,
        ]);
        $entry = $statement->fetch();

        return $entry === false ? null : $entry;
    }

    public function deleteEntry(int $userId, int $habitId, string $performedDate): bool
    {
        $statement = $this->database->connect()->prepare(
            'DELETE FROM habit_entries
             WHERE user_id = :user_id
                AND habit_id = :habit_id
                AND performed_date = :performed_date'
        );
        $statement->execute([
            'user_id' => $userId,
            'habit_id' => $habitId,
            'performed_date' => $performedDate,
        ]);

        return $statement->rowCount() > 0;
    }

    private function seriesUid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
