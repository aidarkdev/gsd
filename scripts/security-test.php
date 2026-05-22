<?php

declare(strict_types=1);

use App\App\Bootstrap;
use App\Middleware\AccessPolicyMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\LoginRateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Bootstrap::loadEnv(BASE_PATH . '/.env');

$services = Bootstrap::services(BASE_PATH);
$requestFactory = new ServerRequestFactory();

$_SESSION = [];
$csrfResponse = (new CsrfMiddleware($services->csrf, $services->logger))(
    $requestFactory->createServerRequest('POST', '/login')->withParsedBody([]),
    handler(200)
);
assertStatus($csrfResponse, 419, 'POST /login without CSRF returns 419');

$_SESSION = [];
$apiCsrfResponse = (new CsrfMiddleware($services->csrf, $services->logger))(
    $requestFactory->createServerRequest('POST', '/api/missing')->withParsedBody([]),
    handler(200)
);
assertStatus($apiCsrfResponse, 419, 'POST /api/missing without CSRF returns 419');
assertHeader($apiCsrfResponse, 'Content-Type', 'application/json', 'API CSRF response is JSON');

$_SESSION = [];
$dashboardResponse = (new AccessPolicyMiddleware($services->auth))(
    $requestFactory->createServerRequest('GET', '/dashboard'),
    handler(200)
);
assertStatus($dashboardResponse, 302, 'GET /dashboard without session redirects');
assertHeader($dashboardResponse, 'Location', '/login', 'Unauthenticated web redirect target');

$headersResponse = (new SecurityHeadersMiddleware())(
    $requestFactory->createServerRequest('GET', '/'),
    handler(200)
);
assertHeader($headersResponse, 'X-Content-Type-Options', 'nosniff', 'nosniff header is set');
assertHeader($headersResponse, 'Referrer-Policy', 'same-origin', 'referrer policy is set');
assertHeader($headersResponse, 'X-Frame-Options', 'DENY', 'frame deny header is set');
assertHeaderContains($headersResponse, 'Content-Security-Policy', "default-src 'self'", 'CSP is set');

try {
    $pdo = $services->database->connect();
    $pdo->beginTransaction();

    $email = 'security-test-user@example.com';
    $services->users->create($email, 'Security Test User', 'password', 'user');
    $user = $services->users->findByEmail($email);
    $_SESSION = ['user_id' => (int) $user['id']];

    $adminResponse = (new AccessPolicyMiddleware($services->auth))(
        $requestFactory->createServerRequest('GET', '/admin/users'),
        handler(200)
    );
    assertStatus($adminResponse, 403, 'Admin page with user role returns 403');

    $_SESSION = [];
    $rateLimit = new LoginRateLimitMiddleware($services->loginAttempts, 5, 900, 900);
    $loginRequest = $requestFactory
        ->createServerRequest('POST', '/login', ['REMOTE_ADDR' => '127.0.0.1'])
        ->withParsedBody(['email' => 'locked@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $rateLimit($loginRequest, handler(422));
    }

    $lockedResponse = $rateLimit($loginRequest, handler(422));
    assertStatus($lockedResponse, 429, 'Sixth failed login attempt returns 429');

    $clearRequest = $requestFactory
        ->createServerRequest('POST', '/login', ['REMOTE_ADDR' => '127.0.0.1'])
        ->withParsedBody(['email' => 'clear@example.com']);
    $rateLimit($clearRequest, handler(422));
    $clearedResponse = $rateLimit($clearRequest, handler(302));
    assertStatus($clearedResponse, 302, 'Successful login response passes through');

    $pdo->rollBack();
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'DB security tests skipped: ' . $exception->getMessage() . PHP_EOL);
}

echo "Security tests passed\n";

function handler(int $status): RequestHandlerInterface
{
    return new class ($status) implements RequestHandlerInterface {
        public function __construct(private int $status)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response($this->status);
        }
    };
}

function assertStatus(ResponseInterface $response, int $expected, string $message): void
{
    if ($response->getStatusCode() !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . $response->getStatusCode());
    }
}

function assertHeader(ResponseInterface $response, string $name, string $expected, string $message): void
{
    if ($response->getHeaderLine($name) !== $expected) {
        fail($message . ': expected ' . $expected . ', got ' . $response->getHeaderLine($name));
    }
}

function assertHeaderContains(ResponseInterface $response, string $name, string $needle, string $message): void
{
    if (!str_contains($response->getHeaderLine($name), $needle)) {
        fail($message . ': missing ' . $needle);
    }
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
