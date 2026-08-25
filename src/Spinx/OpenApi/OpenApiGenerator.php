<?php

declare(strict_types=1);

namespace Spinx\OpenApi;

use Spinx\OpenApi\Attributes\ApiParam;
use Spinx\OpenApi\Attributes\ApiResponse;
use Spinx\OpenApi\Attributes\ApiSummary;
use Spinx\OpenApi\Attributes\ApiTag;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates an OpenAPI 3.1 document by reflecting on registered routes and controller attributes.
 * Run via `spinx openapi:generate`.
 */
final class OpenApiGenerator
{
    public function __construct(
        private readonly RouteCollection $routes,
        private readonly string $title = 'Spinx Application API',
        private readonly string $version = '1.0.0',
    ) {
    }

    /**
     * @return array<string, mixed> OpenAPI 3.1 specification tree
     */
    public function generate(): array
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => 'API documentation generated automatically by Spinx Framework.',
            ],
            'paths' => [],
        ];

        foreach ($this->routes->all() as $name => $route) {
            $path = $route->getPath();
            $methods = $route->getMethods() ?: ['GET'];
            $controller = $route->getDefault('_controller');

            if (!$controller || !is_string($controller) || !class_exists($controller)) {
                continue;
            }

            $refClass = new \ReflectionClass($controller);
            $refMethod = $refClass->hasMethod('__invoke') ? $refClass->getMethod('__invoke') : null;

            // Extract tags
            $tags = [];
            foreach ($refClass->getAttributes(ApiTag::class) as $attr) {
                $tags[] = $attr->newInstance()->tag;
            }
            if ($refMethod) {
                foreach ($refMethod->getAttributes(ApiTag::class) as $attr) {
                    $tags[] = $attr->newInstance()->tag;
                }
            }
            if ($tags === []) {
                $tags[] = (new \ReflectionClass($controller))->getShortName();
            }

            // Extract summary and description
            $summary = "Route: {$name}";
            $description = '';
            $summaryAttrs = $refMethod ? $refMethod->getAttributes(ApiSummary::class) : [];
            if ($summaryAttrs === []) {
                $summaryAttrs = $refClass->getAttributes(ApiSummary::class);
            }
            if ($summaryAttrs !== []) {
                /** @var ApiSummary $inst */
                $inst = $summaryAttrs[0]->newInstance();
                $summary = $inst->summary;
                $description = $inst->description;
            }

            // Extract parameters
            $parameters = [];
            // 1. Path parameters from URL pattern {param}
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
            $pathParams = $matches[1] ?? [];

            foreach ($pathParams as $paramName) {
                $parameters[$paramName] = [
                    'name' => $paramName,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
            }

            // 2. Explicit #[ApiParam] attributes
            $paramAttrs = $refMethod ? $refMethod->getAttributes(ApiParam::class) : [];
            foreach (array_merge($refClass->getAttributes(ApiParam::class), $paramAttrs) as $attr) {
                /** @var ApiParam $p */
                $p = $attr->newInstance();
                $parameters[$p->name] = [
                    'name' => $p->name,
                    'in' => $p->in,
                    'required' => $p->required,
                    'description' => $p->description,
                    'schema' => ['type' => $p->type],
                ];
            }

            // Extract responses
            $responses = [];
            $responseAttrs = $refMethod ? $refMethod->getAttributes(ApiResponse::class) : [];
            foreach (array_merge($refClass->getAttributes(ApiResponse::class), $responseAttrs) as $attr) {
                /** @var ApiResponse $r */
                $r = $attr->newInstance();
                $responses[(string) $r->status] = [
                    'description' => $r->description,
                ];
            }

            if ($responses === []) {
                $responses['200'] = [
                    'description' => 'Successful operation',
                ];
            }

            foreach ($methods as $method) {
                $methodLower = strtolower($method);
                $spec['paths'][$path][$methodLower] = [
                    'operationId' => "{$methodLower}_{$name}",
                    'tags' => array_values(array_unique($tags)),
                    'summary' => $summary,
                    'description' => $description,
                    'parameters' => array_values($parameters),
                    'responses' => $responses,
                ];
            }
        }

        return $spec;
    }

    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
