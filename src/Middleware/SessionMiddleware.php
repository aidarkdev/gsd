<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware
{
    public function __construct(
        private string $savePath,
        private bool $cookieSecure
    ) {
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!is_dir($this->savePath)) {
                @mkdir($this->savePath, 0775, true);
            }

            session_save_path($this->savePath);
            ini_set('session.use_strict_mode', '1');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $this->cookieSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        return $handler->handle($request);
    }
}
