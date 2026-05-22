<?php

declare(strict_types=1);

namespace App\Auth;

use App\Middleware\SessionMiddleware;
use App\Repository\UserRepository;

final class AuthService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            SessionMiddleware::deleteCookie();
            session_destroy();
        }
    }

    public function user(): ?array
    {
        $userId = $_SESSION['user_id'] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        return $this->users->findById((int) $userId);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();

        return $user !== null && $user['role'] === $role;
    }
}
