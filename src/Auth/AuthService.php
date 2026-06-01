<?php

declare(strict_types=1);

namespace App\Auth;

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
            $this->deleteSessionCookie();
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

    private function deleteSessionCookie(): void
    {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
}
