# Caching Subsystem

Spinx includes a dedicated, high-throughput caching subsystem designed for persistent runtime workers (RoadRunner & Swoole) and traditional environments alike.

---

## 1. Configuration

Cache configuration lives in `config/cache.php`:

```php
return [
    'default' => env('CACHE_DRIVER', 'file'),

    'stores' => [
        'file' => [
            'driver' => 'file',
            'path'   => storage_path('cache/data'),
        ],

        'array' => [
            'driver' => 'array',
        ],

        'redis' => [
            'driver'   => 'redis',
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'port'     => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'spinx_cache:'),
];
```

---

## 2. Using the `Cache` Facade

The `Spinx\Cache\Cache` facade provides static access to all cache operations:

```php
use Spinx\Cache\Cache;

// Store an item for 1 hour (3600 seconds)
Cache::put('dashboard:metrics', $metrics, 3600);

// Store indefinitely (0 or null)
Cache::put('app:settings', $settings);

// Retrieve an item with default fallback
$metrics = Cache::get('dashboard:metrics', []);

// Check existence
if (Cache::has('dashboard:metrics')) {
    // ...
}

// Remember: fetch from cache or compute and store
$user = Cache::remember('user:42', 600, function () {
    return User::find(42);
});

// Increment & Decrement
Cache::increment('page:views', 1);
Cache::decrement('tickets:available', 1);

// Remove specific key
Cache::forget('dashboard:metrics');

// Flush entire cache
Cache::flush();
```

---

## 3. Global `cache()` Helper

The `cache()` helper provides shorthand syntax:

```php
// Retrieve a value
$value = cache('user:1');

// Retrieve with default
$value = cache('user:1', 'default_value');

// Store multiple values with TTL (3600s)
cache(['key1' => 'val1', 'key2' => 'val2'], 3600);

// Access CacheManager instance
$manager = cache();
```

---

## 4. Switching Stores at Runtime

Access specific configured stores via `Cache::store()`:

```php
// Use Redis store explicitly
$redisStats = Cache::store('redis')->get('live_stats');

// Use Array store for in-memory temporary cache
Cache::store('array')->put('temp_key', $data, 60);
```

---

## 5. CLI Cache Management Commands

Spinx CLI includes commands for cache maintenance:

```bash
# Clear application data cache (storage/cache/data/)
php spinx cache:clear

# Remove a specific key from cache
php spinx cache:forget dashboard:metrics

# Clear compiled Blade template views
php spinx view:clear

# Clear compiled DI container
php spinx container:clear

# Clear compiled DBAL schema cache
php spinx schema:clear

# Clear ALL caches simultaneously
php spinx optimize:clear

# Optimize for production (pre-compiles container, schema columns, and warms cache)
php spinx optimize
```
