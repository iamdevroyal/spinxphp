<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\ClaudeClient;
use Spinx\Ai\Anthropic\PromptTemplates;
use Spinx\Ai\Continuity\ContinuityTracker;
use Spinx\Ai\Tools\ToolRegistry;

/**
 * Specialized Spinx AI Builder agent for Asynchronous Queues and Real-Time WebSocket Broadcasting.
 */
final class AsyncAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'async';
    }

    public function getDescription(): string
    {
        return 'Specialist in asynchronous Queue jobs, multi-queue priorities, retry policies, and real-time WebSocket event broadcasting.';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## AsyncAgent Specialization:
You are the **Async & Real-Time Specialist**.
Your responsibilities:
1. **Asynchronous Jobs (`app/Modules/<Name>/Application/Jobs/<JobName>Job.php`):**
   - Implement `Spinx\Queue\Job` interface (`public function handle(): void`).
   - Keep constructor arguments to simple serializable primitives (IDs, strings, scalars) — never store live DB connections in job properties.
   - Use `Spinx\Queue\JobContext::resolve(Service::class)` if needed inside `handle()`.
   - Dispatch via `Queue::push($job)`, `Queue::onQueue('name')->withPriority(10)->push($job)`, or `Queue::later(60, $job)`.

2. **Real-Time WebSocket Broadcasting (`app/Modules/<Name>/Domain/Events/` or `Application/Events/`):**
   - Implement `Spinx\Broadcasting\ShouldBroadcast` interface.
   - Return `Channel`, `PrivateChannel`, or `PresenceChannel` in `broadcastOn()`.
   - Return payload array in `broadcastWith()`.
   - Dispatch via `Broadcast::event($event)` or `Broadcast::channel('name')->event('EventName', $data)`.
   - Register channel authorization callbacks in `module.php` using `Broadcast::channelAuth('channel.{id}', fn(\$user, \$id) => ...)`.

Output clean, production-ready PHP 8.2+ code adhering strictly to Spinx standards.
PROMPT;
    }
}
