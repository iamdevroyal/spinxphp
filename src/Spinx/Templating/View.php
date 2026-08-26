<?php

declare(strict_types=1);

namespace Spinx\Templating;

use Spinx\Http\Response;

/**
 * Static facade for rendering Spinx templates.
 *
 * Usage:
 *   return View::render('Auth::login', ['error' => null]);
 *   $html = View::make('welcome', ['title' => 'Home']);
 */
final class View
{
    private static ?TemplateRenderer $renderer = null;

    public static function setRenderer(TemplateRenderer $renderer): void
    {
        self::$renderer = $renderer;
    }

    public static function getRenderer(): TemplateRenderer
    {
        if (self::$renderer === null) {
            throw new \RuntimeException(
                'TemplateRenderer has not been set on View facade. Kernel boots this automatically.'
            );
        }

        return self::$renderer;
    }

    public static function make(string $template, array $data = []): string
    {
        return self::getRenderer()->render($template, $data);
    }

    public static function render(string $template, array $data = [], int $status = 200, array $headers = []): Response
    {
        $html = self::make($template, $data);
        $headers['Content-Type'] ??= 'text/html; charset=UTF-8';

        return new Response($html, $status, $headers);
    }
}
