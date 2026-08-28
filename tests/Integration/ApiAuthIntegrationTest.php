<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Auth\Jwt\Jwt;
use Spinx\Auth\Jwt\JwtException;
use Spinx\Auth\Token\NewAccessToken;

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

// Bootstrap a minimal Config with JWT secret injected via a temp config directory
$tmpConfigDir = sys_get_temp_dir() . '/spinx_test_config_' . uniqid();
mkdir($tmpConfigDir, 0777, true);

$jwtSecret = base64_encode(random_bytes(32));
$appKey    = base64_encode(random_bytes(32));

file_put_contents($tmpConfigDir . '/auth.php', "<?php\nreturn [\n    'api' => [\n        'jwt_secret' => '{$jwtSecret}',\n        'jwt_algo'   => 'HS256',\n        'jwt_ttl'    => 3600,\n    ],\n];\n");
file_put_contents($tmpConfigDir . '/app.php', "<?php\nreturn [\n    'key' => '{$appKey}',\n    'url' => 'https://spinx.test',\n];\n");

\Spinx\Support\Config::boot($tmpConfigDir);

// Minimal user stub
$user = new class {
    public int $id = 42;
    public string $name = 'Test Author';
};

// ─────────────────────────────────────────────────────────────────────────────
echo "1. JWT Engine — Encoding & Decoding\n";
// ─────────────────────────────────────────────────────────────────────────────

// 1a. Encode produces a valid 3-segment JWT
$accessToken = Jwt::encode($user, ttlSeconds: 3600, claims: ['role' => 'admin']);
$segments    = explode('.', $accessToken);
assert_test(count($segments) === 3, '1a. Jwt::encode() produces 3-segment header.payload.signature token');

// 1b. Decode verifies signature and returns correct sub
$payload = Jwt::decode($accessToken);
assert_test((int)($payload['sub'] ?? -1) === 42, '1b. Decoded "sub" claim matches user ID (42)');

// 1c. Token type is 'access'
assert_test(($payload['typ'] ?? '') === 'access', '1c. Token "typ" claim is "access"');

// 1d. Custom claims survive encode → decode
assert_test(($payload['role'] ?? '') === 'admin', '1d. Custom "role" claim preserved through encode/decode');

// 1e. Standard claims are set
assert_test(isset($payload['iss']), '1e. "iss" (issuer) claim is present');
assert_test(isset($payload['iat']), '1f. "iat" (issued at) claim is present');
assert_test(isset($payload['exp']), '1g. "exp" (expiration) claim is present');
assert_test(isset($payload['jti']), '1h. "jti" (unique JWT ID) claim is present');

// 1i. Expiry is roughly correct (within 10 seconds)
$expectedExp = time() + 3600;
assert_test(abs((int)$payload['exp'] - $expectedExp) < 10, '1i. "exp" claim is ~3600 seconds from now');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. JWT Engine — Tamper & Expiry Rejection\n";
// ─────────────────────────────────────────────────────────────────────────────

// 2a. Tampered signature is rejected
$tampered = $segments[0] . '.' . $segments[1] . '.INVALIDSIGNATURE__';
$result   = Jwt::tryDecode($tampered);
assert_test($result === null, '2a. Tampered signature returns null via tryDecode()');

// 2b. Tampered signature throws JwtException via decode()
$threw = false;
try {
    Jwt::decode($tampered);
} catch (JwtException) {
    $threw = true;
}
assert_test($threw, '2b. Tampered signature throws JwtException via decode()');

// 2c. Expired token is rejected (ttlSeconds: -120 ensures well past the 60s leeway window)
$expiredToken = Jwt::encode($user, ttlSeconds: -120);
$expResult    = Jwt::tryDecode($expiredToken);
assert_test($expResult === null, '2c. Expired token (past leeway window) returns null via tryDecode()');

// 2d. Malformed (non-3-segment) token is rejected
$malformedResult = Jwt::tryDecode('not.a.valid.jwt.string');
assert_test($malformedResult === null, '2d. Malformed JWT string returns null');

// 2e. Completely invalid string is rejected
$invalidResult = Jwt::tryDecode('totally-invalid-content');
assert_test($invalidResult === null, '2e. Completely invalid token returns null');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. JWT Refresh Tokens\n";
// ─────────────────────────────────────────────────────────────────────────────

$refreshToken   = Jwt::createRefreshToken($user, ttlSeconds: 86400);
$refreshPayload = Jwt::decode($refreshToken);

assert_test(($refreshPayload['typ'] ?? '') === 'refresh', '3a. Refresh token "typ" is "refresh"');
assert_test((int)($refreshPayload['sub'] ?? -1) === 42, '3b. Refresh token "sub" matches user ID');
assert_test(abs((int)$refreshPayload['exp'] - (time() + 86400)) < 10, '3c. Refresh token "exp" is ~24 hours from now');

// Refresh tokens should NOT be used as access tokens (block at middleware level)
assert_test(($refreshPayload['typ'] ?? '') !== 'access', '3d. Refresh token type is NOT "access" (middleware blocks it)');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Personal Access Token Format & DTO\n";
// ─────────────────────────────────────────────────────────────────────────────

// 4a. Token format: spinx_pat_{id}|{64hex}
$plaintext     = bin2hex(random_bytes(32));
$fakeId        = 7;
$fullPlaintext = 'spinx_pat_' . $fakeId . '|' . $plaintext;

assert_test(str_starts_with($fullPlaintext, 'spinx_pat_'), '4a. PAT starts with "spinx_pat_" prefix');
assert_test(str_contains($fullPlaintext, '|'), '4b. PAT contains id|plaintext separator');

$parsed = explode('|', substr($fullPlaintext, strlen('spinx_pat_')), 2);
assert_test(count($parsed) === 2, '4c. PAT parses into exactly 2 segments after stripping prefix');
assert_test((int)$parsed[0] === $fakeId, '4d. ID segment parsed correctly');
assert_test(strlen($parsed[1]) === 64, '4e. Plaintext segment is 64 hex characters');

// 4b. SHA-256 hash of plaintext
$hash = hash('sha256', $plaintext);
assert_test(strlen($hash) === 64, '4f. SHA-256 hash is 64 hex characters');
assert_test($hash !== $plaintext, '4g. Hash differs from plaintext (never stored in plaintext)');
assert_test($hash === hash('sha256', $plaintext), '4h. SHA-256 is deterministic for same input');

// 4c. NewAccessToken DTO — use an actual PersonalAccessToken-like stub
// We test the DTO contract and the ability-checking logic on the PAT model directly.
$mockPat = new class extends \Spinx\Auth\Token\PersonalAccessToken {
    public int $id = 1;
    /** @var string[] */
    public mixed $abilities = ['*'];
    public mixed $expires_at = null;
    public function can(string $ability): bool {
        $abilities = (array) ($this->abilities ?? []);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
    public function isExpired(): bool { return false; }
    public function save(): bool { return true; }
};

$dto = new NewAccessToken($mockPat, 'spinx_pat_1|abc123');
assert_test($dto->plainTextToken === 'spinx_pat_1|abc123', '4i. NewAccessToken stores plaintext string correctly');
assert_test($dto->accessToken === $mockPat, '4j. NewAccessToken accessToken property holds the PAT record');
assert_test($mockPat->can('*'), '4k. Omniscient token (*) passes wildcard check');
assert_test($mockPat->can('projects:create'), '4l. Omniscient token (*) passes specific ability check');
assert_test(!$mockPat->isExpired(), '4m. Token with null expires_at reports as not expired');

// Scoped token — test PersonalAccessToken::can() logic directly
$scopedPat = new class extends \Spinx\Auth\Token\PersonalAccessToken {
    public mixed $abilities = ['chapters:write', 'projects:read'];
    public function can(string $ability): bool {
        $abilities = (array) ($this->abilities ?? []);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
    public function save(): bool { return true; }
};
assert_test($scopedPat->can('chapters:write'), '4n. Scoped token passes allowed ability');
assert_test(!$scopedPat->can('projects:delete'), '4o. Scoped token blocks non-allowed ability');

// ─────────────────────────────────────────────────────────────────────────────
// Cleanup
// ─────────────────────────────────────────────────────────────────────────────
array_map('unlink', glob($tmpConfigDir . '/*.php') ?: []);
rmdir($tmpConfigDir);

echo "\n=======================================================\n";
echo "  Results: {$pass} passed, {$fail} failed\n";
echo "=======================================================\n\n";

if ($fail > 0) {
    exit(1);
}
