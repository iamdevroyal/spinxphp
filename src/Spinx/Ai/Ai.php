<?php

declare(strict_types=1);

namespace Spinx\Ai;

use Spinx\Ai\Agents\AgentInterface;
use Spinx\Ai\Continuity\ContinuityTracker;
use Spinx\Ai\Tools\ToolRegistry;

/**
 * Static Facade for Spinx AI Framework Builder.
 *
 * Usage:
 *   $result = Ai::build('Create a Payments module with Stripe webhooks');
 *   $chat   = Ai::chat('Explain how the continuity tracker works');
 *   $agent  = Ai::agent('architect');
 */
final class Ai
{
    private static ?AiManager $manager = null;

    public static function setManager(AiManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function getManager(): AiManager
    {
        if (self::$manager === null) {
            $projectRoot = defined('SPINX_PROJECT_ROOT') 
                ? (string) constant('SPINX_PROJECT_ROOT') 
                : dirname(__DIR__, 3);

            self::$manager = new AiManager($projectRoot);
        }

        return self::$manager;
    }

    public static function chat(string $prompt, array $conversationHistory = [], ?callable $onStep = null): array
    {
        return self::getManager()->chat($prompt, $conversationHistory, $onStep);
    }

    public static function build(string $prompt, ?callable $onStep = null): array
    {
        return self::getManager()->build($prompt, $onStep);
    }

    public static function agent(string $name = 'orchestrator'): AgentInterface
    {
        return self::getManager()->agent($name);
    }

    public static function tools(): ToolRegistry
    {
        return self::getManager()->getTools();
    }

    public static function continuity(): ContinuityTracker
    {
        return self::getManager()->getContinuity();
    }
}
