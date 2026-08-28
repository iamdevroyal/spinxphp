<?php

declare(strict_types=1);

namespace Spinx\Auth\Jwt;

use Spinx\Support\Config;

/**
 * Jwt — Zero-dependency RFC 7519 JSON Web Token engine for Spinx.
 *
 * Algorithms:
 *   - HS256 (HMAC-SHA256, default): Uses APP_KEY / JWT_SECRET
 *   - HS512 (HMAC-SHA512): Uses APP_KEY / JWT_SECRET
 *
 * Usage:
 *
 *   // Issue access token (1 hour)
 *   $token = Jwt::encode($user);
 *
 *   // Issue access token with custom TTL and extra claims
 *   $token = Jwt::encode($user, ttlSeconds: 900, claims: ['role' => 'admin']);
 *
 *   // Issue refresh token (30 days)
 *   $refresh = Jwt::createRefreshToken($user);
 *
 *   // Verify & decode (throws JwtException on failure)
 *   $payload = Jwt::decode($token);
 *   $userId  = $payload['sub'];
 *
 *   // Returns null instead of throwing
 *   $payload = Jwt::tryDecode($token);
 */
final class Jwt
{
    // ─────────────────────────────────────────────────────────────────────────
    // Static API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Encode a JWT access token for the given user entity.
     *
     * @param object   $user        Entity with ->id or getId()
     * @param int      $ttlSeconds  Token lifetime in seconds (default: 3600 = 1h)
     * @param array<string,mixed> $claims  Extra claims merged into payload
     * @return string  Signed JWT string (header.payload.signature)
     */
    public static function encode(
        object $user,
        int $ttlSeconds = 3600,
        array $claims = [],
    ): string {
        $now    = time();
        $secret = self::secret();
        $algo   = self::algo();

        $payload = array_merge([
            'iss' => Config::get('app.url', 'spinx'),          // issuer
            'sub' => self::resolveId($user),                   // subject (user ID)
            'iat' => $now,                                     // issued at
            'exp' => $now + $ttlSeconds,                       // expiration
            'jti' => bin2hex(random_bytes(8)),                 // unique JWT ID
            'typ' => 'access',
        ], $claims);

        return self::build($payload, $secret, $algo);
    }

    /**
     * Create a long-lived refresh token for token rotation.
     *
     * @param object $user        Entity with ->id or getId()
     * @param int    $ttlSeconds  Default: 30 days (2592000 seconds)
     * @return string  Signed JWT refresh token
     */
    public static function createRefreshToken(object $user, int $ttlSeconds = 2592000): string
    {
        $now    = time();
        $secret = self::secret();
        $algo   = self::algo();

        $payload = [
            'iss' => Config::get('app.url', 'spinx'),
            'sub' => self::resolveId($user),
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(8)),
            'typ' => 'refresh',
        ];

        return self::build($payload, $secret, $algo);
    }

    /**
     * Decode and verify a JWT. Throws JwtException on any validation failure.
     *
     * @param string $token      Raw JWT string (header.payload.signature)
     * @param int    $leeway     Clock skew tolerance in seconds (default: 60)
     * @return array<string,mixed>  Decoded payload claims
     * @throws JwtException
     */
    public static function decode(string $token, int $leeway = 60): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new JwtException('Malformed JWT: expected 3 segments, got ' . count($parts));
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        // 1. Verify signature
        $secret    = self::secret();
        $algo      = self::algo();
        $expected  = self::sign($headerB64 . '.' . $payloadB64, $secret, $algo);

        if (!hash_equals($expected, $sigB64)) {
            throw new JwtException('JWT signature verification failed.');
        }

        // 2. Decode payload
        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload     = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new JwtException('JWT payload is not valid JSON.');
        }

        // 3. Validate standard claims
        $now = time();

        if (isset($payload['exp']) && ($payload['exp'] + $leeway) < $now) {
            throw new JwtException('JWT has expired.');
        }

        if (isset($payload['nbf']) && ($payload['nbf'] - $leeway) > $now) {
            throw new JwtException('JWT is not yet valid (nbf claim).');
        }

        return $payload;
    }

    /**
     * Decode and verify a JWT, returning null on failure instead of throwing.
     *
     * @return array<string,mixed>|null  Decoded payload or null
     */
    public static function tryDecode(string $token, int $leeway = 60): ?array
    {
        try {
            return self::decode($token, $leeway);
        } catch (JwtException) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $payload
     */
    private static function build(array $payload, string $secret, string $algo): string
    {
        $headerB64  = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => $algo], JSON_THROW_ON_ERROR));
        $payloadB64 = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $sigB64     = self::sign($headerB64 . '.' . $payloadB64, $secret, $algo);

        return $headerB64 . '.' . $payloadB64 . '.' . $sigB64;
    }

    private static function sign(string $data, string $secret, string $algo): string
    {
        $hmacAlgo = match ($algo) {
            'HS512' => 'sha512',
            default => 'sha256',
        };

        return self::base64UrlEncode(hash_hmac($hmacAlgo, $data, $secret, binary: true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
        $decoded = base64_decode($padded, strict: true);
        if ($decoded === false) {
            throw new JwtException('JWT segment is not valid base64url.');
        }
        return $decoded;
    }

    private static function secret(): string
    {
        $secret = Config::get('auth.api.jwt_secret', '') ?: Config::get('app.key', '');
        if ($secret === '') {
            throw new \RuntimeException('JWT secret is not configured. Set JWT_SECRET or APP_KEY in your .env file.');
        }
        return $secret;
    }

    private static function algo(): string
    {
        return Config::get('auth.api.jwt_algo', 'HS256');
    }

    private static function resolveId(object $entity): int|string
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }
        if (isset($entity->id)) {
            return $entity->id;
        }
        throw new \LogicException(get_class($entity) . ' must expose ->id or getId() for JWT subject.');
    }
}
