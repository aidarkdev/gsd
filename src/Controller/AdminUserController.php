<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\Repository\UserRepository;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminUserController
{
    public function __construct(
        private TemplateRenderer $templates,
        private UserRepository $users,
        private AuthService $auth,
        private CsrfToken $csrf
    ) {
    }

    public function index(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->templates->render('admin/users.php', [
            'user' => $this->auth->user(),
            'users' => $this->users->findAll(),
            'csrfToken' => $this->csrf->get(),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function me(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'user' => $this->publicUser($this->auth->user()),
        ], JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiIndex(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $users = array_map(fn (array $user): array => $this->publicUser($user), $this->users->findAll());
        $response->getBody()->write(json_encode(['users' => $users], JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function publicUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'created_at' => $user['created_at'],
        ];
    }
}
