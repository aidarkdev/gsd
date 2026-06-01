<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class HealthController
{
    public function __construct(private Database $database)
    {
    }

    public function show(ServerRequestInterface $_request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->database->ping();

            return $this->json($response, [
                'status' => 'ok',
                'database' => 'ok',
            ]);
        } catch (Throwable $exception) {
            return $this->json($response, [
                'status' => 'ok',
                'database' => 'error',
                'error' => $exception->getMessage(),
            ], 200);
        }
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
