<?php

declare(strict_types=1);

use Spinx\Support\Config;

if (!function_exists('env')) {
    /**
     * Reads a value from the environment (.env file, loaded once at boot
     * by vlucas/phpdotenv — see Kernel::boot() — or a real environment
     * variable set by the OS/container orchestrator, which always takes
     * precedence since Dotenv never overwrites an already-set variable).
     *
     * String "true"/"false"/"null"/"empty" are cast to their PHP
     * equivalents, matching Laravel's env() helper exactly — .env files
     * are text, so this is the difference between MAIL_DEBUG=false
     * meaning boolean false versus the non-empty, therefore truthy,
     * string "false".
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Dot-notation config access: config('services.paystack.secret_key').
     * Backed by every file in config/ except container.php — see
     * Spinx\Support\Config's own docblock.
     */
    function config(string $key, mixed $default = null): mixed
    {
        return Config::instance()->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     */
    function base_path(string $path = ''): string
    {
        $root = defined('SPINX_BASE_PATH')
            ? (string) constant('SPINX_BASE_PATH')
            : (defined('SPINX_PROJECT_ROOT') ? (string) constant('SPINX_PROJECT_ROOT') : dirname(__DIR__, 3));

        return $path === '' ? $root : $root . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}

if (!function_exists('app_path')) {
    /**
     * Get the path to the application folder.
     */
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}

if (!function_exists('config_path')) {
    /**
     * Get the path to the config folder.
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     */
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the path to the resources folder.
     */
    function resource_path(string $path = ''): string
    {
        return base_path('resources' . ($path === '' ? '' : '/' . ltrim($path, '/\\')));
    }
}
