<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private string $dsn,
        private string $user,
        private string $password
    ) {
    }

    public function connect(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if ($this->dsn === '') {
            throw new \RuntimeException('DB_DSN is not configured');
        }

        $this->pdo = new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }
}
