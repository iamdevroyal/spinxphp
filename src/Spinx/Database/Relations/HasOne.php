<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

final class HasOne extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $foreignKey,
        private readonly string $localKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): ?Model
    {
        $localValue = $this->parent->getAttribute($this->localKey);

        if ($localValue === null) {
            return null;
        }

        return $this->relatedClass::query()->where($this->foreignKey, $localValue)->first();
    }

    /** @param array<int, Model> $parents */
    public function eagerLoad(array $parents, string $relationName): void
    {
        $localValues = $this->distinctAttributeValues($parents, $this->localKey);
        $related = $localValues === [] ? [] : $this->relatedClass::query()->whereIn($this->foreignKey, $localValues)->get();

        $byForeignKey = [];
        foreach ($related as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $byForeignKey[$key] ??= $model; // first match wins for hasOne, matching getResults()' ->first()
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute($this->localKey);
            $parent->setRelation($relationName, $byForeignKey[$key] ?? null);
        }
    }
}
