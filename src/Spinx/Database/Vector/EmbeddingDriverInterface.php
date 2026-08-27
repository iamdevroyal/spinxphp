<?php

declare(strict_types=1);

namespace Spinx\Database\Vector;

/**
 * Interface for generating text embeddings for semantic vector search.
 */
interface EmbeddingDriverInterface
{
    /**
     * Generate a float vector embedding for a single text input.
     *
     * @return float[]
     */
    public function embed(string $text): array;

    /**
     * Generate float vector embeddings for multiple text inputs.
     *
     * @param string[] $texts
     * @return list<float[]>
     */
    public function embedBatch(array $texts): array;

    /**
     * Return the dimensions of the vector embeddings.
     */
    public function dimensions(): int;
}
