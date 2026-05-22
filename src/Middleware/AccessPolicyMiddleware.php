<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AccessPolicyMiddleware
{
    private const PUBLIC_ROUTES = [
        'GET /' => true,
        'GET /login' => true,
        'POST /login' => true,
        'GET /api/health' => true,
    ];

    public function __construct(private AuthService $auth)
    {
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        if (isset(self::PUBLIC_ROUTES[$method . ' ' . $path])) {
            return $handler->handle($request);
        }

        if (!$this->auth->check()) {
            return $this->unauthorized($path);
        }

        if ($this->requiresAdmin($path) && !$this->auth->hasRole('admin')) {
            return $this->forbidden($path);
        }

        return $handler->handle($request);
    }

    private function requiresAdmin(string $path): bool
    {
        return $path === '/admin'
            || str_starts_with($path, '/admin/')
            || $path === '/api/admin'
            || str_starts_with($path, '/api/admin/');
    }

    private function unauthorized(string $path): ResponseInterface
    {
        $response = new Response(str_starts_with($path, '/api/') ? 401 : 302);

        if (str_starts_with($path, '/api/')) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Location', '/login');
    }

    private function forbidden(string $path): ResponseInterface
    {
        $response = new Response(403);

        if (str_starts_with($path, '/api/')) {
            $response->getBody()->write(json_encode(['error' => 'Forbidden'], JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write('<!doctype html><html lang="en"><body><h1>403</h1></body></html>');

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
