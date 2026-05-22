<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Repository\LoginAttemptRepository;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class LoginRateLimitMiddleware
{
    public function __construct(
        private LoginAttemptRepository $attempts,
        private int $maxAttempts,
        private int $windowSeconds,
        private int $lockSeconds
    ) {
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'POST' || $request->getUri()->getPath() !== '/login') {
            return $handler->handle($request);
        }

        [$key, $email, $ipAddress] = $this->requestKey($request);
        $attempt = $this->attempts->find($key);

        if ($attempt !== null && $attempt['locked_until'] !== null) {
            $lockedUntil = new DateTimeImmutable((string) $attempt['locked_until']);

            if ($lockedUntil->getTimestamp() > time()) {
                return $this->lockedResponse();
            }
        }

        $response = $handler->handle($request);

        if ($response->getStatusCode() === 302) {
            $this->attempts->clear($key);
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            $this->attempts->recordFailure(
                $key,
                $email,
                $ipAddress,
                $this->maxAttempts,
                $this->windowSeconds,
                $this->lockSeconds
            );
        }

        return $response;
    }

    private function requestKey(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        $email = is_array($body) ? strtolower(trim((string) ($body['email'] ?? ''))) : '';
        $ipAddress = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        return [
            hash('sha256', $email . '|' . $ipAddress),
            $email,
            $ipAddress,
        ];
    }

    private function lockedResponse(): ResponseInterface
    {
        $response = new Response(429);
        $response->getBody()->write('<!doctype html><html lang="en"><body><h1>Too many login attempts</h1></body></html>');

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Retry-After', (string) $this->lockSeconds);
    }
}
