<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

final class BelongsTo extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private readonly string $foreignKey,
        private readonly string $ownerKey,
    ) {
        parent::__construct($parent, $relatedClass);
    }

    public function getResults(): ?Model
    {
        $foreignValue = $this->parent->getAttribute($this->foreignKey);

        if ($foreignValue === null) {
            return null;
        }

        return $this->relatedClass::query()->where($this->ownerKey, $foreignValue)->first();
    }

    /** @param array<int, Model> $parents */
    public function eagerLoad(array $parents, string $relationName): void
    {
        $foreignValues = $this->distinctAttributeValues($parents, $this->foreignKey);
        $related = $foreignValues === [] ? [] : $this->relatedClass::query()->whereIn($this->ownerKey, $foreignValues)->get();

        $byOwnerKey = [];
        foreach ($related as $model) {
            $byOwnerKey[$model->getAttribute($this->ownerKey)] = $model;
        }

        foreach ($parents as $parent) {
            $key = $parent->getAttribute($this->foreignKey);
            $parent->setRelation($relationName, $byOwnerKey[$key] ?? null);
        }
    }
}
