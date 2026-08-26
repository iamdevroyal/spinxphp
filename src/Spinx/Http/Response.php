<?php

declare(strict_types=1);

namespace Spinx\Http;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
// Spinx subclass imports — do NOT use Symfony JsonResponse/RedirectResponse directly;

/**
 * Spinx HTTP Response — extends Symfony's Response so it can be used
 * directly as a PHP return type in controllers.
 *
 * Factory methods make it the only import you need in any controller:
 *
 *   use Spinx\Http\Response;
 *
 *   public function index(): Response
 *   {
 *       return view('Home::index', $data);           // view() returns Response
 *       return Response::json(['status' => 'ok']);    // JsonResponse (extends Response)
 *       return Response::redirect('/dashboard');      // RedirectResponse (extends Response)
 *       return Response::html('<h1>Hello</h1>');      // HTML Response
 *       return Response::make('plain text', 200);     // Plain Response
 *       return Response::noContent();                 // 204 No Content
 *       return Response::stream(fn() => ..., 200);   // Streaming Response
 *       return Response::download($path, $name);      // File download
 *   }
 */
class Response extends SymfonyResponse
{
    // ─── Static factory methods ──────────────────────────────────────────────

    /**
     * Create a plain text or HTML response.
     */
    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    /**
     * Create an HTML response with charset header.
     */
    public static function html(string $content = '', int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] ??= 'text/html; charset=UTF-8';

        return new static($content, $status, $headers);
    }

    /**
     * Create a JSON response.
     * Returns a JsonResponse which extends Response, so it's compatible with the Response return type.
     */
    public static function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Convenience: create a standard success JSON envelope.
     */
    public static function jsonSuccess(mixed $data = null, int $status = 200): JsonResponse
    {
        return JsonResponse::success($data, $status);
    }

    /**
     * Convenience: create a standard error JSON envelope.
     *
     * @param array<string, mixed>|null $errors
     */
    public static function jsonError(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return JsonResponse::error($message, $status, $errors);
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    /**
     * Permanent redirect (301).
     */
    public static function permanentRedirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url, 301);
    }

    /**
     * Create a 204 No Content response.
     */
    public static function noContent(int $status = 204, array $headers = []): static
    {
        return new static('', $status, $headers);
    }

    /**
     * Create a plain text response.
     */
    public static function text(string $content = '', int $status = 200, array $headers = []): static
    {
        $headers['Content-Type'] ??= 'text/plain; charset=UTF-8';

        return new static($content, $status, $headers);
    }

    /**
     * Create a downloadable file response.
     */
    public static function download(string $filePath, ?string $fileName = null, array $headers = []): static
    {
        $fileName ??= basename($filePath);
        $content    = (string) file_get_contents($filePath);
        $headers    = array_merge([
            'Content-Type'              => mime_content_type($filePath) ?: 'application/octet-stream',
            'Content-Disposition'       => "attachment; filename=\"{$fileName}\"",
            'Content-Length'            => (string) strlen($content),
        ], $headers);

        return new static($content, 200, $headers);
    }

    /**
     * Create a response with specific status code shorthand.
     */
    public static function status(int $status, string $message = ''): static
    {
        return new static($message ?: SymfonyResponse::$statusTexts[$status] ?? '', $status);
    }

    // ─── Instance fluent helpers ──────────────────────────────────────────────

    /**
     * Add/override a header and return the same instance (fluent).
     */
    public function withHeader(string $name, string $value): static
    {
        $this->headers->set($name, $value);

        return $this;
    }

    /**
     * Set the status code and return the same instance (fluent).
     */
    public function withStatus(int $status): static
    {
        $this->setStatusCode($status);

        return $this;
    }
}
