<?php

declare(strict_types=1);

namespace Spinx\Http\Middleware;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * HttpCacheMiddleware — Provides HTTP 304 Not Modified ETag caching and Cache-Control headers
 * for high-volume GET endpoints.
 *
 * Registered globally as the 'cache.headers' middleware alias.
 *
 * Usage in module.php:
 *   $routes->get('/api/v1/chapters/{id}', [ChapterController::class, 'show'])
 *       ->middleware('cache.headers:max_age=3600,etag');
 */
final class HttpCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $options = 'max_age=3600,etag',
    ) {
    }

    public function process(\Symfony\Component\HttpFoundation\Request $request, \Closure $next): SymfonyResponse
    {
        return $this->handle($request, $next, $this->options);
    }

    /**
     * @param mixed $request
     * @param \Closure(mixed): mixed $next
     * @param string $options e.g. "max_age=3600,etag" or "max_age=86400,public"
     */
    public function handle(mixed $request, \Closure $next, string $options = 'max_age=3600,etag'): mixed
    {

        $response = $next($request);

        $method = '';
        if ($request instanceof SymfonyResponse || $request instanceof \Symfony\Component\HttpFoundation\Request) {
            $method = $request->getMethod();
        } elseif (class_exists(\Spinx\Http\Request::class) && method_exists(\Spinx\Http\Request::class, 'method')) {
            $method = \Spinx\Http\Request::method();
        }
        if ($method === '') {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        }

        if (!in_array(strtoupper((string) $method), ['GET', 'HEAD'], true)) {
            return $response;
        }

        if (!$response instanceof SymfonyResponse || $response->getStatusCode() !== 200) {
            return $response;
        }

        $parsedOptions = $this->parseOptions($options);
        $maxAge        = $parsedOptions['max_age'] ?? 3600;
        $useEtag       = $parsedOptions['etag'] ?? true;
        $isPublic      = $parsedOptions['public'] ?? true;

        // 1. Set Cache-Control header
        $cacheControl = ($isPublic ? 'public' : 'private') . ", max-age={$maxAge}";
        $response->headers->set('Cache-Control', $cacheControl);

        // 2. Compute and attach ETag
        if ($useEtag) {
            $content = (string) $response->getContent();
            $etag    = '"' . sha1($content) . '"';
            $response->headers->set('ETag', $etag);

            // 3. Evaluate conditional If-None-Match header
            $ifNoneMatch = null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $ifNoneMatch = $request->headers->get('If-None-Match');
            } elseif (class_exists(\Spinx\Http\Request::class) && method_exists(\Spinx\Http\Request::class, 'header')) {
                $ifNoneMatch = \Spinx\Http\Request::header('If-None-Match');
            }
            if ($ifNoneMatch === null) {
                $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
            }

            if ($ifNoneMatch !== null) {
                $clientEtags = array_map('trim', explode(',', (string) $ifNoneMatch));


                if (in_array($etag, $clientEtags, true) || in_array('*', $clientEtags, true)) {
                    $response->setStatusCode(304);
                    $response->setContent('');
                    $response->headers->remove('Content-Type');
                    $response->headers->remove('Content-Length');
                    return $response;
                }
            }
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOptions(string $options): array
    {
        $result = ['max_age' => 3600, 'etag' => true, 'public' => true];

        foreach (explode(',', $options) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'max_age=')) {
                $result['max_age'] = (int) substr($part, 8);
            } elseif ($part === 'etag') {
                $result['etag'] = true;
            } elseif ($part === 'no_etag') {
                $result['etag'] = false;
            } elseif ($part === 'private') {
                $result['public'] = false;
            } elseif ($part === 'public') {
                $result['public'] = true;
            }
        }

        return $result;
    }
}
