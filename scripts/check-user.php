<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$email = strtolower(trim((string) ($argv[1] ?? '')));
$envPath = $argv[2] ?? $rootPath . '/.env';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/check-user.php user@example.com [env-file]\n");
    exit(2);
}

loadEnv($envPath);

$dsn = env('DB_DSN', 'pgsql:dbname=gsd');
$user = env('DB_USER', 'gsd');
$password = env('DB_PASSWORD', '');

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $statement = $pdo->prepare(
        'SELECT id, email, name, role, created_at, updated_at
         FROM users
         WHERE email = :email'
    );
    $statement->execute(['email' => $email]);
    $row = $statement->fetch();
} catch (Throwable $exception) {
    fwrite(STDERR, 'Database error: ' . $exception->getMessage() . PHP_EOL);
    exit(3);
}

if ($row === false) {
    echo "User not found: $email\n";
    exit(1);
}

echo "User found\n";
echo 'id: ' . (int) $row['id'] . PHP_EOL;
echo 'email: ' . $row['email'] . PHP_EOL;
echo 'name: ' . $row['name'] . PHP_EOL;
echo 'role: ' . $row['role'] . PHP_EOL;
echo 'created_at: ' . $row['created_at'] . PHP_EOL;
echo 'updated_at: ' . $row['updated_at'] . PHP_EOL;

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
    }
}

function env(string $key, string $default): string
{
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? (string) $_ENV[$key] : $default;
}
