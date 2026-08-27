<?php

declare(strict_types=1);

namespace Spinx\Llm\Contracts;

use Spinx\Llm\LlmRequest;
use Spinx\Llm\LlmResponse;

/**
 * Universal provider-agnostic LLM interface for application AI integrations.
 */
interface LlmProviderInterface
{
    /**
     * Send a completion or multi-turn chat request to the LLM.
     */
    public function generate(LlmRequest $request): LlmResponse;

    /**
     * Stream a response token-by-token.
     *
     * @return \Generator<int, string> Yields string tokens/chunks
     */
    public function stream(LlmRequest $request): \Generator;
}
