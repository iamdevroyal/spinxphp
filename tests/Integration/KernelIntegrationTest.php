<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

spl_autoload_register(function ($class) use ($projectRoot) {
    if (str_starts_with($class, 'Spinx\\Tests\\Support\\')) {
        $file = $projectRoot . '/tests/Support/' . str_replace('\\', '/', substr($class, 20)) . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
    if (str_starts_with($class, 'Spinx\\')) {
        $file = $projectRoot . '/src/Spinx/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
    if (str_starts_with($class, 'App\\')) {
        $file = $projectRoot . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

// Require test stubs
require_once $projectRoot . '/tests/Support/DiStubs.php';
require_once $projectRoot . '/tests/Support/HttpFoundationStubs.php';
require_once $projectRoot . '/tests/Support/RoutingStubs.php';

use Spinx\Auth\{Auth, Hash, EloquentUserProvider, UserProviderInterface};
use Spinx\Http\Middleware\Pipeline;
use Spinx\Routing\{AliasRegistry, Route, RouteBuilder, RouteDefinition};
use Spinx\Schedule\{Scheduler, CronExpression};
use Spinx\Session\FileSession;
use Spinx\Support\Config;
use Spinx\Validation\Validator;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

echo "\n==============================================\n";
echo "    Spinx Kernel End-to-End Integration Test\n";
echo "==============================================\n\n";

// -------------------------------------------------------------
// 1. Fluent Routing DSL & Alias Compilation
// -------------------------------------------------------------
echo "1. Routing & Alias Registry:\n";

$registry = new AliasRegistry();

// Test controller
class TestApiController {
    public function __invoke(Request $req, string $id): Response {
        return new JsonResponse(['id' => $id, 'source' => 'TestApiController']);
    }
}

// Test middleware
class TestHeaderMiddleware implements \Spinx\Http\Middleware\MiddlewareInterface {
    public function process(Request $req, \Closure $next): Response {
        $res = $next($req);
        $res->headers->set('X-Test-Middleware', 'Executed');
        return $res;
    }
}

$registry->registerController('test_api', TestApiController::class);
$registry->registerMiddleware('test_header', TestHeaderMiddleware::class);

assertTest($registry->hasController('test_api'), '1a. Registry has controller alias');
assertTest($registry->hasMiddleware('test_header'), '1b. Registry has middleware alias');

$routes = new RouteCollection();
$builder = new RouteBuilder('', $registry);
Route::setActiveBuilder($builder);

Route::get(['api.show', '/api/items/{id}'])
    ->middleware(['test_header'])
    ->controller('test_api');

Route::clearActiveBuilder();
$builder->compileInto($routes);

assertTest($routes->get('api.show') !== null, '1c. Route compiled into RouteCollection');
$compiledRoute = $routes->get('api.show');
assertTest($compiledRoute->getDefault('_controller') === TestApiController::class, '1d. Controller alias resolved to FQCN');
assertTest($compiledRoute->getDefault('_middleware') === [TestHeaderMiddleware::class], '1e. Middleware alias resolved to FQCN');

// -------------------------------------------------------------
// 2. Request Dispatching & Middleware Pipeline
// -------------------------------------------------------------
echo "\n2. Request Dispatching & Pipeline:\n";

$context = new RequestContext();
$request = Request::create('/api/items/99', 'GET');
$context->fromRequest($request);
$matcher = new UrlMatcher($routes, $context);

$parameters = $matcher->match($request->getPathInfo());
$controllerClass = $parameters['_controller'];
$middlewareClasses = $parameters['_middleware'];
unset($parameters['_controller'], $parameters['_route'], $parameters['_middleware']);

$controller = new $controllerClass();
$finalHandler = fn (Request $r): Response => $controller($r, ...array_values($parameters));

$container = new \Spinx\Tests\Support\TestContainer();
$pipeline = new Pipeline($container);
$response = $pipeline->handle($request, $middlewareClasses, $finalHandler);

assertTest($response instanceof JsonResponse, '2a. Controller executed and returned JsonResponse');
assertTest($response->headers->get('X-Test-Middleware') === 'Executed', '2b. Middleware executed in pipeline and added header');
$data = json_decode((string) $response->getContent(), true);
assertTest(($data['id'] ?? null) === '99', '2c. URL parameter {id} passed correctly to controller');

// -------------------------------------------------------------
// 3. Validation Subsystem
// -------------------------------------------------------------
echo "\n3. Validator Engine:\n";

$input = [
    'title' => '  Spinx Framework  ',
    'email' => 'developer@spinx.dev',
    'count' => '42',
    'status' => 'active',
    'password' => 'secret123',
    'password_confirmation' => 'secret123',
    'extra_junk_field' => 'should be removed',
];

$rules = [
    'title' => 'required|string|min:3|max:50',
    'email' => 'required|email',
    'count' => 'required|integer',
    'status' => 'required|in:active,inactive',
    'password' => 'required|min:8|confirmed',
    'note' => 'nullable|string',
];

$validated = Validator::make($input, $rules)->validate();

assertTest($validated['title'] === '  Spinx Framework  ', '3a. Title string validated');
assertTest($validated['email'] === 'developer@spinx.dev', '3b. Email validated');
assertTest($validated['count'] === '42', '3c. Integer string accepted');
assertTest(!array_key_exists('extra_junk_field', $validated), '3d. Allowlist stripped undeclared fields');

// -------------------------------------------------------------
// 4. Task Scheduler
// -------------------------------------------------------------
echo "\n4. Scheduler & Cron Engine:\n";

$scheduler = new Scheduler();
$executed = false;

$scheduler->call(function () use (&$executed) {
    $executed = true;
}, 'heartbeat task')->everyMinute();

$due = $scheduler->dueTasks(new \DateTimeImmutable());
assertTest(count($due) === 1, '4a. everyMinute task is due');
($due[0]->callback)();
assertTest($executed === true, '4b. Scheduled task callback executed');

// -------------------------------------------------------------
// 5. Auth & Session Subsystem
// -------------------------------------------------------------
echo "\n5. Authentication & Session:\n";

$tmpDir = sys_get_temp_dir() . '/spinx_int_test_' . uniqid();
$session = new FileSession($tmpDir);
$session->hydrate('session_token_1', []);

class UserEntity {
    public int $id = 7;
    public string $email = 'user@spinx.dev';
    public string $password = '';
}
$user = new UserEntity();
$user->password = Hash::make('mypassword', cost: 10);

$provider = new class($user) implements UserProviderInterface {
    public function __construct(private UserEntity $u) {}
    public function findById(int|string $id): ?object { return $id == $this->u->id ? $this->u : null; }
    public function findByCredentials(array $creds): ?object { return ($creds['email'] ?? null) === $this->u->email ? $this->u : null; }
    public function validateCredentials(object $user, string $pw): bool { return Hash::check($pw, $user->password); }
};

Auth::boot($provider, $session);

assertTest(Auth::guest(), '5a. Initial state is guest');
$logged = Auth::attempt(['email' => 'user@spinx.dev', 'password' => 'mypassword']);
assertTest($logged === true, '5b. Auth::attempt succeeded');
assertTest(Auth::check() === true, '5c. Auth::check is true');
assertTest(Auth::id() === 7, '5d. Auth::id returns 7');
assertTest(Auth::user()->email === 'user@spinx.dev', '5e. Auth::user returns correct entity');
Auth::logout();
assertTest(Auth::guest() === true, '5f. Auth::guest is true after logout');

echo "\n==============================================\n";
echo "  Results: {$passed} assertions passed, {$failed} failed\n";
echo "==============================================\n\n";

exit($failed > 0 ? 1 : 0);
