# tests/Support/FakeDbal.php

An in-memory SQL-ish engine implementing just enough of Doctrine DBAL's
`Connection`/`Query\QueryBuilder`/result surface for Spinx's real,
unmodified `src/Spinx/Database/*.php` code to run against — built during
the v2 hardening pass specifically to verify eager-loading actually
batches into a single `WHERE IN` query (a query-count assertion, not
just "the results look right"), and to exercise every Eloquent-style
convenience method against real code rather than a parallel fake
implementation of Spinx's own logic.

## Usage

```php
require 'tests/Support/FakeDbal.php'; // defines Doctrine\DBAL\Connection and Query\QueryBuilder
require 'src/Spinx/Database/Model.php'; // and the rest of src/Spinx/Database/*.php

use Doctrine\DBAL\FakeConnection;

$conn = new FakeConnection();
$conn->seed('orders', [
    ['id' => 1, 'customer_id' => 1, 'total' => 100],
]);

// Wire it up via a test ConnectionManager implementing the real interface:
final class TestConnManager implements \Spinx\Database\Connection\ConnectionManager {
    public function __construct(private FakeConnection $conn) {}
    public function get(): \Doctrine\DBAL\Connection { return $this->conn; }
    public function release(\Doctrine\DBAL\Connection $c): void {}
}

\Spinx\Database\Model::setConnectionManager(new TestConnManager($conn));

// Now real Model/QueryBuilder/Relations classes work against $conn's
// in-memory tables, including $conn->queryLog for asserting on query
// count (e.g. verifying with() issues exactly one query, not N).
```

## What it supports

`where`/`orWhere` (`=`, `!=`, `>`, `<`, `>=`, `<=`), `whereIn`,
`whereNull`/`whereNotNull`, `whereBetween`/`whereNotBetween`, `orderBy`,
`limit`/`offset`, `COUNT(*)` aggregation, `insert`/`update`/`delete`
(including `increment`/`decrement`'s `column = column +/- :amount`
expression form), and `lastInsertId()` tracking the most recently
inserted-into table specifically (an early version of this harness
picked the wrong table here — a real bug in the harness, caught while
testing the queue system, fixed by tracking the insert target
explicitly rather than inferring it from array key order).

## Known limitations

No JOIN support (matches Spinx's own `QueryBuilder`, which doesn't have
it either), no `GROUP BY`/`HAVING` evaluation (accepted syntactically,
not actually applied to results), no transaction semantics. Extend the
`evalExpr()`/`matchesRow()` methods in `FakeQueryBuilder` if a test needs
a WHERE pattern not already listed above.
