<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class AttachmentRepository
{
    public function __construct(private Database $database)
    {
    }

    public function createForTask(
        int $userId,
        int $taskId,
        string $originalName,
        string $storagePath,
        string $mimeType,
        string $mediaType,
        int $sizeBytes
    ): int {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO attachments (
                user_id, task_id, original_name, storage_path, mime_type, media_type, size_bytes
             )
             SELECT :user_id, task_instances.id, :original_name, :storage_path, :mime_type, :media_type, :size_bytes
             FROM task_instances
             WHERE task_instances.id = :task_id AND task_instances.user_id = :user_id
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'task_id' => $taskId,
            'original_name' => $originalName,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'media_type' => $mediaType,
            'size_bytes' => $sizeBytes,
        ]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException('Task not found for attachment');
        }

        return (int) $id;
    }

    public function createForNote(
        int $userId,
        int $noteId,
        string $originalName,
        string $storagePath,
        string $mimeType,
        string $mediaType,
        int $sizeBytes
    ): int {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO attachments (
                user_id, note_id, original_name, storage_path, mime_type, media_type, size_bytes
             )
             SELECT :user_id, notes.id, :original_name, :storage_path, :mime_type, :media_type, :size_bytes
             FROM notes
             WHERE notes.id = :note_id AND notes.user_id = :user_id
             RETURNING id'
        );
        $statement->execute([
            'user_id' => $userId,
            'note_id' => $noteId,
            'original_name' => $originalName,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'media_type' => $mediaType,
            'size_bytes' => $sizeBytes,
        ]);
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new \RuntimeException('Note not found for attachment');
        }

        return (int) $id;
    }

    public function findForTasks(int $userId, array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $placeholders = $this->placeholders('task_id', $taskIds);
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, task_id, note_id, original_name, storage_path, mime_type,
                    media_type, size_bytes, created_at
             FROM attachments
             WHERE user_id = :user_id AND task_id IN (' . implode(', ', $placeholders['names']) . ')
             ORDER BY created_at, id'
        );
        $statement->execute(['user_id' => $userId] + $placeholders['params']);

        return $statement->fetchAll();
    }

    public function findForNotes(int $userId, array $noteIds): array
    {
        if ($noteIds === []) {
            return [];
        }

        $placeholders = $this->placeholders('note_id', $noteIds);
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, task_id, note_id, original_name, storage_path, mime_type,
                    media_type, size_bytes, created_at
             FROM attachments
             WHERE user_id = :user_id AND note_id IN (' . implode(', ', $placeholders['names']) . ')
             ORDER BY created_at, id'
        );
        $statement->execute(['user_id' => $userId] + $placeholders['params']);

        return $statement->fetchAll();
    }

    public function findForUser(int $userId, int $attachmentId): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, user_id, task_id, note_id, original_name, storage_path, mime_type,
                    media_type, size_bytes, created_at
             FROM attachments
             WHERE user_id = :user_id AND id = :id'
        );
        $statement->execute([
            'user_id' => $userId,
            'id' => $attachmentId,
        ]);
        $attachment = $statement->fetch();

        return $attachment === false ? null : $attachment;
    }

    private function placeholders(string $prefix, array $ids): array
    {
        $names = [];
        $params = [];

        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . '_' . $index;
            $names[] = ':' . $name;
            $params[$name] = (int) $id;
        }

        return [
            'names' => $names,
            'params' => $params,
        ];
    }
}
