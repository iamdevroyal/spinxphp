<?php

declare(strict_types=1);

namespace Spinx\Database\Relations;

use Spinx\Database\Model;

/**
 * Unlike every other relation type, MorphTo has no single fixed related
 * class — it's read from the morph type column at runtime (e.g. a
 * Comment's "commentable_type" column might hold "App\Modules\Orders\..."
 * one row and "App\Modules\Posts\..." the next). $relatedClass on the
 * parent Relation class is therefore always empty here and unused.
 */
final class MorphTo extends Relation
{
    public function __construct(
        Model $parent,
        private readonly string $morphIdColumn,
        private readonly string $morphTypeColumn,
    ) {
        parent::__construct($parent, '');
    }

    public function getResults(): ?Model
    {
        $type = $this->parent->getAttribute($this->morphTypeColumn);
        $id = $this->parent->getAttribute($this->morphIdColumn);

        if ($type === null || $id === null || !is_string($type) || !class_exists($type)) {
            return null;
        }

        /** @var class-string<Model> $type */
        return $type::find($id);
    }

    /**
     * Groups parents by their morph type first (since each parent can
     * point to a DIFFERENT related class — the whole reason MorphTo
     * exists), then issues one whereIn query per distinct type present
     * in the batch. Still a large improvement over one query per row
     * whenever a batch has few distinct types, which is the common case
     * (e.g. comments mostly on Posts, occasionally on Products — two
     * queries total, not N).
     *
     * @param array<int, Model> $parents
     */
    public function eagerLoad(array $parents, string $relationName): void
    {
        /** @var array<class-string<Model>, list<int|string>> $idsByType */
        $idsByType = [];

        foreach ($parents as $parent) {
            $type = $parent->getAttribute($this->morphTypeColumn);
            $id = $parent->getAttribute($this->morphIdColumn);

            if ($type === null || $id === null || !is_string($type) || !class_exists($type)) {
                continue;
            }

            $idsByType[$type][] = $id;
        }

        /** @var array<class-string<Model>, array<int|string, Model>> $resolvedByType */
        $resolvedByType = [];

        foreach ($idsByType as $type => $ids) {
            $ids = array_values(array_unique($ids));

            foreach ($type::query()->whereIn('id', $ids)->get() as $model) {
                $resolvedByType[$type][$model->getAttribute('id')] = $model;
            }
        }

        foreach ($parents as $parent) {
            $type = $parent->getAttribute($this->morphTypeColumn);
            $id = $parent->getAttribute($this->morphIdColumn);
            $parent->setRelation($relationName, $resolvedByType[$type][$id] ?? null);
        }
    }
}
