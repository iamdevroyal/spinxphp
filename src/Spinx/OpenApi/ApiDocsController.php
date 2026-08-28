<?php

declare(strict_types=1);

namespace Spinx\OpenApi;

use Spinx\Http\Response;
use Spinx\Support\Config;

/**
 * ApiDocsController — Serves the interactive Scalar API documentation UI and raw openapi.json.
 */
final class ApiDocsController
{
    /**
     * Render the interactive Scalar API Reference documentation sandbox.
     */
    public function docs(): Response
    {
        $title   = Config::get('app.name', 'Spinx API') . ' Reference';
        $specUrl = '/openapi.json';

        $html = <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{$title}</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">
    <style>
      body { margin: 0; padding: 0; background: #0b0f19; font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
  </head>
  <body>
    <script
      id="api-reference"
      data-url="{$specUrl}"
      data-configuration='{
        "theme": "purple",
        "darkMode": true,
        "showSidebar": true,
        "searchHotKey": "k",
        "layout": "modern"
      }'>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </body>
</html>
HTML;

        return Response::html($html);
    }
}
