# Security

None of the middleware below is attached globally by default — attach
it per-route or per-module via a route's `_middleware` default (see
[routing-and-controllers.md](routing-and-controllers.md#middleware)).
An app with no forms has no reason to pay CSRF's cost; a pure
server-to-server API has no reason to pay CORS preflight handling. The
`Health` and `Todo` modules are live, working reference examples for
all four — read their `module.php` files alongside this doc.

## XSS — already on by default, not something you attach

Every `{{ $expr }}` in a `.spinx.html` template is `htmlspecialchars()`-escaped
automatically by the directive compiler — this isn't middleware, it's
built into how templates compile, so there's nothing to remember to turn
on. Use `{!! $expr !!}` only for content you've deliberately decided is
safe raw HTML (and never for anything derived from user input).

## CSRF — `Spinx\Http\Middleware\CsrfMiddleware`

Double-submit-cookie pattern (not session-backed — no session subsystem
exists in this framework). A token is set as a cookie and echoed into
forms via `@csrf`; a POST/PUT/PATCH/DELETE is only accepted if the
submitted token matches the cookie.

```php
'_middleware' => [CsrfMiddleware::class],
```
```html
<form method="POST" action="/todos">
    @csrf
    <input type="text" name="title">
</form>
```

Attach it to the **GET route too**, not just the form-submitting POST —
the cookie has to be set on some response before there's a token to
submit back. See `Todo`'s `module.php` for the working three-route
example.

## CORS — `Spinx\Http\Middleware\CorsMiddleware`

Config-driven via `config/cors.php` / `.env`:
```
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_CREDENTIALS=false
```

Reflects the request's actual `Origin` back rather than literally
sending `*` when credentials are allowed — the CORS spec forbids
combining a wildcard origin with credentialed requests, and browsers
enforce this by rejecting the response outright, so this handles it
correctly rather than silently sending a header that won't work.

## Rate limiting — `Spinx\Http\Middleware\RateLimitMiddleware`

Config-driven via `config/rate_limit.php` / `.env`:
```
RATE_LIMIT_MAX_ATTEMPTS=60
RATE_LIMIT_DECAY_SECONDS=60
```

**Read before relying on this in production:** the default store
(`InMemoryRateLimitStore`) only tracks attempts within a single worker
process. RoadRunner and Swoole both run a *pool* of workers — this
store's counts aren't shared across them, so the effective limit is
closer to `(configured limit × worker count)` than the number you set.
Correct for single-worker dev setups or genuinely low traffic; for real
production traffic, implement `Spinx\Http\RateLimit\RateLimitStore`
against Redis (or similar) and register it in `config/container.php` in
place of the default — same interface, one swap.

## Security headers — `SecurityHeadersMiddleware` (per-module, not a framework class)

The `Health` module's `SecurityHeadersMiddleware` (in its own
`Infrastructure/Http/Middleware/`) is a template for the security
headers pattern, not a framework-provided class — copy it into your own
module and adjust for your needs (`X-Content-Type-Options`,
`X-Frame-Options`, `Content-Security-Policy`, etc. — what's appropriate
varies enough by app that this stays a documented pattern rather than a
one-size-fits-all default).

## Everything else

- **SQL injection**: every `QueryBuilder` method binds parameters, never
  interpolates raw values into SQL strings — this isn't opt-in.
- **Mass-assignment**: `Model::$fillable` guards `fill()`/`create()`
  by default — anything not listed is silently dropped, mirroring
  Eloquent's guarded-by-default behavior.
- **Password hashing**: not yet provided as a framework helper — use
  PHP's built-in `password_hash()`/`password_verify()` (bcrypt/argon2),
  which need no framework wrapper to use correctly.
