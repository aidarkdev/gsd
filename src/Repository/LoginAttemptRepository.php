<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database;
use DateTimeImmutable;
use DateTimeInterface;

final class LoginAttemptRepository
{
    public function __construct(private Database $database)
    {
    }

    public function find(string $key): ?array
    {
        $statement = $this->database->connect()->prepare(
            'SELECT attempt_key, email, ip_address, attempts, first_attempt_at, locked_until
             FROM login_attempts
             WHERE attempt_key = :attempt_key'
        );
        $statement->execute(['attempt_key' => $key]);
        $attempt = $statement->fetch();

        return $attempt === false ? null : $attempt;
    }

    public function recordFailure(
        string $key,
        string $email,
        string $ipAddress,
        int $maxAttempts,
        int $windowSeconds,
        int $lockSeconds
    ): void {
        $current = $this->find($key);
        $now = new DateTimeImmutable();
        $attempts = 1;

        if ($current !== null) {
            $firstAttemptAt = new DateTimeImmutable((string) $current['first_attempt_at']);

            if ($firstAttemptAt->getTimestamp() >= $now->getTimestamp() - $windowSeconds) {
                $attempts = (int) $current['attempts'] + 1;
            }
        }

        $lockedUntil = $attempts >= $maxAttempts
            ? $now->modify('+' . $lockSeconds . ' seconds')->format(DateTimeInterface::ATOM)
            : null;

        $statement = $this->database->connect()->prepare(
            'INSERT INTO login_attempts (attempt_key, email, ip_address, attempts, first_attempt_at, locked_until)
             VALUES (:attempt_key, :email, :ip_address, :attempts, NOW(), :locked_until)
             ON CONFLICT (attempt_key) DO UPDATE SET
                email = EXCLUDED.email,
                ip_address = EXCLUDED.ip_address,
                attempts = EXCLUDED.attempts,
                first_attempt_at = CASE
                    WHEN login_attempts.first_attempt_at < NOW() - (:window_seconds * INTERVAL \'1 second\')
                    THEN NOW()
                    ELSE login_attempts.first_attempt_at
                END,
                locked_until = EXCLUDED.locked_until'
        );
        $statement->execute([
            'attempt_key' => $key,
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
            'window_seconds' => $windowSeconds,
        ]);
    }

    public function clear(string $key): void
    {
        $statement = $this->database->connect()->prepare(
            'DELETE FROM login_attempts WHERE attempt_key = :attempt_key'
        );
        $statement->execute(['attempt_key' => $key]);
    }
}
