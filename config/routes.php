<?php

declare(strict_types=1);

use App\App\AppServices;
use App\Controller\AdminUserController;
use App\Controller\AttachmentController;
use App\Controller\AuthController;
use App\Controller\CalendarController;
use App\Controller\DashboardController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app, AppServices $services): void {
    $homeController = new HomeController($services->templates, $services->auth, $services->csrf, $services->translator);
    $authController = new AuthController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->validator
    );
    $dashboardController = new DashboardController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator
    );
    $calendarController = new CalendarController(
        $services->templates,
        $services->auth,
        $services->csrf,
        $services->translator,
        $services->tasks,
        $services->notes,
        $services->attachments
    );
    $attachmentController = new AttachmentController($services->attachments, $services->auth);
    $healthController = new HealthController($services->database);
    $adminUserController = new AdminUserController(
        $services->templates,
        $services->users,
        $services->auth,
        $services->csrf,
        $services->translator
    );
    $app->get('/', [$homeController, 'show']);
    $app->get('/login', [$authController, 'loginForm']);
    $app->post('/login', [$authController, 'login']);
    $app->post('/lang/{code}', [$authController, 'language']);
    $app->post('/logout', [$authController, 'logout']);
    $app->get('/dashboard', [$dashboardController, 'show']);
    $app->get('/calendar', [$calendarController, 'show']);
    $app->get('/attachments/{id}', [$attachmentController, 'show']);
    $app->get('/admin/users', [$adminUserController, 'index']);

    $app->get('/api/health', [$healthController, 'show']);
    $app->get('/api/me', [$adminUserController, 'me']);
    $app->get('/api/admin/users', [$adminUserController, 'apiIndex']);

    $app->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/{routes:.+}', static function (
        ServerRequestInterface $request,
        ResponseInterface $response
    ) use ($services): ResponseInterface {
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, '/api/')) {
            $response->getBody()->write(json_encode([
                'error' => 'Not found',
            ], JSON_UNESCAPED_SLASHES));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }

        $lang = $services->translator->currentLanguage();
        $message = $services->translator->translate($lang, 'error.not_found');
        $response->getBody()->write(
            '<!doctype html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"><body><h1>'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</h1></body></html>'
        );

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(404);
    });
};
