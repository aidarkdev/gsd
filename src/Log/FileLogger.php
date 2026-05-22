<?php

declare(strict_types=1);

namespace App\Log;

use Psr\Log\AbstractLogger;
use Stringable;

final class FileLogger extends AbstractLogger
{
    public function __construct(private string $path)
    {
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('c'),
            strtoupper((string) $level),
            $this->interpolate((string) $message, $context),
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES)
        );

        if (@file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }
}
