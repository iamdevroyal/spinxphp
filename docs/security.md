# Production Security & Hardening

Spinx is built with a security-first philosophy across every subsystem. This guide covers the attack vectors addressed at the framework level — so developers building on Spinx are not exposed to common PHP vulnerabilities by default.

---

## 🛡️ 1. Cryptographic Queue Payload Signing (Anti-RCE)

**Attack:** PHP Object Injection via unserialized queue payloads leading to Remote Code Execution (RCE).

**Defense:** Every job pushed to the queue is serialized and then signed with `HMAC-SHA256` using the application's `APP_KEY`. The worker daemon verifies the signature before calling `unserialize()`. Forged or tampered payloads are rejected.

```php
// DO: Use Spinx Queue API — signing is automatic
Queue::push(new ProcessInvoiceJob($id));

// NEVER manually deserialize queue payloads
// unserialize($rawPayload) ← FORBIDDEN — HMAC verification bypassed
```

---

## 🛡️ 2. Path Traversal & Null-Byte Injection Defense (Storage)

**Attack:** `Storage::get('../../.env')` — reading arbitrary filesystem files outside the storage root.

**Defense:** `LocalFilesystemDriver::fullPath()` strips null bytes, normalizes directory separators, and throws `\InvalidArgumentException` immediately if any `..` segment is detected. Paths are jail-rooted to the configured disk root.

```php
// BLOCKED by framework — throws InvalidArgumentException:
Storage::get('../../../.env');
Storage::put("..\\..\\.htaccess", "deny from all");
```

---

## 🛡️ 3. Secure CORS Origin Matching

**Attack:** `Access-Control-Allow-Origin: *` combined with `Access-Control-Allow-Credentials: true` allows any origin to make credentialed cross-origin requests, effectively bypassing CSRF protections.

**Defense:** Spinx's `CorsMiddleware` enforces an invariant: if `allow_credentials: true`, wildcard `*` is **never** reflected as the origin. Only origins explicitly listed in `allowed_origins` config may be echoed back with credentials.

```php
// config/cors.php
return [
    'allowed_origins'  => ['https://app.mysite.com', 'https://admin.mysite.com'],
    'allow_credentials'=> true,
    // wildcard '*' combined with credentials is auto-blocked
];
```

---

## 🛡️ 4. CSRF Token Coroutine Isolation (Persistent Worker Safety)

**Attack:** In persistent workers (RoadRunner/Swoole), static CSRF tokens from one request bleed into the next if not reset, allowing cross-request forgery.

**Defense:** `Csrf::reset()` is automatically called in the `finally` block of every `Kernel::handle()` invocation. The CSRF token is also isolated per Swoole coroutine ID so concurrent coroutines can never share tokens.

---

## 🛡️ 5. Production AI Dashboard Route Shielding

**Attack:** Unauthenticated access to `/_spinx/ai/*` routes in production, exposing internal AI tools, build capabilities, or framework internals.

**Defense:** AI dashboard routes are entirely disabled in `APP_ENV=production` unless explicitly authorized:

```env
# .env — opt-in only when intentionally exposing the AI dashboard (behind auth)
SPINX_AI_DASHBOARD_ENABLED=true
```

---

## 🛡️ 6. SQL Injection Hardening in QueryBuilder

**Attack:** Passing user-controlled strings as `$direction` in `QueryBuilder::orderBy()` to inject arbitrary SQL.

**Defense:** Direction is normalized to a strict whitelist: `strtoupper(trim($direction)) === 'DESC' ? 'DESC' : 'ASC'`. Any other value — including `ASC; DROP TABLE users;` — defaults to `ASC`.

---

## 🛡️ 7. Cryptographic Webhook Signature Verification

Verify incoming webhook signatures from Stripe, GitHub, Slack, or any HMAC-provider:

```php
use Spinx\Webhooks\HmacWebhookVerifier;

$verifier = new HmacWebhookVerifier(secret: env('STRIPE_WEBHOOK_SECRET'));

// Stripe-style: "t=...,v1=..." timestamped signature header
if (!$verifier->verifyStripe($request, maxAgeSeconds: 300)) {
    return response()->json(['error' => 'Invalid signature'], 403);
}

// Generic HMAC-SHA256 header verification
if (!$verifier->verify($request, headerName: 'X-Hub-Signature-256')) {
    abort(403);
}
```

> **Important:** Always call `Request::rawBody()` before reading `$request->json()` or `$request->all()`. Parsing the request body before verification changes the raw content hash.

---

## 🛡️ 8. Route CSRF Exemptions

Webhook-receiving endpoints must be excluded from CSRF protection:

```php
// app/Modules/Billing/module.php
RouteBuilder::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutCsrf(); // Excludes route from CSRF middleware validation
```

---

## 🔒 Security Headers Reference

Add security headers globally in your kernel middleware:

```php
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'geolocation=(), camera=()');
$response->headers->set(
    'Content-Security-Policy',
    "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline';"
);
```
