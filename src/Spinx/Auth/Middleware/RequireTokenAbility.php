<?php

declare(strict_types=1);

namespace Spinx\Auth\Middleware;

use Spinx\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RequireTokenAbility — Enforces that the currently authenticated token
 * holds a required ability/scope.
 *
 * Usage in route declarations (module.php):
 *
 *   // Single ability:
 *   $routes->post('/api/v1/projects', [ApiProjectController::class, 'create'])
 *       ->middleware('ability:projects:create');
 *
 *   // Multiple abilities (all required - AND logic):
 *   $routes->put('/api/v1/chapters/{id}', [ApiChapterController::class, 'update'])
 *       ->middleware('ability:chapters:write,projects:read');
 *
 * NOTE: This middleware MUST be used after 'auth:api' in the middleware stack.
 *
 * Returns JSON 403 Forbidden if the token lacks the required ability.
 */
final class RequireTokenAbility implements MiddlewareInterface
{
    public function __construct(
        private readonly string $ability = '*',
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        return $this->handle($request, $next, $this->ability);
    }

    /**
     * @param  string  $ability   Comma-separated abilities from middleware alias, e.g. "projects:create"
     * @param  \Closure(mixed): mixed  $next
     */
    public function handle(mixed $request, \Closure $next, string $ability = '*'): mixed
    {
        $user = Auth::user();

        if ($user === null) {
            return $this->forbidden('Authentication required.');
        }

        // Support comma-separated multi-ability requirements
        $required = array_map('trim', explode(',', $ability));

        foreach ($required as $scope) {
            if (!$this->userCan($user, $scope)) {
                return $this->forbidden("Token missing required ability: [{$scope}].");
            }
        }

        return $next($request);
    }

    private function userCan(object $user, string $ability): bool
    {
        // Use HasApiTokens trait if available
        if (method_exists($user, 'tokenCan')) {
            return $user->tokenCan($ability);
        }

        // Fallback: check JWT claims directly
        $claims    = Auth::tokenClaims();
        $abilities = (array) ($claims['abilities'] ?? $claims['scopes'] ?? ['*']);

        if (in_array('*', $abilities, true)) {
            return true;
        }

        return in_array($ability, $abilities, true);
    }

    private function forbidden(string $message): Response
    {
        $body = json_encode(['error' => 'Forbidden', 'message' => $message], JSON_UNESCAPED_SLASHES);

        return new Response((string) $body, 403, [
            'Content-Type' => 'application/json',
        ]);
    }
}

