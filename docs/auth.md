# Authentication & Sessions

Spinx includes a built-in authentication and session management subsystem engineered specifically for persistent-worker runtimes (RoadRunner & Swoole).

## Persistent Session Architecture

Traditional PHP global `$_SESSION` variables are not safe in persistent worker runtimes because global state persists across requests.

Spinx provides a request-isolated `SessionInterface` that loads data from external storage at request start and saves it at response end:

- **FileSession (Default)**: Stored as JSON files in `storage/sessions/{id}.json`.
- **DatabaseSession**: Stored in the `spinx_sessions` table for multi-node deployments.

Configure drivers in `config/session.php`:

```php
return [
    'driver'    => env('SESSION_DRIVER', 'file'),
    'lifetime'  => (int) env('SESSION_LIFETIME', 120),
    'secure'    => (bool) env('SESSION_SECURE_COOKIE', false),
    'same_site' => env('SESSION_SAME_SITE', 'Lax'),
];
```

## The Auth Façade

Authenticate users and query auth state via the `Spinx\Auth\Auth` static façade:

```php
use Spinx\Auth\Auth;

// Attempt login with credentials:
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    $user = Auth::user(); // Returns authenticated user model
}

// Check status:
if (Auth::check()) {
    $userId = Auth::id();
}

// Log out:
Auth::logout();
```

> **Security Note:** `Auth::attempt()` and `Auth::login()` automatically call `$session->regenerate()`, preventing session fixation attacks.

## Password Hashing

`Spinx\Auth\Hash` uses standard bcrypt (`PASSWORD_BCRYPT`) with configurable cost:

```php
use Spinx\Auth\Hash;

$hash = Hash::make('plain_password', cost: 12);
$isValid = Hash::check('plain_password', $hash);
```

## Route Middlewares

Attach auth middlewares via aliases in `module.php`:

```php
// Protect authenticated routes:
Route::get(['dashboard', '/dashboard'])
    ->middleware(['auth'])
    ->controller('dashboard_controller');

// Protect guest-only routes (redirects authenticated users):
Route::get(['login', '/login'])
    ->middleware(['guest'])
    ->controller('login_controller');
```
