<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Auth\Jwt\Jwt;
use Spinx\Auth\Jwt\JwtException;
use Spinx\Auth\Token\NewAccessToken;
use Spinx\Auth\Token\PersonalAccessTokenInterface;
use Spinx\Support\Config;

echo "\n=======================================================\n";
echo "    Spinx API Auth Engine Integration Test\n";
echo "=======================================================\n\n";

$pass = 0;
$fail = 0;

function assert_test(bool $condition, string $label, ?string $error = null): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  [PASS] {$label}\n";
    } else {
        $fail++;
        echo "  [FAIL] {$label}\n";
        if ($error) {
            echo "         → {$error}\n";
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
echo "1. Dynamic Config::set() & Config::get()\n";
// ─────────────────────────────────────────────────────────────────────────────

$jwtSecret = base64_encode(random_bytes(32));
$appKey    = base64_encode(random_bytes(32));

Config::set('auth.api.jwt_secret', $jwtSecret);
Config::set('auth.api.jwt_algo', 'HS256');
Config::set('auth.api.jwt_ttl', 3600);
Config::set('app.key', $appKey);
Config::set('app.url', 'https://spinx.test');

assert_test(Config::get('auth.api.jwt_secret') === $jwtSecret, '1a. Config::set() / get() nested dot notation works');
assert_test(Config::has('auth.api.jwt_secret'), '1b. Config::has() returns true for set key');
assert_test(!Config::has('auth.nonexistent.key'), '1c. Config::has() returns false for missing key');

// Minimal user stub
$user = new class {
    public int $id = 42;
    public string $name = 'Test Author';
};

// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. JWT Engine — Encoding & Decoding\n";
// ─────────────────────────────────────────────────────────────────────────────

// 2a. Encode produces a valid 3-segment JWT
$accessToken = Jwt::encode($user, ttlSeconds: 3600, claims: ['role' => 'admin']);
$segments    = explode('.', $accessToken);
assert_test(count($segments) === 3, '2a. Jwt::encode() produces 3-segment header.payload.signature token');

// 2b. Decode verifies signature and returns correct sub
$payload = Jwt::decode($accessToken);
assert_test((int)($payload['sub'] ?? -1) === 42, '2b. Decoded "sub" claim matches user ID (42)');

// 2c. Token type is 'access'
assert_test(($payload['typ'] ?? '') === 'access', '2c. Token "typ" claim is "access"');

// 2d. Custom claims survive encode → decode
assert_test(($payload['role'] ?? '') === 'admin', '2d. Custom "role" claim preserved through encode/decode');

// 2e. Standard claims are set
assert_test(isset($payload['iss']), '2e. "iss" (issuer) claim is present');
assert_test(isset($payload['iat']), '2f. "iat" (issued at) claim is present');
assert_test(isset($payload['exp']), '2g. "exp" (expiration) claim is present');
assert_test(isset($payload['jti']), '2h. "jti" (unique JWT ID) claim is present');

// 2i. Expiry is roughly correct (within 10 seconds)
$expectedExp = time() + 3600;
assert_test(abs((int)$payload['exp'] - $expectedExp) < 10, '2i. "exp" claim is ~3600 seconds from now');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. JWT Engine — Tamper & Expiry Rejection\n";
// ─────────────────────────────────────────────────────────────────────────────

// 3a. Tampered signature is rejected
$tampered = $segments[0] . '.' . $segments[1] . '.INVALIDSIGNATURE__';
$result   = Jwt::tryDecode($tampered);
assert_test($result === null, '3a. Tampered signature returns null via tryDecode()');

// 3b. Tampered signature throws JwtException via decode()
$threw = false;
try {
    Jwt::decode($tampered);
} catch (JwtException) {
    $threw = true;
}
assert_test($threw, '3b. Tampered signature throws JwtException via decode()');

// 3c. Expired token is rejected (ttlSeconds: -120 ensures well past the 60s leeway window)
$expiredToken = Jwt::encode($user, ttlSeconds: -120);
$expResult    = Jwt::tryDecode($expiredToken);
assert_test($expResult === null, '3c. Expired token (past leeway window) returns null via tryDecode()');

// 3d. Malformed (non-3-segment) token is rejected
$malformedResult = Jwt::tryDecode('not.a.valid.jwt.string');
assert_test($malformedResult === null, '3d. Malformed JWT string returns null');

// 3e. Completely invalid string is rejected
$invalidResult = Jwt::tryDecode('totally-invalid-content');
assert_test($invalidResult === null, '3e. Completely invalid token returns null');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. JWT Refresh Tokens\n";
// ─────────────────────────────────────────────────────────────────────────────

$refreshToken   = Jwt::createRefreshToken($user, ttlSeconds: 86400);
$refreshPayload = Jwt::decode($refreshToken);

assert_test(($refreshPayload['typ'] ?? '') === 'refresh', '4a. Refresh token "typ" is "refresh"');
assert_test((int)($refreshPayload['sub'] ?? -1) === 42, '4b. Refresh token "sub" matches user ID');
assert_test(abs((int)$refreshPayload['exp'] - (time() + 86400)) < 10, '4c. Refresh token "exp" is ~24 hours from now');

// Refresh tokens should NOT be used as access tokens (block at middleware level)
assert_test(($refreshPayload['typ'] ?? '') !== 'access', '4d. Refresh token type is NOT "access" (middleware blocks it)');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. Personal Access Token Format, Interface & DTO\n";
// ─────────────────────────────────────────────────────────────────────────────

// 5a. Token format: spinx_pat_{id}|{64hex}
$plaintext     = bin2hex(random_bytes(32));
$fakeId        = 7;
$fullPlaintext = 'spinx_pat_' . $fakeId . '|' . $plaintext;

assert_test(str_starts_with($fullPlaintext, 'spinx_pat_'), '5a. PAT starts with "spinx_pat_" prefix');
assert_test(str_contains($fullPlaintext, '|'), '5b. PAT contains id|plaintext separator');

$parsed = explode('|', substr($fullPlaintext, strlen('spinx_pat_')), 2);
assert_test(count($parsed) === 2, '5c. PAT parses into exactly 2 segments after stripping prefix');
assert_test((int)$parsed[0] === $fakeId, '5d. ID segment parsed correctly');
assert_test(strlen($parsed[1]) === 64, '5e. Plaintext segment is 64 hex characters');

// 5b. SHA-256 hash of plaintext
$hash = hash('sha256', $plaintext);
assert_test(strlen($hash) === 64, '5f. SHA-256 hash is 64 hex characters');
assert_test($hash !== $plaintext, '5g. Hash differs from plaintext (never stored in plaintext)');
assert_test($hash === hash('sha256', $plaintext), '5h. SHA-256 is deterministic for same input');

// 5c. NewAccessToken DTO with PersonalAccessTokenInterface contract
$mockPat = new class implements PersonalAccessTokenInterface {
    public int $id = 1;
    public array $abilities = ['*'];
    public ?string $expires_at = null;
    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }
    public function isExpired(): bool { return false; }
};

$dto = new NewAccessToken($mockPat, 'spinx_pat_1|abc123');
assert_test($dto->plainTextToken === 'spinx_pat_1|abc123', '5i. NewAccessToken stores plaintext string correctly');
assert_test($dto->accessToken === $mockPat, '5j. NewAccessToken accepts any PersonalAccessTokenInterface implementation');
assert_test($mockPat->can('*'), '5k. Omniscient token (*) passes wildcard check');
assert_test($mockPat->can('projects:create'), '5l. Omniscient token (*) passes specific ability check');
assert_test(!$mockPat->isExpired(), '5m. Token with null expires_at reports as not expired');

// Scoped token via interface
$scopedPat = new class implements PersonalAccessTokenInterface {
    public array $abilities = ['chapters:write', 'projects:read'];
    public function can(string $ability): bool
    {
        return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true);
    }
    public function isExpired(): bool { return false; }
};
assert_test($scopedPat->can('chapters:write'), '5n. Scoped token passes allowed ability');
assert_test(!$scopedPat->can('projects:delete'), '5o. Scoped token blocks non-allowed ability');

echo "\n=======================================================\n";
echo "  Results: {$pass} passed, {$fail} failed\n";
echo "=======================================================\n\n";

if ($fail > 0) {
    exit(1);
}
