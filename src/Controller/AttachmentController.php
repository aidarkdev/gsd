<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthService;
use App\Repository\AttachmentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AttachmentController
{
    public function __construct(
        private AttachmentRepository $attachments,
        private AuthService $auth
    ) {
    }

    public function show(
        ServerRequestInterface $_request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $user = $this->auth->user();
        $id = (string) ($args['id'] ?? '');

        if ($user === null) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }

        if (!ctype_digit($id)) {
            return $response->withStatus(404);
        }

        $attachment = $this->attachments->findForUser((int) $user['id'], (int) $id);

        if ($attachment === null) {
            return $response->withStatus(404);
        }

        $filePath = $this->resolveAttachmentPath((string) $attachment['storage_path']);

        if ($filePath === null || !is_file($filePath) || !is_readable($filePath)) {
            return $response->withStatus(404);
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            return $response->withStatus(404);
        }

        $filename = basename((string) $attachment['original_name']);
        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', (string) $attachment['mime_type'])
            ->withHeader('Content-Length', (string) strlen($content))
            ->withHeader('Content-Disposition', 'inline; filename="' . addcslashes($filename, "\\\"") . '"');
    }

    private function resolveAttachmentPath(string $storagePath): ?string
    {
        $relativePath = ltrim(str_replace('\\', '/', $storagePath), '/');

        if (!str_starts_with($relativePath, 'storage/attachments/')) {
            return null;
        }

        $basePath = realpath(BASE_PATH . '/storage/attachments');
        $filePath = realpath(BASE_PATH . '/' . $relativePath);

        if ($basePath === false || $filePath === false) {
            return null;
        }

        return str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR) ? $filePath : null;
    }
}
