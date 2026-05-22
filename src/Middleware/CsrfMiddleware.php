<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\CsrfToken;
use App\Log\FileLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CsrfMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private CsrfToken $csrf,
        private FileLogger $logger
    ) {
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();

        $body = $request->getParsedBody();
        $bodyToken = is_array($body) ? ($body['_csrf'] ?? null) : null;
        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        $token = is_string($bodyToken) && $bodyToken !== '' ? $bodyToken : $headerToken;

        if ($this->csrf->validate(is_string($token) ? $token : null)) {
            return $handler->handle($request);
        }

        $this->logger->warning('Invalid CSRF token', ['path' => $path]);

        $response = new Response(419);

        if (str_starts_with($path, '/api/')) {
            $response->getBody()->write(json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write('<!doctype html><html lang="en"><body><h1>Invalid form token</h1></body></html>');

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
