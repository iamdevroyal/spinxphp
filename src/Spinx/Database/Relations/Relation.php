<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

abstract class Relation
{
    public function __construct(
        protected readonly Model $parent,
        /** @var class-string<Model> Empty string for MorphTo, which resolves its class dynamically */
        protected readonly string $relatedClass,
    ) {
    }

    /** Lazy single-model access — e.g. $order->customer via Model::__get(). @return Model|array<int, Model>|null */
    abstract public function getResults(): mixed;

    /**
     * Batched eager loading for with('relationName') — issues ONE query
     * (or a small, fixed number, for BelongsToMany/MorphTo) covering
     * every model in $parents, then assigns each parent's slice via
     * setRelation(). This is what makes with() genuine eager loading
     * rather than N+1-in-disguise — see each subclass's implementation
     * for the specific batching strategy.
     *
     * @param array<int, Model> $parents Every model in the current result set, same class
     */
    abstract public function eagerLoad(array $parents, string $relationName): void;

    /** @param array<int, Model> $models @return list<int|string> Distinct, non-null values of $attribute across $models */
    protected function distinctAttributeValues(array $models, string $attribute): array
    {
        $values = [];

        foreach ($models as $model) {
            $value = $model->getAttribute($attribute);

            if ($value !== null) {
                $values[$value] = $value; // keyed to dedupe cheaply, array_values() strips keys after
            }
        }

        return array_values($values);
    }
}
