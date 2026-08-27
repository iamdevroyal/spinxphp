<?php

declare(strict_types=1);

namespace Spinx\Ai;

use Spinx\Ai\Agents\AgentInterface;
use Spinx\Ai\Agents\ArchitectAgent;
use Spinx\Ai\Agents\DatabaseAgent;
use Spinx\Ai\Agents\DevOpsAgent;
use Spinx\Ai\Agents\FrontendAgent;
use Spinx\Ai\Agents\OrchestratorAgent;
use Spinx\Ai\Agents\RoutingAgent;
use Spinx\Ai\Agents\SecurityAgent;
use Spinx\Ai\Anthropic\ClaudeClient;
use Spinx\Ai\Continuity\ContinuityTracker;
use Spinx\Ai\Guard\AiGuard;
use Spinx\Ai\Reasoning\ReasoningEngine;
use Spinx\Ai\Reasoning\ReasoningResult;
use Spinx\Ai\Tools\ToolRegistry;

/**
 * Main AI Builder manager coordinating Claude client, tools, continuity, reasoning engine, and specialized agents.
 */
final class AiManager
{
    private ClaudeClient $client;
    private ToolRegistry $tools;
    private ContinuityTracker $continuity;
    private ReasoningEngine $reasoning;
    /** @var array<string, AgentInterface> */
    private array $agents = [];

    public function __construct(
        private readonly string $projectRoot,
        ?ClaudeClient $client = null,
        ?ToolRegistry $tools = null,
        ?ContinuityTracker $continuity = null,
    ) {
        $this->client     = $client ?? new ClaudeClient();
        $this->continuity = $continuity ?? new ContinuityTracker($this->projectRoot);
        $this->tools      = $tools ?? new ToolRegistry($this->projectRoot, $this->continuity, fn(string $name) => $this->agent($name));
        $this->reasoning  = new ReasoningEngine($this->projectRoot, $this->client, $this->continuity);
        $this->registerDefaultAgents();
    }

    /**
     * Reason about a developer prompt, inspect project context, and generate plan + clarifying questions.
     */
    public function reason(string $prompt): ReasoningResult
    {
        AiGuard::validatePrompt($prompt, $this->continuity);

        return $this->reasoning->analyze($prompt);
    }

    /**
     * Interactive chat with the Orchestrator agent.
     */
    public function chat(string $prompt, array $conversationHistory = [], ?callable $onStep = null): array
    {
        AiGuard::validatePrompt($prompt, $this->continuity);

        return $this->agent('orchestrator')->handle($prompt, $conversationHistory, $onStep);
    }

    /**
     * Autonomous build execution.
     */
    public function build(string $prompt, ?callable $onStep = null): array
    {
        AiGuard::validatePrompt($prompt, $this->continuity);

        $enhancedPrompt = "Please build the following feature/module end-to-end following Spinx strict DDD standards. You may delegate sub-tasks to specialized agents (architect, database, routing, frontend, security, devops) using delegate_to_agent. Always run verify_production_readiness at the end to ensure 100% production readiness: {$prompt}";

        return $this->agent('orchestrator')->handle($enhancedPrompt, [], $onStep);
    }

    public function agent(string $name = 'orchestrator'): AgentInterface
    {
        if (!isset($this->agents[$name])) {
            throw new \InvalidArgumentException("AI Agent [{$name}] is not registered.");
        }

        return $this->agents[$name];
    }

    public function registerAgent(AgentInterface $agent): void
    {
        $this->agents[$agent->getName()] = $agent;
    }

    public function getTools(): ToolRegistry
    {
        return $this->tools;
    }

    public function getContinuity(): ContinuityTracker
    {
        return $this->continuity;
    }

    public function getReasoning(): ReasoningEngine
    {
        return $this->reasoning;
    }

    public function getClient(): ClaudeClient
    {
        return $this->client;
    }

    private function registerDefaultAgents(): void
    {
        $this->registerAgent(new OrchestratorAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new ArchitectAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new DatabaseAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new RoutingAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new FrontendAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new SecurityAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new DevOpsAgent($this->client, $this->tools, $this->continuity));
        $this->registerAgent(new \Spinx\Ai\Agents\AsyncAgent($this->client, $this->tools, $this->continuity, $this->projectRoot));
        $this->registerAgent(new \Spinx\Ai\Agents\StorageVectorAgent($this->client, $this->tools, $this->continuity, $this->projectRoot));
    }
}
