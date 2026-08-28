<?php

declare(strict_types=1);

namespace Spinx\Templating;

use Spinx\Http\Request;
use Spinx\Security\Csrf;

/**
 * The single entry point controllers use to render a view.
 * Ties together ViewFinder, TemplateCache, Vite, Stack & Slot management,
 * and View Directive helpers into one unified renderer.
 */
final class TemplateRenderer
{
    /** @var array<string, string[]> Stack name => list of pushed string chunks */
    private array $stacks = [];

    /** @var array<string, string> Slot name => slot string content */
    private array $slots = [];

    /** @var array<int, array{string, array<string, mixed>}> Stack of active layouts: [view, data] */
    private array $layoutStack = [];

    /** @var array<string, bool> Tracks rendered once identifiers */
    private array $renderedOnce = [];

    /** @var array<int, array{key: string, ttl: int}> Active fragment cache stack */
    private array $cacheStack = [];

    /** @var string Active slot/stack capture target */
    private string $activeCaptureName = '';

    public function __construct(
        private readonly ViewFinder $finder,
        private readonly TemplateCache $cache,
        private readonly Vite $vite,
    ) {
    }

    /**
     * @param array<string, mixed> $data Passed into the template's scope as variables
     */
    public function render(string $view, array $data = []): string
    {
        $sourcePath = $this->finder->resolve($view);
        $compiledPath = $this->cache->getCompiledPath($sourcePath);

        // Bound as $__spinxRenderer so compiled directives can call
        // back into render methods without polluting template variable scope.
        $__spinxRenderer = $this;

        extract($data, EXTR_SKIP);

        ob_start();
        include $compiledPath;

        return (string) ob_get_clean();
    }

    /** Include template partial if file exists */
    public function renderIf(string $view, array $data = []): string
    {
        try {
            return $this->render($view, $data);
        } catch (\Throwable) {
            return '';
        }
    }

    // ── Stacks & Pushing ───────────────────────────────────────────────────

    public function startPush(string $name): void
    {
        $this->activeCaptureName = $name;
        ob_start();
    }

    public function stopPush(): void
    {
        $content = (string) ob_get_clean();
        $this->stacks[$this->activeCaptureName][] = $content;
    }

    public function startPrepend(string $name): void
    {
        $this->activeCaptureName = $name;
        ob_start();
    }

    public function stopPrepend(): void
    {
        $content = (string) ob_get_clean();
        if (!isset($this->stacks[$this->activeCaptureName])) {
            $this->stacks[$this->activeCaptureName] = [];
        }
        array_unshift($this->stacks[$this->activeCaptureName], $content);
    }

    public function yieldStack(string $name, string $default = ''): string
    {
        if (empty($this->stacks[$name])) {
            return $default;
        }

        return implode('', $this->stacks[$name]);
    }

    // ── Layouts & Slots ───────────────────────────────────────────────────

    /** @param array<string, mixed> $data */
    public function startLayout(string $view, array $data = []): void
    {
        $this->layoutStack[] = [$view, $data];
        ob_start();
    }

    public function endLayout(): string
    {
        $content = (string) ob_get_clean();
        if (empty($this->layoutStack)) {
            return $content;
        }

        [$layoutView, $layoutData] = array_pop($this->layoutStack);
        $layoutData['slot'] = $content;
        $layoutData = array_merge($this->slots, $layoutData);

        return $this->render($layoutView, $layoutData);
    }

    public function startSlot(string $name): void
    {
        $this->activeCaptureName = $name;
        ob_start();
    }

    public function stopSlot(): void
    {
        $content = (string) ob_get_clean();
        $this->slots[$this->activeCaptureName] = $content;
    }

    public function renderSlot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    // ── Once Tracker ──────────────────────────────────────────────────────

    public function hasRenderedOnce(string $id): bool
    {
        if (isset($this->renderedOnce[$id])) {
            return true;
        }

        $this->renderedOnce[$id] = true;
        return false;
    }

    // ── Forms & Attributes ─────────────────────────────────────────────────

    public function vite(): string
    {
        return $this->vite->tags();
    }

    public function csrfField(): string
    {
        $token = Csrf::current();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public function methodField(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . htmlspecialchars(strtoupper($method), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function old(string $key, mixed $default = null): mixed
    {
        if (class_exists(Request::class) && method_exists(Request::class, 'old')) {
            return Request::old($key, $default);
        }

        return $default;
    }


    /**
     * Build class attribute from string, list, or conditional map.
     * Example: classAttr(['btn', 'btn-primary' => $isPrimary, 'opacity-50' => $disabled])
     */
    public function classAttr(mixed $classes): string
    {
        if (is_string($classes)) {
            $classes = [$classes];
        }

        if (!is_array($classes)) {
            return '';
        }

        $result = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if (!empty($value)) {
                    $result[] = trim((string) $value);
                }
            } elseif ($value) {
                $result[] = trim((string) $key);
            }
        }

        if (empty($result)) {
            return '';
        }

        return 'class="' . htmlspecialchars(implode(' ', $result), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Build style attribute from string, list, or conditional map.
     * Example: styleAttr(['color: red' => $hasError, 'background: ' . $bg])
     */
    public function styleAttr(mixed $styles): string
    {
        if (is_string($styles)) {
            $styles = [$styles];
        }

        if (!is_array($styles)) {
            return '';
        }

        $result = [];
        foreach ($styles as $key => $value) {
            if (is_int($key)) {
                if (!empty($value)) {
                    $result[] = rtrim(trim((string) $value), ';');
                }
            } elseif ($value) {
                $result[] = rtrim(trim((string) $key), ';');
            }
        }

        if (empty($result)) {
            return '';
        }

        return 'style="' . htmlspecialchars(implode('; ', $result) . ';', ENT_QUOTES, 'UTF-8') . '"';
    }

    // ── JavaScript & JSON ─────────────────────────────────────────────────

    public function js(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    // ── SEO & OpenGraph ───────────────────────────────────────────────────

    /** @param array<string, mixed> $meta */
    public function seo(array $meta): string
    {
        $tags = [];
        $title = $meta['title'] ?? null;
        $description = $meta['description'] ?? null;
        $image = $meta['image'] ?? null;
        $canonical = $meta['canonical'] ?? ($meta['url'] ?? null);
        $type = $meta['type'] ?? 'website';
        $siteName = $meta['site_name'] ?? (defined('APP_NAME') ? constant('APP_NAME') : 'Spinx Application');

        if ($title) {
            $tags[] = '<title>' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '</title>';
            $tags[] = '<meta property="og:title" content="' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '">';
        }

        if ($description) {
            $tags[] = '<meta name="description" content="' . htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta property="og:description" content="' . htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') . '">';
        }

        if ($image) {
            $tags[] = '<meta property="og:image" content="' . htmlspecialchars((string) $image, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars((string) $image, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        } else {
            $tags[] = '<meta name="twitter:card" content="summary">';
        }

        if ($canonical) {
            $tags[] = '<link rel="canonical" href="' . htmlspecialchars((string) $canonical, ENT_QUOTES, 'UTF-8') . '">';
            $tags[] = '<meta property="og:url" content="' . htmlspecialchars((string) $canonical, ENT_QUOTES, 'UTF-8') . '">';
        }

        $tags[] = '<meta property="og:type" content="' . htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars((string) $siteName, ENT_QUOTES, 'UTF-8') . '">';

        return implode("\n    ", $tags);
    }

    // ── Media & SVG Inlining ───────────────────────────────────────────────

    /** @param array<string, mixed> $attributes */
    public function svg(string $path, array $attributes = []): string
    {
        $base = defined('SPINX_BASE_PATH') ? (string) constant('SPINX_BASE_PATH') : (string) getcwd();
        $candidates = [
            $base . '/public/' . ltrim($path, '/\\'),
            $base . '/resources/' . ltrim($path, '/\\'),
            $base . '/' . ltrim($path, '/\\'),
        ];

        $filePath = null;
        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                $filePath = $cand;
                break;
            }
        }

        if ($filePath === null) {
            return '<!-- SVG not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $content = (string) file_get_contents($filePath);
        if ($attributes === []) {
            return $content;
        }

        $attrString = '';
        foreach ($attributes as $k => $v) {
            $attrString .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }

        return (string) preg_replace('/<svg\b/i', '<svg' . $attrString, $content, 1);
    }

    /** @param array<string, mixed> $attributes */
    public function image(string $path, array $attributes = []): string
    {
        $url = str_starts_with($path, 'http') ? $path : '/' . ltrim($path, '/');
        $alt = $attributes['alt'] ?? '';
        $lazy = $attributes['lazy'] ?? true;
        unset($attributes['alt'], $attributes['lazy']);

        $attrString = ' src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) $alt, ENT_QUOTES, 'UTF-8') . '"';
        if ($lazy) {
            $attrString .= ' loading="lazy"';
        }

        foreach ($attributes as $k => $v) {
            $attrString .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }

        return '<img' . $attrString . '>';
    }

    /** @param mixed $user Object or array */
    public function avatar(mixed $user, array $attributes = []): string
    {
        $name = is_array($user) ? ($user['name'] ?? 'User') : ($user->name ?? 'User');
        $avatarUrl = is_array($user) ? ($user['avatar'] ?? null) : ($user->avatar ?? null);
        $size = (int) ($attributes['size'] ?? 40);
        $class = (string) ($attributes['class'] ?? 'avatar');

        if ($avatarUrl) {
            return '<img src="' . htmlspecialchars((string) $avatarUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" width="' . $size . '" height="' . $size . '" style="border-radius:50%; object-fit:cover;">';
        }

        $initials = strtoupper(substr((string) $name, 0, 1));
        $bg = '#E11D63'; // Spinx signature pink

        return '<div class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" style="width:' . $size . 'px; height:' . $size . 'px; border-radius:50%; background:' . $bg . '; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:bold; font-size:' . round($size * 0.45) . 'px;">' . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    // ── Formatting Helpers ─────────────────────────────────────────────────

    public function fileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $val = $bytes / (1024 ** $power);

        return round($val, 2) . ' ' . $units[$power];
    }

    public function truncate(string $text, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return mb_substr($text, 0, $limit, 'UTF-8') . $end;
    }

    public function plural(int $count, string $singular, ?string $plural = null): string
    {
        $plural ??= $singular . 's';
        $word = ($count === 1) ? $singular : $plural;

        return $count . ' ' . $word;
    }

    public function formatDate(mixed $date, string $format = 'M j, Y'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }

        if (is_numeric($date)) {
            return date($format, (int) $date);
        }

        try {
            $dt = new \DateTimeImmutable((string) $date);
            return $dt->format($format);
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    public function timeAgo(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = is_numeric($date)
            ? (int) $date
            : ($date instanceof \DateTimeInterface ? $date->getTimestamp() : (new \DateTimeImmutable((string) $date))->getTimestamp());

        $diff = time() - $timestamp;

        if ($diff < 5) {
            return 'just now';
        }
        if ($diff < 60) {
            return $diff . ' seconds ago';
        }
        if ($diff < 3600) {
            $mins = round($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = round($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = round($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $timestamp);
    }

    public function formatMoney(float|int $amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'NGN' => '₦', 'JPY' => '¥'];
        $symbol = $symbols[strtoupper($currency)] ?? strtoupper($currency) . ' ';

        return $symbol . number_format((float) $amount, 2);
    }

    // ── Auth, Roles & Flash ───────────────────────────────────────────────

    public function currentUser(): mixed
    {
        if (class_exists(\Spinx\Auth\Auth::class) && method_exists(\Spinx\Auth\Auth::class, 'user')) {
            return \Spinx\Auth\Auth::user();
        }

        return null;
    }

    public function isDarkTheme(?string $theme = null): bool
    {
        if ($theme !== null && $theme !== '') {
            return strtolower($theme) === 'dark';
        }

        if (class_exists(Request::class) && method_exists(Request::class, 'cookie')) {
            return Request::cookie('theme') === 'dark';
        }

        return false;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        if (is_object($user) && method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole($role);
        }

        $userRole = is_array($user) ? ($user['role'] ?? '') : ($user->role ?? '');
        return strtolower((string) $userRole) === strtolower($role);
    }

    public function can(string $permission, mixed $arguments = null): bool
    {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        if (is_object($user) && method_exists($user, 'can')) {
            return (bool) $user->can($permission, $arguments);
        }

        return true;
    }

    public function flash(string $key): ?string
    {
        $session = class_exists(Request::class) && method_exists(Request::class, 'session')
            ? Request::session()
            : null;

        if ($session !== null && $session->has('_flash.' . $key)) {
            $msg = $session->get('_flash.' . $key);
            $session->forget('_flash.' . $key);
            return (string) $msg;
        }

        return null;
    }

    /**
     * Retrieve all active flash messages and clear them from session.
     *
     * @return array<string, string>
     */
    public function allFlashes(): array
    {
        $session = class_exists(Request::class) && method_exists(Request::class, 'session')
            ? Request::session()
            : null;

        if ($session !== null) {
            $flashes = (array) $session->get('_flash', []);
            $session->forget('_flash');
            return $flashes;
        }

        return [];
    }


    // ── Fragment Caching ──────────────────────────────────────────────────

    public function cacheStart(string $key, int $ttl = 3600): bool
    {
        if (function_exists('cache')) {
            $cached = cache($key);
            if ($cached !== null) {
                echo (string) $cached;
                return false;
            }
        }

        $this->cacheStack[] = ['key' => $key, 'ttl' => $ttl];
        ob_start();
        return true;
    }

    public function cacheEnd(): string
    {
        $content = (string) ob_get_clean();
        if (!empty($this->cacheStack)) {
            $item = array_pop($this->cacheStack);
            if (function_exists('cache')) {
                cache([$item['key'] => $content], $item['ttl']);
            }
        }

        return $content;
    }
}
