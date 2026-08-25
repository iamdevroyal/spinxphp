# Routing, Controllers & Middleware

Routes are declared per-module in `app/Modules/<Name>/module.php` using Spinx's fluent `Route` DSL and string alias resolution.

```php
use App\Modules\Orders\Infrastructure\Http\Controllers\OrderShowController;
use Spinx\Auth\Middleware\AuthMiddleware;
use Spinx\Http\Middleware\RateLimitMiddleware;
use Spinx\Routing\{AliasRegistry, Route, RouteBuilder};

return [
    // Register controller aliases:
    'controllers' => static function (AliasRegistry $r): void {
        $r->registerController('order_show', OrderShowController::class);
    },

    // Register middleware aliases:
    'middlewares' => static function (AliasRegistry $r): void {
        $r->registerMiddleware('auth',       AuthMiddleware::class);
        $r->registerMiddleware('rate_limit', RateLimitMiddleware::class);
    },

    // Declare routes using fluent DSL:
    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['orders.show', '/orders/{id}'])
            ->middleware(['auth', 'rate_limit'])
            ->controller('order_show');
    },
];
```

## Fluent Route DSL

Spinx provides intuitive HTTP method shorthands:

```php
Route::get(['route.name', '/path'])->controller('alias');
Route::post(['route.name', '/path'])->controller('alias');
Route::put(['route.name', '/path'])->controller('alias');
Route::patch(['route.name', '/path'])->controller('alias');
Route::delete(['route.name', '/path'])->controller('alias');
```

### Route Groups & Prefixes
Nest routes with shared prefixes:

```php
Route::group('/api/v1', function (RouteBuilder $group): void {
    Route::get(['users.index', '/users'])->controller('user_list');
    Route::get(['users.show', '/users/{id}'])->controller('user_show');
});
```

## Controllers

Controllers are invokable classes (`__invoke(Request): Response`). All controllers registered via `$r->registerController()` are automatically wired into the Symfony DI container with autowiring enabled:

```php
namespace App\Modules\Orders\Infrastructure\Http\Controllers;

use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use App\Modules\Orders\Application\Services\OrderService;

final class OrderShowController
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function __invoke(Request $request, string $id): Response
    {
        $order = $this->orders->find($id);

        return new JsonResponse(['order' => $order]);
    }
}
```

Path parameters (`{id}` above) are passed to `__invoke()` as positional arguments following the `Request` object.

## Middleware

Middlewares wrap requests in onion-order. Register aliases in `module.php`'s `middlewares` closure:

```bash
php spinx make:middleware Orders RateLimit
```

```php
namespace App\Modules\Orders\Infrastructure\Http\Middleware;

use Symfony\Component\HttpFoundation\{Request, Response};

final class RateLimitMiddleware
{
    public function process(Request $request, \Closure $next): Response
    {
        // Pre-controller inspection
        $response = $next($request);
        // Post-controller headers
        $response->headers->set('X-RateLimit-Remaining', '99');

        return $response;
    }
}
```
