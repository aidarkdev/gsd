<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeController
{
    public function __construct(
        private TemplateRenderer $templates,
        private AuthService $auth,
        private CsrfToken $csrf
    ) {
    }

    public function show(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        $response->getBody()->write($this->templates->render('home.php', [
            'app' => 'gsd',
            'environment' => $_ENV['APP_ENV'] ?? 'local',
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
