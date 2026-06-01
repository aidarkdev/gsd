<?php

declare(strict_types=1);

namespace App\App;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\Database;
use App\Http\ErrorHandler;
use App\I18n\Translator;
use App\Log\FileLogger;
use App\Repository\AttachmentRepository;
use App\Repository\HabitRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Validation\Validator;
use App\View\TemplateRenderer;

final class Bootstrap
{
    public static function loadEnv(string $path): void
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

            $existingValue = getenv($key);

            if ((isset($_ENV[$key]) && $_ENV[$key] !== '') || ($existingValue !== false && $existingValue !== '')) {
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
            putenv($key . '=' . $value);
        }
    }

    public static function services(string $basePath): AppServices
    {
        $database = new Database(
            $_ENV['DB_DSN'] ?? '',
            $_ENV['DB_USER'] ?? '',
            $_ENV['DB_PASSWORD'] ?? ''
        );

        $logger = new FileLogger($_ENV['APP_LOG'] ?? $basePath . '/storage/logs/app.log');
        $users = new UserRepository($database);
        $loginAttempts = new LoginAttemptRepository($database);
        $tasks = new TaskRepository($database);
        $notes = new NoteRepository($database);
        $habits = new HabitRepository($database);
        $attachments = new AttachmentRepository($database);
        $auth = new AuthService($users);
        $translator = new Translator($_ENV['APP_DEFAULT_LANG'] ?? 'en');
        $debug = self::boolEnv('APP_DEBUG', false);

        return new AppServices(
            database: $database,
            templates: new TemplateRenderer($basePath . '/templates'),
            logger: $logger,
            csrf: new CsrfToken(),
            users: $users,
            loginAttempts: $loginAttempts,
            tasks: $tasks,
            notes: $notes,
            habits: $habits,
            attachments: $attachments,
            auth: $auth,
            translator: $translator,
            validator: new Validator(),
            errorHandler: new ErrorHandler($logger, $debug, $translator),
            debug: $debug,
            sessionPath: $_ENV['APP_SESSION_PATH'] ?? $basePath . '/storage/sessions',
            cookieSecure: self::boolEnv('APP_COOKIE_SECURE', false),
            loginMaxAttempts: self::intEnv('LOGIN_MAX_ATTEMPTS', 5),
            loginWindowSeconds: self::intEnv('LOGIN_WINDOW_SECONDS', 900),
            loginLockSeconds: self::intEnv('LOGIN_LOCK_SECONDS', 900)
        );
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function intEnv(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? null;

        if ($value === null || $value === '' || !ctype_digit((string) $value)) {
            return $default;
        }

        return (int) $value;
    }
}
