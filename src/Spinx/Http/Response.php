<?php

declare(strict_types=1);

namespace Spinx\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Static facade and factory for constructing HTTP responses.
 *
 * Usage:
 *   return Response::json(['status' => 'ok'], 200);
 *   return Response::redirect('/dashboard');
 *   return Response::html('<h1>Hello</h1>', 200);
 */
final class Response
{
    public static function make(string $content = '', int $status = 200, array $headers = []): SymfonyResponse
    {
        return new SymfonyResponse($content, $status, $headers);
    }

    public static function html(string $content = '', int $status = 200, array $headers = []): SymfonyResponse
    {
        $headers['Content-Type'] ??= 'text/html; charset=UTF-8';
        return new SymfonyResponse($content, $status, $headers);
    }

    public static function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    public static function noContent(int $status = 204, array $headers = []): SymfonyResponse
    {
        return new SymfonyResponse('', $status, $headers);
    }
}
