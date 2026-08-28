<?php

declare(strict_types=1);

namespace Spinx\Auth\Middleware;

use Spinx\Auth\Auth;
use Spinx\Auth\Jwt\Jwt;
use Spinx\Auth\Jwt\JwtException;
use Spinx\Auth\Token\Token;
use Spinx\Support\Config;

/**
 * AuthenticateApi — HTTP middleware for API bearer token authentication.
 *
 * Supports two authentication drivers configurable via config/auth.php:
 *
 * 1. 'token' (Personal Access Tokens):
 *    Inspects "Authorization: Bearer spinx_pat_{id}|{plaintext}" header.
 *    Performs SHA-256 hash lookup in the personal_access_tokens table.
 *    Touches last_used_at on success.
 *
 * 2. 'jwt' (Stateless JSON Web Tokens):
 *    Inspects "Authorization: Bearer {header.payload.signature}" header.
 *    Performs HMAC-SHA256 signature verification entirely in-memory.
 *    Resolves user from JWT 'sub' (user ID) claim.
 *
 * Register in your route group with:
 *   $routes->group(['middleware' => ['auth:api']], ...);
 *
 * On failure, returns a JSON 401 Unauthorized response immediately,
 * without reaching the controller.
 */
final class AuthenticateApi
{
    private string $driver;

    public function __construct()
    {
        $this->driver = Config::get('auth.api.driver', 'token');
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Closure(mixed): mixed  $next
     */
    public function handle(mixed $request, \Closure $next): mixed
    {
        $bearer = $this->extractBearer();

        if ($bearer === null) {
            return $this->unauthorized('Missing Authorization: Bearer token.');
        }

        if ($this->driver === 'jwt') {
            return $this->handleJwt($bearer, $request, $next);
        }

        return $this->handlePat($bearer, $request, $next);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAT Driver
    // ─────────────────────────────────────────────────────────────────────────

    private function handlePat(string $bearer, mixed $request, \Closure $next): mixed
    {
        $token = Token::findToken($bearer);

        if ($token === null) {
            return $this->unauthorized('Invalid or expired token.');
        }

        // Resolve user via tokenable_type and tokenable_id
        $userClass = $token->tokenable_type;

        if (!class_exists($userClass)) {
            return $this->unauthorized('Token references unknown entity class.');
        }

        $user = $userClass::find($token->tokenable_id);

        if ($user === null) {
            return $this->unauthorized('Authenticated user not found.');
        }

        // Bind the active token to the user for ability checking
        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($token);
        }

        // Update last_used_at (non-blocking)
        $token->touchLastUsed();

        // Bind user to Auth facade for the duration of this request
        Auth::setUser($user);

        return $next($request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JWT Driver
    // ─────────────────────────────────────────────────────────────────────────

    private function handleJwt(string $bearer, mixed $request, \Closure $next): mixed
    {
        try {
            $payload = Jwt::decode($bearer);
        } catch (JwtException $e) {
            return $this->unauthorized($e->getMessage());
        }

        // Validate token type — block refresh tokens from being used as access tokens
        if (($payload['typ'] ?? 'access') !== 'access') {
            return $this->unauthorized('Refresh tokens cannot be used for API access.');
        }

        // Resolve user from 'sub' claim
        $userId    = $payload['sub'] ?? null;
        $userClass = Config::get('auth.providers.users.model', null);

        if ($userId === null || $userClass === null || !class_exists($userClass)) {
            return $this->unauthorized('JWT subject cannot be resolved to a user.');
        }

        $user = $userClass::find($userId);

        if ($user === null) {
            return $this->unauthorized('Authenticated user not found.');
        }

        // Stash JWT claims for tokenCan() ability checks in HasApiTokens trait
        Auth::setTokenClaims($payload);
        Auth::setUser($user);

        return $next($request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function extractBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return null;
    }

    private function unauthorized(string $message): mixed
    {
        $body = json_encode(['error' => 'Unauthorized', 'message' => $message], JSON_UNESCAPED_SLASHES);

        header('Content-Type: application/json', replace: true, response_code: 401);
        echo $body;

        // Signal the kernel to halt further processing
        return false;
    }
}
