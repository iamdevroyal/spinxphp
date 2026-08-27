<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Database\QueryBuilder;
use Spinx\Filesystem\Storage;
use Spinx\Http\Middleware\CorsMiddleware;
use Spinx\Kernel\Kernel;
use Spinx\Queue\Driver\DatabaseQueueDriver;
use Spinx\Queue\Job;
use Spinx\Security\Csrf;
use Spinx\Session\FileSession;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$passed = 0;
$failed = 0;

function assertSec(string $name, bool $condition, string $msg = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$msg}\n";
        $failed++;
    }
}

echo "\n========================================================\n";
echo "    Spinx Framework Security Hardening Integration Test\n";
echo "========================================================\n\n";

$projectRoot = dirname(__DIR__, 2);

// ==========================================
// 1. Storage Path Traversal Hardening
// ==========================================
echo "1. Filesystem & Storage Path Traversal Protection:\n";

$localDisk = Storage::disk('local');

// 1a. Normal path succeeds
$writeOk = $localDisk->put('sec_test/safe.txt', 'Safe Content');
$readContent = $localDisk->get('sec_test/safe.txt');
assertSec("1a. Safe path operations succeed", $writeOk && $readContent === 'Safe Content');

// 1b. Directory traversal with ../ is blocked
$traversalBlocked = false;
try {
    $localDisk->get('../../../.env');
} catch (\InvalidArgumentException $e) {
    $traversalBlocked = true;
}
assertSec("1b. Directory traversal (../) blocked in Storage::get()", $traversalBlocked);

// 1c. Directory traversal with ..\ (Windows) is blocked
$winTraversalBlocked = false;
try {
    $localDisk->put('..\\..\\hacked.php', '<?php echo 1;');
} catch (\InvalidArgumentException $e) {
    $winTraversalBlocked = true;
}
assertSec("1c. Windows directory traversal (..\\) blocked in Storage::put()", $winTraversalBlocked);

// Cleanup
$localDisk->delete('sec_test/safe.txt');

// ==========================================
// 2. Queue Cryptographic Tampering & RCE Defense
// ==========================================
echo "\n2. Queue Insecure Deserialization & RCE Defense:\n";

class SecurityTestJob implements Job {
    public function __construct(public string $data = 'test') {}
    public function handle(): void {}
}

$testJob = new SecurityTestJob('payload-123');

$queueDriver = new DatabaseQueueDriver();

// 2a. Un-tampered valid job serialization and HMAC signing
$reflection = new \ReflectionClass($queueDriver);
$serializeMethod = $reflection->getMethod('serializePayload');
$serializeMethod->setAccessible(true);
$unserializeMethod = $reflection->getMethod('unserializePayload');
$unserializeMethod->setAccessible(true);

$validPayload = $serializeMethod->invoke($queueDriver, $testJob);
$decoded = json_decode($validPayload, true);
assertSec("2a. Queue payload includes HMAC signature", isset($decoded['hmac']) && isset($decoded['data']));

// 2b. Valid payload deserializes properly
$unserializedJob = $unserializeMethod->invoke($queueDriver, $validPayload);
assertSec("2b. Legitimate signed payload deserializes successfully", $unserializedJob instanceof Job);

// 2c. Tampered payload is rejected before unserialize
$tamperedPayload = json_encode([
    'data' => base64_encode(serialize(new \stdClass())), // Tampered object
    'hmac' => $decoded['hmac'], // Original HMAC doesn't match new payload
]);
$tamperedResult = $unserializeMethod->invoke($queueDriver, $tamperedPayload);
assertSec("2c. Forged/Tampered queue payload rejected by HMAC check", $tamperedResult === null);

// ==========================================
// 3. CORS Credentialed Wildcard Origin Defense
// ==========================================
echo "\n3. CORS Origin Reflection Defense:\n";

$cors = new CorsMiddleware();

// 3a. Wildcard with credentials: false -> returns '*'
$req1 = Request::create('/api/data', 'GET');
$req1->headers->set('Origin', 'https://attacker.com');

// Simulate config: allowed_origins = ['*'], allow_credentials = false
$corsRef = new \ReflectionClass($cors);
$resolveOrigin = $corsRef->getMethod('resolveAllowOrigin');
$resolveOrigin->setAccessible(true);

$origin1 = $resolveOrigin->invoke($cors, ['*'], 'https://attacker.com', false);
assertSec("3a. Wildcard without credentials returns '*'", $origin1 === '*');

// 3b. Wildcard with credentials: true -> MUST NOT reflect attacker origin
$origin2 = $resolveOrigin->invoke($cors, ['*'], 'https://attacker.com', true);
assertSec("3b. Wildcard with credentials DOES NOT reflect attacker origin", $origin2 === null);

// 3c. Explicit origin with credentials: true -> allows legitimate origin
$origin3 = $resolveOrigin->invoke($cors, ['https://app.mysite.com'], 'https://app.mysite.com', true);
assertSec("3c. Explicit allowed origin permitted with credentials", $origin3 === 'https://app.mysite.com');

// ==========================================
// 4. Request-Isolated CSRF Protection
// ==========================================
echo "\n4. CSRF Persistent Worker Isolation & Verification:\n";

$session = new FileSession(sys_get_temp_dir() . '/spinx_sec_sessions');
$token = Csrf::tokenForSession($session);

assertSec("4a. CSRF generates high-entropy 64-hex-char token", strlen($token) === 64);
assertSec("4b. Csrf::verify matches valid session token", Csrf::verify($token, session: $session));
assertSec("4c. Csrf::verify rejects forged token", !Csrf::verify('forged-token-1234567890', session: $session));

// 4d. CSRF Reset clears static current token
Csrf::reset();
$newToken = Csrf::current();
assertSec("4d. Csrf::reset() properly cycles current token", $newToken !== '');

// ==========================================
// 5. QueryBuilder SQL Direction Sanitization
// ==========================================
echo "\n5. QueryBuilder SQL Injection Sanitization:\n";

$conn = \Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
$qb = new QueryBuilder($conn, 'users');
$qb->orderBy('created_at', 'ASC; DROP TABLE users;');

$qbRef = new \ReflectionClass($qb);
$doctrineQueryProp = $qbRef->getProperty('query');
$doctrineQueryProp->setAccessible(true);
$doctrineQuery = $doctrineQueryProp->getValue($qb);

$sql = $doctrineQuery->getSQL();
assertSec("5a. QueryBuilder sanitizes orderBy direction injection to ASC", !str_contains($sql, 'DROP TABLE') && str_contains($sql, 'ASC'));

echo "\n========================================================\n";
echo "  Results: {$passed} assertions passed, {$failed} failed\n";
echo "========================================================\n\n";

if ($failed > 0) {
    exit(1);
}
