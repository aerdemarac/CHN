<?php
declare(strict_types=1);

/**
 * Load environment variables from config/.env or .env into getenv()/$_ENV/$_SERVER.
 */
function loadChnEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        dirname(__DIR__) . '/config/.env',
        dirname(__DIR__) . '/.env',
    ];

    $envPath = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $envPath = $candidate;
            break;
        }
    }

    if ($envPath === null) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Append a timestamped error message to logs/error.log without exposing secrets.
 */
function chnLogError(string $message): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logDir . '/error.log', $line, FILE_APPEND | LOCK_EX);
}
