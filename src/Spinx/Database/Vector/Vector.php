<?php

declare(strict_types=1);

namespace Spinx\Database\Vector;

/**
 * Static facade for generating embeddings and executing semantic vector searches.
 *
 * Usage:
 *   $embedding = Vector::embed('Artificial intelligence writing tools');
 *   $results = Vector::search('continuity_entries', 'embedding', $embedding, ['project_id' => 10], 5);
 */
final class Vector
{
    private static ?VectorService $service = null;

    public static function setService(VectorService $service): void
    {
        self::$service = $service;
    }

    public static function getService(): VectorService
    {
        if (self::$service === null) {
            self::$service = new VectorService();
        }

        return self::$service;
    }

    public static function embed(string $text): array
    {
        return self::getService()->embed($text);
    }

    public static function search(
        string $table,
        string $vectorColumn,
        array|string $query,
        array $filters = [],
        int $limit = 10,
        string $distance = 'cosine'
    ): array {
        return self::getService()->search($table, $vectorColumn, $query, $filters, $limit, $distance);
    }

    public static function formatVector(array $vector): string
    {
        return self::getService()->formatVector($vector);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getService()->$method(...$arguments);
    }
}
