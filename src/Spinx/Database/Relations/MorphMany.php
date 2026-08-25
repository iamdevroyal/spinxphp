<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

final class MorphMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $morphIdColumn,
        private readonly string $morphTypeColumn,
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

        return $this->relatedClass::query()
            ->where($this->morphIdColumn, $parentId)
            ->where($this->morphTypeColumn, $this->parent::class)
            ->get();
    }

    /** @param array<int, Model> $parents All the same class, so a single morph type covers the whole batch. */
    public function eagerLoad(array $parents, string $relationName): void
    {
        $parentIds = $this->distinctAttributeValues($parents, 'id');
        $parentType = $this->parent::class;

        $related = $parentIds === [] ? [] : $this->relatedClass::query()
            ->whereIn($this->morphIdColumn, $parentIds)
            ->where($this->morphTypeColumn, $parentType)
            ->get();

        $grouped = [];
        foreach ($related as $model) {
            $grouped[$model->getAttribute($this->morphIdColumn)][] = $model;
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute('id');
            $parent->setRelation($relationName, $grouped[$key] ?? []);
        }
    }
}
