# Security & Session-Backed CSRF

Spinx delivers persistent-worker-safe security subsystems including **session-backed CSRF protection with token rotation**, session fixation defense, and auth middleware guards.

---

## 1. Session-Backed CSRF Protection

Spinx CSRF protection is tied directly to the user's active session (`SessionInterface` under `_token`):

1. **Generation:** When a session begins or regenerates, a cryptographically secure 64-character hex token is assigned.
2. **Verification:** On state-changing HTTP methods (`POST`, `PUT`, `PATCH`, `DELETE`), `CsrfMiddleware` checks the submitted token against the session token.
3. **Cookie Synchronization:** On every response, `CsrfMiddleware` synchronizes the active token to a readable `XSRF-TOKEN` cookie, allowing frontend JavaScript clients (axios/fetch/Vue/React) to read and send it automatically in headers (`X-CSRF-TOKEN` or `X-XSRF-TOKEN`).

---

## 2. Using `@csrf` in Templates

Include the `@csrf` directive in every HTML form:

```html
<form method="POST" action="/todos">
    @csrf
    <input type="text" name="title" placeholder="New todo item" required />
    <button type="submit">Create Todo</button>
</form>
```

This compiles to:
```html
<input type="hidden" name="_token" value="4f8a9e7d..." />
```

---

## 3. Token Rotation on Authentication

To prevent session fixation and CSRF hijacking, regenerate the token during login and logout:

```php
use Spinx\Security\Csrf;

// Regenerate upon login:
Csrf::regenerateToken($session);
```

---

## 4. Auth & Guest Middleware Guards

Spinx includes built-in middleware for guarding routes:

```php
// app/Modules/Auth/module.php
return [
    'middlewares' => static function ($r): void {
        $r->registerMiddleware('auth', \Spinx\Auth\Middleware\AuthMiddleware::class);
        $r->registerMiddleware('guest', \Spinx\Auth\Middleware\GuestMiddleware::class);
        $r->registerMiddleware('csrf', \Spinx\Http\Middleware\CsrfMiddleware::class);
    },

    'routes' => static function (RouteBuilder $routes): void {
        // Authenticated users only
        Route::get(['auth.dashboard', '/dashboard'])
            ->middleware(['auth', 'csrf'])
            ->controller('auth@dashboard');

        // Unauthenticated guests only
        Route::get(['auth.login', '/login'])
            ->middleware(['guest', 'csrf'])
            ->controller('auth@showLogin');
    },
];
```
