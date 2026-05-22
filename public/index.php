<?php

declare(strict_types=1);

use App\App\Bootstrap;
use Slim\Factory\AppFactory;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Bootstrap::loadEnv(BASE_PATH . '/.env');

$app = AppFactory::create();
$services = Bootstrap::services(BASE_PATH);

(require BASE_PATH . '/config/middleware.php')($app, $services);
(require BASE_PATH . '/config/routes.php')($app, $services);

$app->run();
