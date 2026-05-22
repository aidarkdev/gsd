<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;

final class UserRepository
{
    public function __construct(private Database $database)
    {
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, email, name, password_hash, role, created_at, updated_at FROM users WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT id, email, name, password_hash, role, created_at, updated_at FROM users WHERE email = :email'
        );
        $statement->execute(['email' => strtolower($email)]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function findAll(): array
    {
        return $this->database
            ->connect()
            ->query('SELECT id, email, name, role, created_at, updated_at FROM users ORDER BY id')
            ->fetchAll();
    }

    public function create(string $email, string $name, string $password, string $role = 'user'): int
    {
        $statement = $this->database->connect()->prepare(
            'INSERT INTO users (email, name, password_hash, role)
             VALUES (:email, :name, :password_hash, :role)
             RETURNING id'
        );
        $statement->execute([
            'email' => strtolower($email),
            'name' => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return (int) $statement->fetchColumn();
    }
}
