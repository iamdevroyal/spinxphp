<?php

declare(strict_types=1);

namespace Spinx\Llm;

use Spinx\Llm\Contracts\LlmProviderInterface;

/**
 * Static facade for application-level LLM and AI integrations.
 *
 * Usage:
 *   $reply = Llm::chat('Summarize chapter 3 in two sentences.');
 *   $response = Llm::provider('openai')->generate(new LlmRequest(...));
 */
final class Llm
{
    private static ?LlmManager $manager = null;

    public static function setManager(LlmManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): LlmManager
    {
        if (self::$manager === null) {
            self::$manager = new LlmManager();
        }

        return self::$manager;
    }

    public static function provider(?string $name = null): LlmProviderInterface
    {
        return self::getManager()->provider($name);
    }

    public static function generate(LlmRequest $request, ?string $provider = null): LlmResponse
    {
        return self::getManager()->generate($request, $provider);
    }

    public static function chat(string $prompt, ?string $system = null, ?string $provider = null, ?string $model = null): string
    {
        return self::getManager()->chat($prompt, $system, $provider, $model);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->$method(...$arguments);
    }
}
