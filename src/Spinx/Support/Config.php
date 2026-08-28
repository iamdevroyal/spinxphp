<?php

declare(strict_types=1);

namespace Spinx\Support;

/**
 * Loads every PHP file in config/ (except container.php, which is
 * Symfony DI wiring, not application config — see that file's own
 * docblock for why the split exists) into a single dot-notation store.
 *
 * config/database.php's contents become accessible as
 * config('database.driver'), config/services.php's as
 * config('services.paystack.secret_key'), and so on — one file per top
 * level key, matching Laravel's convention exactly since that's the
 * pattern this was explicitly modeled on.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    private static ?self $instance = null;

    public function __construct(
        private readonly string $configDir,
    ) {
        $this->loadAll();
    }

    public static function boot(string $configDir): self
    {
        return self::$instance = new self($configDir);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'Config::boot() must be called before config() is used — this happens automatically in Kernel::boot().'
            );
        }

        return self::$instance;
    }

    private function loadAll(): void
    {
        if (!is_dir($this->configDir)) {
            return;
        }

        foreach (glob($this->configDir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');

            if ($key === 'container') {
                continue; // DI wiring, not application config — see container.php's docblock.
            }

            $this->items[$key] = require $file;
        }
    }

    /** Dot notation: Config::get('services.paystack.secret_key') or $config->get(...). Returns $default if any segment is missing. */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$instance === null) {
            return $default;
        }

        return self::$instance->getItem($key, $default);
    }

    /** Set a configuration value at runtime using dot notation (e.g. Config::set('auth.api.jwt_secret', 'xyz')). */
    public static function set(string $key, mixed $value): void
    {
        if (self::$instance === null) {
            self::$instance = new self('');
        }

        self::$instance->setItem($key, $value);
    }

    /** Check if a configuration key exists using dot notation. */
    public static function has(string $key): bool
    {
        if (self::$instance === null) {
            return false;
        }

        return self::$instance->hasItem($key);
    }

    public function getItem(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function setItem(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$this->items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $target[$segment] = $value;
            } else {
                if (!isset($target[$segment]) || !is_array($target[$segment])) {
                    $target[$segment] = [];
                }
                $target = &$target[$segment];
            }
        }
    }

    public function hasItem(string $key): bool
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}

