# Database & ORM

Built directly on Doctrine DBAL 4 — not the full Doctrine ORM, whose UnitOfWork is not coroutine-safe and would conflict with the Swoole driver. SQLite is the zero-config default (`storage/database.sqlite`); MySQL/PostgreSQL are a `.env` change away (see `config/database.php`):

```ini
DB_DRIVER=pdo_mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=
```

## Schema Cache (`spinx schema:compile`)

To enable zero-runtime-cost column filtering (`selectWithout`), Spinx includes an ahead-of-time schema compiler:

```bash
spinx schema:compile
```

This introspects your database via DBAL 4 and writes an immutable table column mapping to `storage/cache/schema_columns.php`, which `Kernel::boot()` loads directly into OpCache.

## Models & Query Building

```php
final class Order extends Model
{
    protected static string $table = 'orders';
    protected array $fillable = ['customer_id', 'total', 'status'];
    protected array $casts = ['total' => 'float', 'meta' => 'json'];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
}
```

### Column Selection
```php
// Select specific columns:
$orders = Order::query()->selectWith('id', 'total', 'status')->get();

// Select all columns except sensitive or heavy fields (backed by SchemaCache):
$users = User::query()->selectWithout('password', 'remember_token')->get();
```

### Conditional Queries (`when`, `then`, `else`, `otherwise`)
```php
// Boolean condition:
$orders = Order::query()
    ->where('status', 'active')
    ->when($isAdmin)
        ->then(fn($q) => $q->where('include_internal', true))
        ->else(fn($q) => $q->where('is_public', true))
    ->get();

// Column comparison condition against bound attributes:
Order::query()
    ->where('total', '>', 100)
    ->when('total', '>', 600)
        ->then(fn($q) => $q->where('flagged', true))
        ->otherwise(fn($q) => $q->orderBy('total'))
    ->get();
```

### Atomic Upsert & Row Locking
```php
// Platform-aware atomic upsert (PostgreSQL/SQLite ON CONFLICT, MySQL ON DUPLICATE KEY):
User::upsert(
    values: ['id' => 1, 'email' => 'user@example.com', 'login_count' => 1],
    uniqueColumns: ['id'],
    updateColumns: ['login_count']
);

// Transaction with SELECT FOR UPDATE row locking:
Order::atomic($orderId, function (Order $order): void {
    $order->update(['status' => 'processing']);
});
```

## The DB Façade

For raw statements, aggregations, and multi-table transactions:

```php
use Spinx\Database\DB;

// Multi-table transaction:
DB::transaction(function ($conn): void {
    DB::statement('UPDATE accounts SET balance = balance - 100 WHERE id = :id', ['id' => 1]);
    DB::statement('UPDATE accounts SET balance = balance + 100 WHERE id = :id', ['id' => 2]);
});

// Raw queries:
$rows = DB::select('SELECT id, name FROM users WHERE status = :s', ['s' => 'active']);
$user = DB::selectOne('SELECT * FROM users WHERE id = :id', ['id' => $id]);
$count = DB::scalar('SELECT COUNT(*) FROM orders');
```

## Relationships

```php
// One to One
protected function profile(): HasOne { return $this->hasOne(Profile::class); }

// One to Many
protected function orders(): HasMany { return $this->hasMany(Order::class); }

// Belongs To
protected function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

// Many to Many
protected function tags(): BelongsToMany { return $this->belongsToMany(Tag::class); }

// Eager Loading (single batched query):
$orders = Order::query()->with('customer', 'tags')->get();
```
