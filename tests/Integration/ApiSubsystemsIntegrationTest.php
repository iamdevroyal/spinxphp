<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Http\Resources\JsonResource;
use Spinx\Http\Resources\ResourceCollection;
use Spinx\Http\Resources\MissingValue;
use Spinx\Http\Exceptions\ProblemDetails;
use Spinx\Http\Exceptions\NotFoundHttpException;
use Spinx\Http\Exceptions\ForbiddenHttpException;
use Spinx\Http\Exceptions\BadRequestHttpException;
use Spinx\Database\Pagination\Cursor;
use Spinx\Database\Pagination\CursorPaginator;
use Spinx\Http\Middleware\HttpCacheMiddleware;
use Spinx\Http\Middleware\IdempotencyMiddleware;
use Spinx\OpenApi\ApiDocsController;
use Spinx\Support\Config;

echo "\n=======================================================\n";
echo "    Spinx API Subsystems Integration Test (v1.0.24)\n";
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
echo "1. JsonResource Transformation & Conditional Attributes\n";
// ─────────────────────────────────────────────────────────────────────────────

$rawUser = (object) [
    'id'         => 101,
    'name'       => 'Victor Hugo',
    'email'      => 'victor@lesmis.fr',
    'role'       => 'admin',
    'secret_pin' => '9876',
    'bio'        => null,
    'posts'      => [(object) ['id' => 1, 'title' => 'Les Misérables']],
];

class TestUserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'secret_pin' => $this->when($this->role === 'admin', $this->secret_pin),
            'hidden_key' => $this->when(false, 'should_never_appear'),
            'bio'        => $this->whenNotNull($this->bio),
            'posts'      => $this->whenLoaded('posts'),
            'comments'   => $this->whenLoaded('comments'), // not loaded
        ];
    }
}

// 1a. Test single resource wrapping
JsonResource::wrap('data');
$resource = TestUserResource::make($rawUser);
$json     = $resource->jsonSerialize();

assert_test(isset($json['data']), '1a. JsonResource wraps payload under "data" key');
assert_test($json['data']['id'] === 101, '1b. Resource extracts entity property ->id');
assert_test($json['data']['secret_pin'] === '9876', '1c. $this->when(true) includes the conditional attribute');
assert_test(!array_key_exists('hidden_key', $json['data']), '1d. $this->when(false) strips the attribute completely');
assert_test(!array_key_exists('bio', $json['data']), '1e. $this->whenNotNull(null) strips null attribute');
assert_test(isset($json['data']['posts']), '1f. $this->whenLoaded("posts") includes loaded relation');
assert_test(!array_key_exists('comments', $json['data']), '1g. $this->whenLoaded("comments") strips unloaded relation');

// 1h. Test unwrapped
JsonResource::withoutWrapping();
$unwrapped = $resource->jsonSerialize();
assert_test(!isset($unwrapped['data']) && ($unwrapped['id'] ?? null) === 101, '1h. JsonResource::withoutWrapping() outputs unwrapped root array');

// Reset wrapping
JsonResource::wrap('data');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n2. ResourceCollection & Additional Envelopes\n";
// ─────────────────────────────────────────────────────────────────────────────

$users = [
    (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@test.com', 'role' => 'user', 'secret_pin' => '1', 'bio' => 'Hi', 'posts' => []],
    (object) ['id' => 2, 'name' => 'Bob',   'email' => 'bob@test.com',   'role' => 'admin','secret_pin' => '2', 'bio' => null, 'posts' => []],
];

$collection = TestUserResource::collection($users);
$collection->additional(['meta' => ['api_version' => 'v1']]);
$serializedColl = $collection->jsonSerialize();

assert_test(count($collection) === 2, '2a. ResourceCollection is Countable (count = 2)');
assert_test(count($serializedColl['data']) === 2, '2b. Collection serializes list under "data"');
assert_test($serializedColl['meta']['api_version'] === 'v1', '2c. ->additional() merges custom metadata envelope');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n3. RFC 7807 Standardized ProblemDetails & HttpExceptions\n";
// ─────────────────────────────────────────────────────────────────────────────

// 3a. Fluent factories
$problem404 = ProblemDetails::notFound('Project with id 404 was deleted.', 'PROJECT_NOT_FOUND');
$arr404     = $problem404->toArray();

assert_test($arr404['status'] === 404, '3a. ProblemDetails::notFound has status 404');
assert_test($arr404['title'] === 'Not Found', '3b. ProblemDetails title is "Not Found"');
assert_test($arr404['code'] === 'PROJECT_NOT_FOUND', '3c. Custom error code is mapped');
assert_test($arr404['type'] === 'about:blank', '3d. Default RFC 7807 type is "about:blank"');

// 3b. Validation problem details
$problem422 = ProblemDetails::validation(['email' => ['The email field is required.']]);
$arr422     = $problem422->toArray();
assert_test($arr422['status'] === 422, '3e. ProblemDetails::validation has status 422');
assert_test(isset($arr422['errors']['email']), '3f. Validation field errors present in problem payload');

// 3c. Typed HttpException conversion
$httpEx = new NotFoundHttpException('Book not found.', 'BOOK_NOT_FOUND');
assert_test($httpEx->getStatusCode() === 404, '3g. NotFoundHttpException getStatusCode() is 404');
$convProblem = $httpEx->toProblemDetails();
assert_test($convProblem->status === 404 && $convProblem->code === 'BOOK_NOT_FOUND', '3h. HttpException converts to ProblemDetails accurately');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n4. Cursor-Based Pagination (Cursor & CursorPaginator)\n";
// ─────────────────────────────────────────────────────────────────────────────

$cursor = new Cursor(value: 50, column: 'id', direction: 'asc');
$token  = $cursor->encode();

assert_test(is_string($token) && strlen($token) > 0, '4a. Cursor::encode() creates non-empty token string');

$decoded = Cursor::decode($token);
assert_test($decoded !== null, '4b. Cursor::decode() parses token string');
assert_test($decoded->value === 50, '4c. Decoded cursor value is 50');
assert_test($decoded->column === 'id', '4d. Decoded cursor column is "id"');
assert_test($decoded->direction === 'asc', '4e. Decoded cursor direction is "asc"');

// Test invalid cursor string gracefully returns null
assert_test(Cursor::decode('invalid!!!base64') === null, '4f. Invalid cursor string gracefully returns null');

// CursorPaginator
$items     = [(object)['id' => 51, 'title' => 'Chap 1'], (object)['id' => 52, 'title' => 'Chap 2']];
$paginator = new CursorPaginator(items: $items, perPage: 2, cursorCol: 'id', direction: 'asc', hasMore: true);
$pagArray  = $paginator->toArray();

assert_test($pagArray['pagination']['per_page'] === 2, '4g. CursorPaginator per_page is 2');
assert_test($pagArray['pagination']['has_more'] === true, '4h. CursorPaginator has_more is true');
assert_test($pagArray['pagination']['next_cursor'] !== null, '4i. CursorPaginator generates next_cursor token');

// Decode the generated next cursor
$nextDecoded = Cursor::decode($pagArray['pagination']['next_cursor']);
assert_test($nextDecoded->value === 52, '4j. next_cursor points to last item ID (52)');

// ─────────────────────────────────────────────────────────────────────────────
echo "\n5. HTTP 304 ETag & Caching Middleware\n";
// ─────────────────────────────────────────────────────────────────────────────

$cacheMiddleware = new HttpCacheMiddleware();
$_SERVER['REQUEST_METHOD'] = 'GET';

$response = \Spinx\Http\Response::json(['message' => 'Hello World']);
$processed = $cacheMiddleware->handle(null, fn() => $response, 'max_age=3600,etag');

assert_test($processed->headers->has('Cache-Control'), '5a. Cache-Control header added');
assert_test($processed->headers->has('ETag'), '5b. ETag header added');

$computedEtag = $processed->headers->get('ETag');

// Simulate client sending If-None-Match with matching ETag
$_SERVER['HTTP_IF_NONE_MATCH'] = $computedEtag;
$response2 = \Spinx\Http\Response::json(['message' => 'Hello World']);
$processed304 = $cacheMiddleware->handle(null, fn() => $response2, 'max_age=3600,etag');

assert_test($processed304->getStatusCode() === 304, '5c. Matching If-None-Match produces 304 Not Modified status');
assert_test($processed304->getContent() === '', '5d. 304 response has empty 0-byte body');

// Clean up globals
unset($_SERVER['HTTP_IF_NONE_MATCH']);

// ─────────────────────────────────────────────────────────────────────────────
echo "\n6. Interactive Scalar API Docs Controller\n";
// ─────────────────────────────────────────────────────────────────────────────

$docsController = new ApiDocsController();
$docsResponse   = $docsController->docs();

assert_test($docsResponse->getStatusCode() === 200, '6a. ApiDocsController::docs() returns 200 OK');
assert_test(str_contains($docsResponse->getContent(), '@scalar/api-reference'), '6b. Renders Scalar CDN bundle tag');
assert_test(str_contains($docsResponse->getContent(), '/openapi.json'), '6c. References /openapi.json spec endpoint');

echo "\n=======================================================\n";
echo "  Results: {$pass} passed, {$fail} failed\n";
echo "=======================================================\n\n";

if ($fail > 0) {
    exit(1);
}
