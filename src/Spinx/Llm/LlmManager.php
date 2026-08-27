<?php

declare(strict_types=1);

namespace Spinx\Llm;

use Spinx\Llm\Contracts\LlmProviderInterface;
use Spinx\Llm\Drivers\AnthropicDriver;
use Spinx\Llm\Drivers\OpenAiDriver;
use Spinx\Support\Config;

/**
 * LLM manager coordinating application-level AI providers.
 */
final class LlmManager
{
    /** @var array<string, LlmProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly ?string $defaultProvider = null,
    ) {
    }

    public function provider(?string $name = null): LlmProviderInterface
    {
        $name = $name ?? $this->getDefaultProvider();

        return $this->providers[$name] ??= $this->resolve($name);
    }

    public function getDefaultProvider(): string
    {
        return $this->defaultProvider 
            ?? (string) Config::get('llm.default', env('LLM_PROVIDER', 'anthropic'));
    }

    public function generate(LlmRequest $request, ?string $provider = null): LlmResponse
    {
        return $this->provider($provider)->generate($request);
    }

    public function chat(string $prompt, ?string $system = null, ?string $provider = null, ?string $model = null): string
    {
        $request = new LlmRequest(
            messages: [ChatMessage::user($prompt)],
            model: $model,
            system: $system
        );

        return $this->generate($request, $provider)->text();
    }

    private function resolve(string $name): LlmProviderInterface
    {
        $driver = Config::get("llm.providers.{$name}.driver", $name);

        return match ($driver) {
            'anthropic' => new AnthropicDriver(),
            'openai'    => new OpenAiDriver(),
            default     => throw new \InvalidArgumentException("LLM provider [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->provider()->$method(...$arguments);
    }
}
