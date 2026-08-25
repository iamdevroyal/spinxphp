<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

/**
 * v1 implementation resolves through two queries (pivot row lookup, then
 * a whereIn on the related table) rather than a single SQL JOIN, since
 * QueryBuilder doesn't support joins yet. This is correct, just not the
 * most efficient possible query — worth revisiting once QueryBuilder
 * gains join() support.
 */
final class BelongsToMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    /** @return array<int, Model> */
    public function getResults(): array
    {
        $parentId = $this->parent->getAttribute('id');

        if ($parentId === null) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $pivotRows */
        $pivotRows = $this->relatedClass::rawQuery($this->pivotTable)
            ->where($this->foreignPivotKey, $parentId)
            ->get();

        $relatedIds = array_column($pivotRows, $this->relatedPivotKey);

        if ($relatedIds === []) {
            return [];
        }

        return $this->relatedClass::query()->whereIn('id', $relatedIds)->get();
    }

    /**
     * Still bounded (3 queries total regardless of how many parents are
     * in the batch: one pivot lookup, one related lookup), same
     * reasoning as getResults() above about lacking JOIN support — just
     * batched across every parent instead of one pivot+related pair per
     * parent.
     *
     * @param array<int, Model> $parents
     */
    public function eagerLoad(array $parents, string $relationName): void
    {
        $parentIds = $this->distinctAttributeValues($parents, 'id');

        if ($parentIds === []) {
            foreach ($parents as $parent) {
                $parent->setRelation($relationName, []);
            }

            return;
        }

        /** @var array<int, array<string, mixed>> $pivotRows */
        $pivotRows = $this->relatedClass::rawQuery($this->pivotTable)
            ->whereIn($this->foreignPivotKey, $parentIds)
            ->get();

        $relatedIds = array_values(array_unique(array_column($pivotRows, $this->relatedPivotKey)));
        $relatedModels = $relatedIds === [] ? [] : $this->relatedClass::query()->whereIn('id', $relatedIds)->get();

        $relatedById = [];
        foreach ($relatedModels as $model) {
            $relatedById[$model->getAttribute('id')] = $model;
        }

        $grouped = [];
        foreach ($pivotRows as $row) {
            $relatedModel = $relatedById[$row[$this->relatedPivotKey]] ?? null;

            if ($relatedModel !== null) {
                $grouped[$row[$this->foreignPivotKey]][] = $relatedModel;
            }
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute('id');
            $parent->setRelation($relationName, $grouped[$key] ?? []);
        }
    }
}
