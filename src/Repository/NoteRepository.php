<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class NoteRepository
{
    public function __construct(private Database $database)
    {
    }

    public function createDayNote(int $userId, string $noteDate, string $bodyMd, ?string $title = null): int
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO notes (user_id, note_type, note_date, title, body_md)
             VALUES (:user_id, \'day\', :note_date, :title, :body_md)
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'note_date' => $noteDate,
            'title' => $title,
            'body_md' => $bodyMd,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function createRegularNote(int $userId, string $bodyMd, ?string $title = null): int
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO notes (user_id, note_type, title, body_md)
             VALUES (:user_id, \'regular\', :title, :body_md)
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'body_md' => $bodyMd,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findDayNotesForRange(int $userId, string $startDate, string $endDate): array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, note_type, note_date, title, body_md, created_at, updated_at
             FROM notes
             WHERE user_id = :user_id
                AND note_type = \'day\'
                AND note_date BETWEEN :start_date AND :end_date
             ORDER BY note_date, id'
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $statement->fetchAll();
    }

    public function upsertDayNote(int $userId, string $noteDate, string $bodyMd, ?string $title = null): array
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO notes (user_id, note_type, note_date, title, body_md)
             VALUES (:user_id, \'day\', :note_date, :title, :body_md)
             ON CONFLICT (user_id, note_date) WHERE note_type = \'day\'
             DO UPDATE SET title = EXCLUDED.title, body_md = EXCLUDED.body_md
             RETURNING id, user_id, note_type, note_date, title, body_md, created_at, updated_at'
        );
        $statement->execute([
            'user_id' => $userId,
            'note_date' => $noteDate,
            'title' => $title,
            'body_md' => $bodyMd,
        ]);

        return $statement->fetch();
    }
}
