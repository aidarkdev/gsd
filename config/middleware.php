<?php

declare(strict_types=1);

use App\App\AppServices;
use App\Middleware\AccessPolicyMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\LoginRateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use Slim\App;

return static function (App $app, AppServices $services): void {
    $app->addRoutingMiddleware();
    $app->add(new AccessPolicyMiddleware($services->auth));
    $app->add(new LoginRateLimitMiddleware(
        $services->loginAttempts,
        $services->loginMaxAttempts,
        $services->loginWindowSeconds,
        $services->loginLockSeconds
    ));
    $app->add(new CsrfMiddleware($services->csrf, $services->logger));
    $app->addBodyParsingMiddleware();
    $app->add(new SessionMiddleware($services->sessionPath, $services->cookieSecure));

    $errorMiddleware = $app->addErrorMiddleware($services->debug, true, true);
    $errorMiddleware->setDefaultErrorHandler($services->errorHandler);
    $app->add(new SecurityHeadersMiddleware());
};
