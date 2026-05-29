<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class TagRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findOrCreate(int $userId, string $name, string $slug): int
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO tags (user_id, name, slug)
             VALUES (:user_id, :name, :slug)
             ON CONFLICT (user_id, slug) DO UPDATE SET name = EXCLUDED.name
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'name' => $name,
            'slug' => $slug,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function addToTask(int $userId, int $taskId, int $tagId): void
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO task_tags (task_id, tag_id)
             SELECT task_instances.id, tags.id
             FROM task_instances
             JOIN tags ON tags.id = :tag_id
             WHERE task_instances.id = :task_id
                AND task_instances.user_id = :user_id
                AND tags.user_id = :user_id
             ON CONFLICT DO NOTHING'
        );
        $statement->execute([
            'user_id' => $userId,
            'task_id' => $taskId,
            'tag_id' => $tagId,
        ]);
    }

    public function addToNote(int $userId, int $noteId, int $tagId): void
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO note_tags (note_id, tag_id)
             SELECT notes.id, tags.id
             FROM notes
             JOIN tags ON tags.id = :tag_id
             WHERE notes.id = :note_id
                AND notes.user_id = :user_id
                AND tags.user_id = :user_id
             ON CONFLICT DO NOTHING'
        );
        $statement->execute([
            'user_id' => $userId,
            'note_id' => $noteId,
            'tag_id' => $tagId,
        ]);
    }
}
