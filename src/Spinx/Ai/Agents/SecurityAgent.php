<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

final class SecurityAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'security';
    }

    public function getDescription(): string
    {
        return 'Specialized in session-backed CSRF, authentication guards, Personal Access Tokens (PAT), Stateless JWT, rate limiting, and CORS configuration for both full-stack and headless/API-only Spinx applications.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Security Agent Focus:
You configure all authentication and security concerns for Spinx modules, including both web session auth and API token auth.

### Session Authentication (Full-Stack Web Apps)
- Use `Auth::attempt(['email' => \$e, 'password' => \$p])` for session-backed login.
- Apply `AuthMiddleware` and `GuestMiddleware` to web routes in module.php.
- Wire `CsrfMiddleware` (already global) — always emit `@csrf` in POST forms.
- Hash passwords with `Hash::make(\$password)` (Argon2id).
- Use `Auth::logout()` for session invalidation.

### API Token Authentication (Headless / Mobile / SPA Clients)
- When building API endpoints, ALWAYS add `HasApiTokens` trait to User model.
- For token-based auth (Sanctum pattern): configure `API_AUTH_DRIVER=token` in .env.
- For stateless JWT auth: configure `API_AUTH_DRIVER=jwt` and set `JWT_SECRET` in .env.
- Protect API route groups with `['middleware' => ['auth:api']]` in module.php.
- Use `->middleware('ability:scope:name')` for fine-grained scope enforcement.
- Issue tokens in ApiAuthController: `\$user->createToken('device', ['*'])->plainTextToken`.
- Issue JWT: `Jwt::encode(\$user)` for access token + `Jwt::createRefreshToken(\$user)` for refresh.
- Always return 401 JSON for unauthenticated requests (not redirects).
- `NewAccessToken::plainTextToken` is exposed ONCE at creation. Never log or persist it.

### CORS Configuration (Required for Decoupled Frontends)
- Configure `config/cors.php` with explicit `allowed_origins` matching the frontend URL.
- NEVER combine `allowed_origins: ['*']` with `allow_credentials: true` (security vulnerability).
- Include `Authorization` in `allowed_headers` for bearer token support.

### Rate Limiting
- Apply `RateLimitMiddleware` to auth endpoints (login, register, token refresh).
- Use Redis-backed rate limits in production multi-worker environments.
PROMPT;
    }
}
