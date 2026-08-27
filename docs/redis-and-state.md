# Redis Connection Pooling & Multi-Worker State

Spinx includes a centralized Redis manager designed specifically for persistent-worker architectures (RoadRunner & Swoole).

---

## ⚡ Key Highlights
- **Isolated Database Indexing:** Automatically partitions Redis databases (`default:0`, `cache:1`, `session:2`, `queue:3`) to avoid key collisions across subsystems.
- **Stateless Multi-Server Sessions (`RedisSession`):** Synchronizes user sessions across horizontal server pools with native TTL key expiration.
- **Atomic Multi-Worker Rate Limiting (`RedisRateLimitStore`):** Synchronizes request counters across worker pools using atomic `INCR` + `EXPIRE`.

---

## 🚀 Quick Usage

### 1. Dedicated Connection Pools
```php
use Spinx\Redis\Redis;

// Default connection (DB 0)
Redis::set('system:status', 'online');
$status = Redis::get('system:status');

// Cache connection (DB 1)
Redis::connection('cache')->setex('homepage:featured', 3600, json_encode($data));

// Session connection (DB 2)
Redis::connection('session')->get('session_id_xyz');
```

---

## ⚙️ Configuration (`config/redis.php`)

```php
return [
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DB', 0),
        'timeout'  => 2.0,
    ],

    'connections' => [
        'cache' => [
            'host'     => env('REDIS_CACHE_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port'     => (int) env('REDIS_CACHE_PORT', env('REDIS_PORT', 6379)),
            'password' => env('REDIS_CACHE_PASSWORD', env('REDIS_PASSWORD', null)),
            'database' => (int) env('REDIS_CACHE_DB', 1),
        ],

        'session' => [
            'host'     => env('REDIS_SESSION_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port'     => (int) env('REDIS_SESSION_PORT', env('REDIS_PORT', 6379)),
            'password' => env('REDIS_SESSION_PASSWORD', env('REDIS_PASSWORD', null)),
            'database' => (int) env('REDIS_SESSION_DB', 2),
        ],

        'queue' => [
            'host'     => env('REDIS_QUEUE_HOST', env('REDIS_HOST', '127.0.0.1')),
            'port'     => (int) env('REDIS_QUEUE_PORT', env('REDIS_PORT', 6379)),
            'password' => env('REDIS_QUEUE_PASSWORD', env('REDIS_PASSWORD', null)),
            'database' => (int) env('REDIS_QUEUE_DB', 3),
        ],
    ],
];
```
