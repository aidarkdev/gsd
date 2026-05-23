<?php

declare(strict_types=1);

namespace App\Http;

use App\Log\FileLogger;
use App\I18n\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Psr7\Response;
use Throwable;

final class ErrorHandler
{
    public function __construct(
        private FileLogger $logger,
        private bool $debug,
        private Translator $translator
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $_displayErrorDetails,
        bool $_logErrors,
        bool $_logErrorDetails
    ): ResponseInterface {
        $status = $exception instanceof HttpException ? $exception->getCode() : 500;
        $status = $status >= 400 && $status < 600 ? $status : 500;

        $this->logger->error($exception->getMessage(), [
            'status' => $status,
            'path' => $request->getUri()->getPath(),
            'exception' => $exception::class,
        ]);

        $response = new Response($status);
        $isApi = str_starts_with($request->getUri()->getPath(), '/api/');

        if ($isApi) {
            $payload = ['error' => $status === 404 ? 'Not found' : 'Server error'];

            if ($this->debug) {
                $payload['message'] = $exception->getMessage();
            }

            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $lang = $this->translator->currentLanguage();
        $key = $status === 404 ? 'error.not_found' : 'error.server';
        $message = $this->translator->translate($lang, $key);
        $body = '<!doctype html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><body><h1>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</h1></body></html>';
        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
