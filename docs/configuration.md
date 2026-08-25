# Configuration & Environment

## `.env`

Copy `.env.example` to `.env` (done automatically by `spinx new`) and fill
in real values. `.env` is gitignored — never commit real secrets. Loaded
once at boot (`Kernel::boot()`, via `vlucas/phpdotenv`), not per-request.
Real environment variables set by your OS or container orchestrator
always take precedence — Dotenv never overwrites an already-set variable,
which is exactly right for production deploys that set real env vars
instead of shipping a `.env` file.

## `env()` and `config()`

```php
env('APP_DEBUG');           // reads .env / real env vars, with type casting:
                             // "true"/"false"/"null"/"empty" become their PHP equivalents
env('SOME_KEY', 'fallback'); // default if unset

config('database.driver');           // reads config/database.php
config('services.paystack.secret_key'); // dot notation into config/services.php
config('missing.key', 'fallback');   // default if any segment is missing
```

Every file in `config/` (except `container.php`) becomes accessible this
way — one top-level key per filename, matching Laravel's convention
exactly. Add your own `config/whatever.php` returning an array and
`config('whatever.anything')` works immediately, no registration step.

## `config/container.php` vs. `config/services.php` — don't confuse these

- **`config/container.php`** — Symfony DI wiring for Spinx's own internal
  services (templating, database connection factory, etc.). Framework
  plumbing, rarely touched.
- **`config/services.php`** — third-party API credentials (Paystack,
  Stripe, Resend, Mailgun), a plain array read via `config()`. This is
  the file you edit constantly as you integrate services. See
  [external-services.md](external-services.md).

These two files had the same name earlier in this project's history and
were genuinely confusing — the rename to `container.php` is deliberate,
not cosmetic.

## Other config files

| File | Purpose |
|---|---|
| `config/app.php` | Name, env, debug flag, URL |
| `config/database.php` | DB driver/credentials — see [database.md](database.md) |
| `config/mail.php` | Mail driver/credentials — see [mail-and-queues.md](mail-and-queues.md) |
| `config/services.php` | Third-party API credentials — see [external-services.md](external-services.md) |
| `config/cors.php` | CORS policy — see [security.md](security.md) |
| `config/rate_limit.php` | Rate limiting thresholds — see [security.md](security.md) |

`spinx.json` (separate from `config/`) stays reserved for
framework/infrastructure-level settings that aren't really "app config"
in the Laravel sense: the runtime driver (RoadRunner vs. Swoole),
frontend choice, and the module registry.
