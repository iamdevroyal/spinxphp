# Routing & Controllers

Spinx features a fluent routing DSL with **multi-action controller support** and built-in facades for Requests, Responses, and Views.

---

## 1. Multi-Action Controller Syntax

Controllers can group multiple actions (`index`, `store`, `show`, `update`, `destroy`) in a single class:

```php
// app/Modules/Todo/module.php
use App\Modules\Todo\Infrastructure\Http\Controllers\TodoController;
use Spinx\Routing\Route;
use Spinx\Routing\RouteBuilder;

return [
    'controllers' => static function ($r): void {
        $r->registerController('todo', TodoController::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        Route::get(['todo.index', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo@index');

        Route::post(['todo.create', '/todos'])
            ->middleware(['csrf'])
            ->controller('todo@store');

        Route::post(['todo.toggle', '/todos/{id}/toggle'])
            ->middleware(['csrf'])
            ->controller('todo@toggle');
    },
];
```

You can also reference controller class methods directly via array syntax:
```php
Route::get('/login')->controller([AuthController::class, 'showLogin']);
```

---

## 2. Controller Implementation & Facades

Spinx controllers strictly handle HTTP extraction, validation, calling Application Services, and returning Responses:

```php
namespace App\Modules\Todo\Infrastructure\Http\Controllers;

use App\Modules\Todo\Application\Services\TodoService;
use Spinx\Http\Request;
use Spinx\Http\Response;

final class TodoController
{
    public function __construct(
        private readonly TodoService $todoService,
    ) {}

    public function index(): Response
    {
        $todos = $this->todoService->listTodos();

        return view('Todo::index', [
            'title' => 'Todos',
            'todos' => $todos,
        ]);
    }

    public function store(): Response
    {
        $data = Request::validate([
            'title' => 'required|string|min:1|max:255',
        ]);

        $this->todoService->createTodo($data['title']);

        return redirect('/todos');
    }
}
```

---

## 3. The `Request` Facade

Access request data statically without boilerplate:

```php
use Spinx\Http\Request;

$all     = Request::all();
$email   = Request::input('email');
$only    = Request::only(['name', 'email']);
$except  = Request::except(['password']);
$ip      = Request::ip();
$method  = Request::method();
$isPost  = Request::isMethod('POST');
$isAjax  = Request::ajax();
$wantsJson = Request::wantsJson();
$file    = Request::file('avatar');
$user    = Request::user();

// Inline Validation
$data = Request::validate([
    'email'    => 'required|email|max:255',
    'password' => 'required|string|min:8',
]);
```

---

## 4. The `Response` & `JsonResponse` Facade

`Spinx\Http\Response` serves as both the factory and return type:

```php
use Spinx\Http\Response;
use Spinx\Http\JsonResponse;

// JSON APIs
return Response::json(['data' => $items], 200);
return Response::jsonSuccess(['id' => 123]);
return Response::jsonError('Could not process order', 400);

// API Status Envelopes
return JsonResponse::validationError($errors); // 422
return JsonResponse::notFound('Item not found'); // 404
return JsonResponse::unauthorized();          // 401
return JsonResponse::forbidden();             // 403

// HTML & Redirects
return Response::html('<h1>Success</h1>');
return Response::redirect('/dashboard');
return redirect('/dashboard');
return Response::noContent();
```

---

## 5. The `View` Facade & `view()` Helper

Render templates and return complete HTTP responses:

```php
// Shorthand helper returning Spinx\Http\Response
return view('Auth::login', [
    'title' => 'Sign In',
    'errors' => [],
]);

// View facade for raw HTML string
$html = \Spinx\Templating\View::make('emails.welcome', ['name' => 'Alice']);
```
