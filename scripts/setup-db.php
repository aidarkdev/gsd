<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$envPath = $argv[1] ?? $rootPath . '/.env';
$schemaPath = $argv[2] ?? $rootPath . '/database/schema.sql';

loadEnv($envPath);

$dbDsn = env('DB_DSN', 'pgsql:dbname=gsd');
$dbName = dsnValue($dbDsn, 'dbname') ?? 'gsd';
$dbUser = env('DB_USER', 'gsd');
$dbPassword = env('DB_PASSWORD', '');
$adminDsn = env('PG_ADMIN_DSN', 'pgsql:dbname=postgres');
$adminUser = nullableEnv('PG_ADMIN_USER');
$adminPassword = nullableEnv('PG_ADMIN_PASSWORD');
$schemaDsn = env('PG_SCHEMA_DSN', 'pgsql:dbname=' . $dbName);
$adminEmail = strtolower(env('ADMIN_EMAIL', 'admin@example.com'));
$adminName = env('ADMIN_NAME', 'Admin');
$adminPasswordPlain = env('ADMIN_PASSWORD', '');

validateIdentifier($dbName, 'database name');
validateIdentifier($dbUser, 'database user');

if ($dbPassword === '') {
    fail('DB_PASSWORD is required');
}

if ($adminPasswordPlain === '' || $adminPasswordPlain === 'change-me-admin') {
    fail('ADMIN_PASSWORD must be set and must not use the default example value');
}

$admin = new PDO($adminDsn, $adminUser, $adminPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if (!$admin->query('SELECT 1 FROM pg_roles WHERE rolname = ' . $admin->quote($dbUser))->fetchColumn()) {
    $admin->exec('CREATE ROLE ' . quoteIdent($dbUser) . ' LOGIN PASSWORD ' . $admin->quote($dbPassword));
    echo "Created PostgreSQL role $dbUser\n";
} else {
    $admin->exec('ALTER ROLE ' . quoteIdent($dbUser) . ' WITH LOGIN PASSWORD ' . $admin->quote($dbPassword));
    echo "Updated PostgreSQL role $dbUser\n";
}

if (!$admin->query('SELECT 1 FROM pg_database WHERE datname = ' . $admin->quote($dbName))->fetchColumn()) {
    $admin->exec('CREATE DATABASE ' . quoteIdent($dbName) . ' OWNER ' . quoteIdent($dbUser));
    echo "Created PostgreSQL database $dbName\n";
} else {
    $admin->exec('ALTER DATABASE ' . quoteIdent($dbName) . ' OWNER TO ' . quoteIdent($dbUser));
    echo "PostgreSQL database $dbName already exists\n";
}

$target = new PDO($schemaDsn, $adminUser, $adminPassword, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$target->exec('SET ROLE ' . quoteIdent($dbUser));
$target->exec((string) file_get_contents($schemaPath));

$statement = $target->prepare(
    'INSERT INTO users (email, name, password_hash, role)
     VALUES (:email, :name, :password_hash, :role)
     ON CONFLICT (email) DO UPDATE SET
        name = EXCLUDED.name,
        password_hash = EXCLUDED.password_hash,
        role = EXCLUDED.role,
        updated_at = NOW()'
);
$statement->execute([
    'email' => $adminEmail,
    'name' => $adminName,
    'password_hash' => password_hash($adminPasswordPlain, PASSWORD_DEFAULT),
    'role' => 'admin',
]);

echo "Applied schema from $schemaPath\n";
echo "Ensured admin user $adminEmail\n";

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

function nullableEnv(string $key): ?string
{
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? (string) $_ENV[$key] : null;
}

function dsnValue(string $dsn, string $key): ?string
{
    if (!str_starts_with($dsn, 'pgsql:')) {
        return null;
    }

    foreach (explode(';', substr($dsn, 6)) as $part) {
        [$partKey, $value] = array_pad(explode('=', $part, 2), 2, null);

        if ($partKey === $key) {
            return $value;
        }
    }

    return null;
}

function validateIdentifier(string $identifier, string $label): void
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
        fail("Invalid $label: $identifier");
    }
}

function quoteIdent(string $identifier): string
{
    return '"' . str_replace('"', '""', $identifier) . '"';
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
