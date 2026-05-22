<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\View\TemplateRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
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

        $response->getBody()->write($this->templates->render('dashboard.php', [
            'user' => $user,
            'csrfToken' => $this->csrf->get(),
            'partsBaked' => [
                'dashboard-summary' => [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'clicks' => 0,
                ],
            ],
            'partsMounts' => [
                'instances' => [
                    [
                        'id' => 'dashboard-summary',
                        'part' => '/parts/dashboard-summary/index.js',
                    ],
                ],
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
