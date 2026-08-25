<?php
// A minimal in-memory SQL-ish engine implementing just enough of Doctrine
// DBAL's Connection/QueryBuilder/Result surface for Spinx's REAL,
// unmodified src/Spinx/Database/*.php code to run against — not a
// parallel fake implementation of Spinx's own code.
//
// Built during the v2 hardening pass to actually verify eager-loading
// batching (a query-count assertion, not just result correctness) and
// every Eloquent-style convenience method added in that pass, in an
// environment with no Packagist access to install real doctrine/dbal.
// See tests/Support/README.md for usage and known limitations.
//
// Require this file first, before any Spinx\Database class — it defines
// Doctrine\DBAL\Connection and Doctrine\DBAL\Query\QueryBuilder as real,
// loadable classes under those exact namespaces, which Spinx's own code
// type-hints against.

namespace Doctrine\DBAL {

if (!class_exists(Connection::class, false)) {
    class Connection {}
}

final class FakeResult {
    public function __construct(private array $rows) {}
    public function fetchAllAssociative(): array { return $this->rows; }
    public function fetchOne(): mixed { $k = array_key_first($this->rows[0] ?? [0 => null]); return $this->rows[0][$k] ?? null; }
    public function fetchFirstColumn(): array { return array_map(fn($r) => array_values($r)[0], $this->rows); }
}

final class FakeConnection extends Connection {
    public array $queryLog = [];
    private array $tables = [];
    private array $autoIncrement = [];
    private ?string $lastInsertTable = null;

    public function __construct() {}

    public function seed(string $table, array $rows): void {
        $this->tables[$table] = [];
        foreach ($rows as $row) { $this->tables[$table][] = $row; }
        $this->autoIncrement[$table] = count($rows) + 1;
    }

    public function tableRows(string $table): array { return $this->tables[$table] ?? []; }
    public function deleteRow(string $table, int $index): void { unset($this->tables[$table][$index]); }
    public function updateRow(string $table, int $index, string $col, mixed $value): void { $this->tables[$table][$index][$col] = $value; }
    public function logQuery(string $table, array $params): void { $this->queryLog[] = ['table' => $table, 'params' => $params]; }

    public function createQueryBuilder(): \Doctrine\DBAL\Query\QueryBuilder { return new \Doctrine\DBAL\Query\QueryBuilder($this); }

    public function insert(string $table, array $data): int {
        $id = $this->autoIncrement[$table] ?? 1;
        $data['id'] ??= $id;
        $this->tables[$table][] = $data;
        $this->autoIncrement[$table] = $id + 1;
        $this->lastInsertTable = $table;
        return 1;
    }

    public function lastInsertId(): int|string {
        $rows = $this->tables[$this->lastInsertTable] ?? [];
        return end($rows)['id'];
    }

    public function isConnected(): bool { return true; }
    public function getDatabasePlatform(): mixed { return null; }
}

}

namespace Doctrine\DBAL\Query {

use Doctrine\DBAL\FakeConnection;
use Doctrine\DBAL\FakeResult;

final class QueryBuilder {
    public array $wheres = [];
    public array $orWheres = [];
    public array $params = [];
    private string $selectExpr = '*';
    private ?int $maxResults = null;
    private int $firstResult = 0;
    private array $orderBy = [];
    private string $mode = 'select';
    private array $setValues = [];
    private string $table = '';

    public function __construct(private FakeConnection $conn) {}

    public function select(string $expr): static { $this->selectExpr = $expr; return $this; }
    public function from(string $table): static { $this->table = $table; return $this; }
    public function update(string $table): static { $this->mode = 'update'; $this->table = $table; return $this; }
    public function delete(string $table): static { $this->mode = 'delete'; $this->table = $table; return $this; }
    public function set(string $col, string $placeholder): static { $this->setValues[$col] = $placeholder; return $this; }
    public function andWhere(string $expr): static { $this->wheres[] = $expr; return $this; }
    public function orWhere(string $expr): static { $this->orWheres[] = $expr; return $this; }
    public function where(string $expr): static { $this->wheres = [$expr]; return $this; }
    public function andHaving(string $expr): static { return $this; }
    public function groupBy(string $expr): static { return $this; }
    public function addOrderBy(string $col, string $dir): static { $this->orderBy[] = [$col, $dir]; return $this; }
    public function setMaxResults(?int $n): static { $this->maxResults = $n; return $this; }
    public function setFirstResult(int $n): static { $this->firstResult = $n; return $this; }
    public function setParameter(string $name, mixed $value): static { $this->params[$name] = $value; return $this; }
    public function getParameters(): array { return $this->params; }
    public function getQueryPart(string $part): mixed {
        if ($part !== 'where') return null;
        $all = array_merge($this->wheres, $this->orWheres);
        return $all === [] ? null : implode(' AND ', $all);
    }

    private function evalExpr(string $expr, array $row): bool {
        if (trim($expr) === '1 = 0') return false;
        if (preg_match('/^(\w+) IS NULL$/', $expr, $m)) {
            return ($row[$m[1]] ?? null) === null;
        }
        if (preg_match('/^(\w+) IS NOT NULL$/', $expr, $m)) {
            return ($row[$m[1]] ?? null) !== null;
        }
        if (preg_match('/^(\w+) BETWEEN :(\w+) AND :(\w+)$/', $expr, $m)) {
            $val = $row[$m[1]] ?? null;
            return $val !== null && $val >= $this->params[$m[2]] && $val <= $this->params[$m[3]];
        }
        if (preg_match('/^(\w+) NOT BETWEEN :(\w+) AND :(\w+)$/', $expr, $m)) {
            $val = $row[$m[1]] ?? null;
            return $val === null || $val < $this->params[$m[2]] || $val > $this->params[$m[3]];
        }
        if (preg_match('/^(\w+) IN \(([^)]*)\)$/', $expr, $m)) {
            $placeholders = array_map('trim', explode(',', $m[2]));
            $values = array_map(fn($p) => $this->params[ltrim($p, ':')], $placeholders);
            return in_array($row[$m[1]] ?? null, $values, true);
        }
        if (preg_match('/^(\w+)\s*(=|>|<|>=|<=|!=)\s*:(\w+)$/', $expr, $m)) {
            [, $col, $op, $paramName] = $m;
            $val = $this->params[$paramName] ?? null;
            $actual = $row[$col] ?? null;
            return match ($op) {
                '=' => $actual == $val, '!=' => $actual != $val,
                '>' => $actual > $val, '<' => $actual < $val,
                '>=' => $actual >= $val, '<=' => $actual <= $val,
            };
        }
        return true;
    }

    private function matchesRow(array $row): bool {
        foreach ($this->wheres as $expr) { if (!$this->evalExpr($expr, $row)) return false; }
        foreach ($this->orWheres as $expr) { if ($this->evalExpr($expr, $row)) return true; }
        return $this->wheres !== [] || $this->orWheres === [];
    }

    public function executeQuery(): FakeResult {
        $this->conn->logQuery($this->table, $this->params);
        $rows = array_values(array_filter($this->conn->tableRows($this->table), fn($r) => $this->matchesRow($r)));

        foreach ($this->orderBy as [$col, $dir]) {
            usort($rows, fn($a, $b) => $dir === 'DESC' ? $b[$col] <=> $a[$col] : $a[$col] <=> $b[$col]);
        }

        if (str_starts_with($this->selectExpr, 'COUNT')) {
            return new FakeResult([['aggregate' => count($rows)]]);
        }

        $rows = array_slice($rows, $this->firstResult, $this->maxResults);
        return new FakeResult($rows);
    }

    public function executeStatement(): int {
        $rows = $this->conn->tableRows($this->table);
        $affected = 0;
        foreach ($rows as $id => $row) {
            if (!$this->matchesRow($row)) continue;
            $affected++;
            if ($this->mode === 'delete') {
                $this->conn->deleteRow($this->table, $id);
            } elseif ($this->mode === 'update') {
                foreach ($this->setValues as $col => $expr) {
                    if (preg_match('/^:(\w+)$/', $expr, $m)) {
                        // Plain assignment: col = :paramName
                        $this->conn->updateRow($this->table, $id, $col, $this->params[$m[1]]);
                    } elseif (preg_match('/^(\w+)\s*([+-])\s*:(\w+)$/', $expr, $m)) {
                        // increment()/decrement(): col = col +/- :paramName
                        $current = $row[$m[1]] ?? 0;
                        $amount = $this->params[$m[3]];
                        $new = $m[2] === '+' ? $current + $amount : $current - $amount;
                        $this->conn->updateRow($this->table, $id, $col, $new);
                    }
                }
            }
        }
        return $affected;
    }
}

}
