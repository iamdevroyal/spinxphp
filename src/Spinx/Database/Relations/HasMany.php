<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

final class HasMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $foreignKey,
        private readonly string $localKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    /** @return array<int, Model> */
    public function getResults(): array
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if ($localValue === null) {
            return [];
        }

        return $this->relatedClass::query()->where($this->foreignKey, $localValue)->get();
    }

    /** @param array<int, Model> $parents */
    public function eagerLoad(array $parents, string $relationName): void
    {
        $localValues = $this->distinctAttributeValues($parents, $this->localKey);
        $related = $localValues === [] ? [] : $this->relatedClass::query()->whereIn($this->foreignKey, $localValues)->get();

        $grouped = [];
        foreach ($related as $model) {
            $grouped[$model->getAttribute($this->foreignKey)][] = $model;
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute($this->localKey);
            $parent->setRelation($relationName, $grouped[$key] ?? []);
        }
    }
}
