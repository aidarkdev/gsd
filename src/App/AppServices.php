<?php

declare(strict_types=1);

namespace App\App;

use App\Auth\AuthService;
use App\Auth\CsrfToken;
use App\Database;
use App\Http\ErrorHandler;
use App\I18n\Translator;
use App\Log\FileLogger;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use App\Validation\Validator;
use App\View\TemplateRenderer;

final class AppServices
{
    public function __construct(
        public readonly Database $database,
        public readonly TemplateRenderer $templates,
        public readonly FileLogger $logger,
        public readonly CsrfToken $csrf,
        public readonly UserRepository $users,
        public readonly LoginAttemptRepository $loginAttempts,
        public readonly AuthService $auth,
        public readonly Translator $translator,
        public readonly Validator $validator,
        public readonly ErrorHandler $errorHandler,
        public readonly bool $debug,
        public readonly string $sessionPath,
        public readonly bool $cookieSecure,
        public readonly int $loginMaxAttempts,
        public readonly int $loginWindowSeconds,
        public readonly int $loginLockSeconds
    ) {
    }
}
