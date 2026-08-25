# External Services & APIs

The pattern for pulling in any third-party API — payment providers,
email APIs, anything over HTTP — is the same three pieces every time:

1. Credentials in `.env`
2. A `config/services.php` entry reading them (with `env()`)
3. `Spinx\Http\HttpClient` (a thin wrapper over Symfony HttpClient) to
   actually make the calls

## Complete example: Paystack

**.env**
```
PAYSTACK_SECRET_KEY=sk_test_xxx
```

**config/services.php** (already present by default):
```php
'paystack' => [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
],
```

**An Application service using it:**
```php
namespace App\Modules\Payments\Application\Services;

use Spinx\Http\HttpClient;

final class PaystackService
{
    private HttpClient $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client
            ->baseUrl(config('services.paystack.base_url'))
            ->withToken(config('services.paystack.secret_key'));
    }

    public function verifyTransaction(string $reference): array
    {
        $response = $this->client->get("/transaction/verify/{$reference}")->throw();

        return $response->json();
    }

    public function initializeTransaction(string $email, int amountKobo): string
    {
        $response = $this->client
            ->post('/transaction/initialize', ['email' => $email, 'amount' => $amountKobo])
            ->throw();

        return $response->get('data.authorization_url');
    }
}
```

Register it in your module's `module.php` `services` closure with
`->setAutowired(true)` (no `->setPublic(true)` needed unless a
controller resolves it directly by class-string) — `HttpClient`
autowires in automatically since it's already registered in
`config/container.php`.

## `HttpClient` reference

```php
$client->get($path, $query = []);
$client->post($path, $data = []);   // sent as JSON
$client->put($path, $data = []);
$client->patch($path, $data = []);
$client->delete($path);

$client->baseUrl($url);      // returns a NEW instance — doesn't mutate the original
$client->withHeaders($arr);  // same — safe to reuse a base client for multiple configured calls
$client->withToken($token, $type = 'Bearer');
```

`HttpResponse` (what every call returns):
```php
$response->status();        // int
$response->successful();    // 200-299
$response->failed();        // !successful()
$response->json();          // decoded array
$response->get('data.id');  // dot-notation into the JSON body
$response->body();          // raw string
$response->throw();         // throws RuntimeException if failed(), otherwise returns $this — chainable
```

## Adding a new service

Add an entry to `config/services.php`, add the credential to `.env` and
`.env.example`, and start calling it with `HttpClient` the same way. No
framework registration step beyond that — this is intentionally the
entire pattern, not a partial example needing more machinery bolted on.
