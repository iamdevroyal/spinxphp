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

if (!function_exists('logger')) {
    /**
     * Log a debug/info message or get the LogManager instance.
     *
     * Usage:
     *   logger('User created', ['id' => 1]);
     *   logger()->error('Something failed', ['exception' => $e]);
     *
     * @param string|\Stringable|null $message
     * @param array<string, mixed> $context
     * @return \Spinx\Log\LogManager|\Psr\Log\LoggerInterface|void
     */
    function logger(string|\Stringable|null $message = null, array $context = [])
    {
        if ($message === null) {
            return \Spinx\Log\Log::getManager();
        }

        \Spinx\Log\Log::info($message, $context);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect HTTP response.
     */
    function redirect(string $to, int $status = 302): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return \Spinx\Http\Response::redirect($to, $status);
    }
}

if (!function_exists('request')) {
    /**
     * Get the active Request instance or an input value.
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return \Spinx\Http\Request::instance();
        }

        return \Spinx\Http\Request::input($key, $default);
    }
}

if (!function_exists('response')) {
    /**
     * Create a response or get the Response factory.
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): \Symfony\Component\HttpFoundation\Response|\Spinx\Http\Response
    {
        if (func_num_args() === 0) {
            return new \Spinx\Http\Response();
        }

        if (is_array($content)) {
            return \Spinx\Http\Response::json($content, $status, $headers);
        }

        return \Spinx\Http\Response::make((string) $content, $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Render a template and return a Symfony Response.
     */
    function view(string $template, array $data = [], int $status = 200, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        return \Spinx\Templating\View::render($template, $data, $status, $headers);
    }
}
