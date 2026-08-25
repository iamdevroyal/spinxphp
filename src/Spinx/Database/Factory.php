<?php

declare(strict_types=1);

namespace Spinx\Database;

/**
 * @template TModel of Model
 */
abstract class Factory
{
    private ?\Faker\Generator $fakerInstance = null;

    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** @return array<string, mixed> */
    abstract public function definition(): array;

    /**
     * @param array<string, mixed> $overrides
     * @return TModel
     */
    public function make(array $overrides = []): Model
    {
        $modelClass = $this->model();

        return new $modelClass(array_merge($this->definition(), $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return TModel
     */
    public function create(array $overrides = []): Model
    {
        $model = $this->make($overrides);
        $model->save();

        return $model;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<TModel>
     */
    public function createMany(int $count, array $overrides = []): array
    {
        return array_map(fn () => $this->create($overrides), range(1, $count));
    }

    /**
     * fakerphp/faker is a require-dev dependency (see composer.json) —
     * available for local seeding/testing, not required in production.
     */
    protected function faker(): \Faker\Generator
    {
        if (!class_exists(\Faker\Factory::class)) {
            throw new \RuntimeException(
                'fakerphp/faker is not installed. It ships as a require-dev dependency — ' .
                'run `composer install` without --no-dev to use faker() in factories.'
            );
        }

        return $this->fakerInstance ??= \Faker\Factory::create();
    }
}
