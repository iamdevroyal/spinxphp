<?php

declare(strict_types=1);

namespace Spinx\Database\Vector;

use Spinx\Database\DB;
use Spinx\Support\Config;

/**
 * Universal Vector service for embedding generation and semantic similarity search.
 */
final class VectorService
{
    private ?EmbeddingDriverInterface $driver = null;

    public function __construct(
        ?EmbeddingDriverInterface $driver = null,
    ) {
        $this->driver = $driver;
    }

    public function getDriver(): EmbeddingDriverInterface
    {
        return $this->driver ??= $this->resolveDriver();
    }

    /**
     * Generate an embedding vector for given text.
     *
     * @return float[]
     */
    public function embed(string $text): array
    {
        return $this->getDriver()->embed($text);
    }

    /**
     * Perform a semantic similarity vector search.
     *
     * @param string $table Database table name
     * @param string $vectorColumn Column containing vector embeddings (e.g. 'embedding')
     * @param float[]|string $query Embedding float array or raw text to embed on the fly
     * @param array<string, mixed> $filters Optional WHERE filters (e.g. ['project_id' => 1])
     * @param int $limit Max results
     * @param string $distance 'cosine' (<=>), 'l2' (<->), or 'ip' (<#>)
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $table,
        string $vectorColumn,
        array|string $query,
        array $filters = [],
        int $limit = 10,
        string $distance = 'cosine'
    ): array {
        $vector = is_string($query) ? $this->embed($query) : $query;
        $vectorStr = $this->formatVector($vector);

        $operator = match ($distance) {
            'l2', 'euclidean' => '<->',
            'ip', 'inner_product' => '<#>',
            default => '<=>', // cosine distance
        };

        $whereClauses = [];
        $params = ['limit' => $limit];

        foreach ($filters as $col => $val) {
            $paramName = 'f_' . preg_replace('/[^a-zA-Z0-9_]/', '', (string) $col);
            $whereClauses[] = "{$col} = :{$paramName}";
            $params[$paramName] = $val;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $sql = "SELECT *, ({$vectorColumn} {$operator} '{$vectorStr}') AS distance 
                FROM {$table} 
                {$whereSql} 
                ORDER BY {$vectorColumn} {$operator} '{$vectorStr}' ASC 
                LIMIT :limit";

        return DB::select($sql, $params);
    }

    /**
     * Format a PHP float array into a PostgreSQL pgvector literal string: '[0.1,0.2,...]'.
     *
     * @param float[] $vector
     */
    public function formatVector(array $vector): string
    {
        $sanitized = array_map(fn($v) => (float) $v, $vector);
        return '[' . implode(',', $sanitized) . ']';
    }

    private function resolveDriver(): EmbeddingDriverInterface
    {
        $driver = (string) Config::get('vector.default', env('VECTOR_DRIVER', 'openai'));

        return match ($driver) {
            'openai' => new OpenAiEmbeddingDriver(),
            default  => new OpenAiEmbeddingDriver(),
        };
    }
}
