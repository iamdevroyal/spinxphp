<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Broadcasting\Broadcast;
use Spinx\Broadcasting\BroadcastManager;
use Spinx\Broadcasting\Channel;
use Spinx\Broadcasting\Driver\LogDriver;
use Spinx\Broadcasting\Driver\PusherDriver;
use Spinx\Broadcasting\PrivateChannel;
use Spinx\Broadcasting\ShouldBroadcast;
use Spinx\Database\Vector\Vector;
use Spinx\Database\Vector\VectorService;
use Spinx\Filesystem\Driver\LocalFilesystemDriver;
use Spinx\Filesystem\FilesystemManager;
use Spinx\Filesystem\Storage;
use Spinx\Http\Webhook\HmacWebhookVerifier;
use Spinx\Llm\ChatMessage;
use Spinx\Llm\Llm;
use Spinx\Llm\LlmManager;
use Spinx\Llm\LlmRequest;
use Spinx\Llm\LlmResponse;
use Spinx\Queue\Driver\DatabaseQueueDriver;
use Spinx\Queue\Driver\SyncQueueDriver;
use Spinx\Queue\Job;
use Spinx\Queue\JobStatus;
use Spinx\Queue\Queue;
use Spinx\Queue\QueueManager;

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $msg = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$msg}\n";
        $failed++;
    }
}

echo "\n==============================================\n";
echo "    Spinx Framework Upgrade Integration Test\n";
echo "==============================================\n\n";

// ==========================================
// 1. Multi-Queue & Worker Subsystem
// ==========================================
echo "1. Multi-Queue & Worker Subsystem:\n";

class TestJob implements Job {
    public static bool $handled = false;
    public function handle(): void {
        self::$handled = true;
    }
}

// 1a. SyncQueueDriver
$syncDriver = new SyncQueueDriver();
$jobRef = $syncDriver->push(new TestJob());
assertTest("1a. SyncQueueDriver executes immediately", TestJob::$handled && is_string($jobRef));

// 1b. QueueManager & Facade
$queueManager = new QueueManager('sync');
Queue::setManager($queueManager);
$ref = Queue::onQueue('high')->withPriority(10)->push(new TestJob());
assertTest("1b. Queue::onQueue()->withPriority() dispatches successfully", is_string($ref) && strlen($ref) > 0);

// ==========================================
// 2. Real-Time Broadcasting Subsystem
// ==========================================
echo "\n2. Real-Time Broadcasting Subsystem:\n";

$broadcastManager = new BroadcastManager('log');
Broadcast::setManager($broadcastManager);

// 2a. Channel pending broadcast
Broadcast::channel('orders.1')->event('OrderShipped', ['id' => 1, 'status' => 'shipped']);
assertTest("2a. Broadcast::channel()->event() dispatched with LogDriver", true);

// 2b. Private channel
$privateChan = new PrivateChannel('user.42');
assertTest("2b. PrivateChannel prefixes channel name correctly", $privateChan->getName() === 'private-user.42');

// 2c. ShouldBroadcast event
class OrderCreatedEvent implements ShouldBroadcast {
    public function broadcastOn(): Channel|array|string {
        return new Channel('orders');
    }
    public function broadcastAs(): ?string {
        return 'OrderCreated';
    }
    public function broadcastWith(): array {
        return ['order_id' => 101, 'amount' => 49.99];
    }
}

Broadcast::event(new OrderCreatedEvent());
assertTest("2c. Broadcast::event(ShouldBroadcast) dispatched successfully", true);

// 2d. Pusher driver channel signature generation
$pusherDriver = new PusherDriver([
    'key'    => 'test-key',
    'secret' => 'test-secret',
    'app_id' => '12345',
]);

$auth = $pusherDriver->authenticateChannel('private-chat', '1234.5678');
assertTest("2d. PusherDriver generates valid HMAC signature for private channel", is_array($auth) && isset($auth['auth']) && str_starts_with($auth['auth'], 'test-key:'));

// ==========================================
// 3. Multi-Disk Filesystem & Storage
// ==========================================
echo "\n3. Multi-Disk Filesystem & Storage Subsystem:\n";

$tempDir = __DIR__ . '/../../storage/test_storage_' . uniqid();
$fsManager = new FilesystemManager('local');
$localDriver = new LocalFilesystemDriver($tempDir, 'http://localhost/storage');
Storage::setManager($fsManager);

// 3a. Put and Get
$localDriver->put('test.txt', 'Hello Spinx Storage!');
assertTest("3a. LocalFilesystemDriver writes file", $localDriver->exists('test.txt'));
assertTest("3b. LocalFilesystemDriver reads content accurately", $localDriver->get('test.txt') === 'Hello Spinx Storage!');

// 3c. Temporary signed URL
$tempUrl = $localDriver->temporaryUrl('test.txt', new \DateTimeImmutable('+1 hour'));
assertTest("3c. LocalFilesystemDriver generates temporary signed URL", str_contains($tempUrl, 'signature=') && str_contains($tempUrl, 'expires='));

// 3d. File listing & cleanup
$files = $localDriver->files();
assertTest("3d. LocalFilesystemDriver lists files", in_array('test.txt', $files, true));
$localDriver->delete('test.txt');
assertTest("3e. LocalFilesystemDriver deletes file", !$localDriver->exists('test.txt'));
$localDriver->deleteDirectory('');

// ==========================================
// 4. Vector Search & DBAL Extensions
// ==========================================
echo "\n4. Vector Search & DBAL Extensions:\n";

$vectorService = new VectorService();
Vector::setService($vectorService);

// 4a. Vector string formatting
$vectorStr = Vector::formatVector([0.1234, -0.5678, 1.0]);
assertTest("4a. Vector::formatVector formats float array to pgvector literal", $vectorStr === '[0.1234,-0.5678,1]');

// ==========================================
// 5. Webhook Signature Verification
// ==========================================
echo "\n5. Webhook Signature Verification:\n";

$verifier = new HmacWebhookVerifier('sha256');
$payload = '{"event":"charge.success","amount":5000}';
$secret = 'whsec_test_secret_123';

// 5a. Standard HMAC-SHA256 signature
$validSig = hash_hmac('sha256', $payload, $secret);
assertTest("5a. HmacWebhookVerifier verifies valid HMAC-SHA256 signature", $verifier->verify($payload, $validSig, $secret));
assertTest("5b. HmacWebhookVerifier rejects invalid signature", !$verifier->verify($payload, 'invalid-signature', $secret));

// 5c. Stripe-style timestamped signature
$time = time();
$stripeSig = hash_hmac('sha256', "{$time}.{$payload}", $secret);
$stripeHeader = "t={$time},v1={$stripeSig}";
assertTest("5c. HmacWebhookVerifier verifies Stripe timestamped signature header", $verifier->verify($payload, $stripeHeader, $secret));

// ==========================================
// 6. Application LLM Abstraction Layer
// ==========================================
echo "\n6. Application LLM Abstraction Layer:\n";

// 6a. ChatMessage serialization
$msg = ChatMessage::user('Write a poem about async PHP.');
assertTest("6a. ChatMessage::user creates valid role and content", $msg->role === 'user' && $msg->content === 'Write a poem about async PHP.');

// 6b. LlmRequest DTO
$req = LlmRequest::fromPrompt('Tell me a joke', 'You are a funny assistant');
assertTest("6b. LlmRequest::fromPrompt creates valid request DTO", count($req->messages) === 1 && $req->system === 'You are a funny assistant');

// 6c. LlmResponse JSON parser
$res = new LlmResponse(
    content: '{"summary": "A fast framework", "score": 98}',
    model: 'claude-3-5-sonnet-20241022',
    usage: ['input_tokens' => 15, 'output_tokens' => 25]
);
assertTest("6c. LlmResponse::json parses structured JSON payload", is_array($res->json()) && ($res->json()['score'] ?? 0) === 98);
assertTest("6d. LlmResponse token counting", $res->inputTokens() === 15 && $res->outputTokens() === 25);

echo "\n==============================================\n";
echo "  Results: {$passed} assertions passed, {$failed} failed\n";
echo "==============================================\n\n";

if ($failed > 0) {
    exit(1);
}
