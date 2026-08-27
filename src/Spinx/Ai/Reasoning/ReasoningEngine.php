<?php

declare(strict_types=1);

namespace Spinx\Ai\Reasoning;

use Spinx\Ai\Anthropic\ClaudeClient;
use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Performs deep contextual reasoning, bidirectional inspection (frontend <-> backend),
 * and generates architectural plans with clarifying follow-up questions before build execution.
 */
final class ReasoningEngine
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ClaudeClient $client,
        private readonly ContinuityTracker $continuity,
    ) {
    }

    /**
     * Inspect project structure and reason about developer intent.
     */
    public function analyze(string $prompt): ReasoningResult
    {
        $context = $this->inspectProjectContext($prompt);

        $systemPrompt = <<<PROMPT
You are the Spinx AI Framework Architect & Reasoning Engine.
Your job is to analyze the developer's build prompt, inspect existing project architecture (sibling modules, frontend templates, backend controllers), and reason about what needs to be created.

Strict Rules:
1. NEVER guess or create fake stubs. If an existing module (e.g. Auth, Users, Cart) or frontend view exists, ground your plan on it.
2. If the prompt has critical ambiguities or underspecified requirements, formulate 1-3 crisp, high-impact clarifying questions.
3. Propose a strict DDD architecture: Domain Entities, Repository Contracts, Application Services, Infrastructure Controllers & Migrations, and Frontend Views/Islands.
4. Output your response as a valid JSON object matching the requested schema.

Output JSON format:
{
  "analysis": "Summary of what the feature requires and how it fits into existing modules",
  "questions": ["Question 1 if needed", "Question 2 if needed"],
  "suggestions": ["Architectural suggestion 1", "Architectural suggestion 2"],
  "proposedPlan": {
    "module": "ModuleName",
    "domain": ["EntityName", "RepositoryInterface"],
    "application": ["ServiceName"],
    "infrastructure": ["ControllerName", "RepositoryImpl", "MigrationName"],
    "frontend": ["view.spinx.html", "IslandComponent"]
  },
  "readyToBuild": true | false
}
PROMPT;

        $userMessage = "Developer Prompt: \"{$prompt}\"\n\nActive Project Context:\n" . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $response = $this->client->messages(
                messages: [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                systemPrompt: $systemPrompt,
                tools: [],
            );

            $rawText = '';
            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $rawText .= $block['text'];
                }
            }

            // Extract JSON from response text
            if (preg_match('/\{[\s\S]*\}/', $rawText, $matches)) {
                $decoded = json_decode($matches[0], true);
                if (is_array($decoded)) {
                    $suggestions = $decoded['suggestions'] ?? [];
                    $violations = \Spinx\Ai\Guard\AiGuard::detectArchitecturalViolations($prompt);
                    foreach ($violations as $v) {
                        $suggestions[] = "⚠️ Architectural Warning ({$v['pattern']}): {$v['warning']} -> {$v['guidance']}";
                    }

                    return new ReasoningResult(
                        prompt: $prompt,
                        analysis: $decoded['analysis'] ?? 'Analyzed project structure for strict DDD build.',
                        questions: $decoded['questions'] ?? [],
                        suggestions: $suggestions,
                        inspectedContext: $context,
                        proposedPlan: $decoded['proposedPlan'] ?? [],
                        readyToBuild: empty($decoded['questions']) && ($decoded['readyToBuild'] ?? true),
                    );
                }
            }
        } catch (\Throwable) {
            // Fallback rule-based reasoning if API is offline or during testing
        }

        return $this->fallbackAnalysis($prompt, $context);
    }

    /**
     * Bidirectional inspection: collects sibling modules and frontend view templates.
     */
    public function inspectProjectContext(string $prompt): array
    {
        $modules = [];
        $modulesDir = $this->projectRoot . '/app/Modules';
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $modulePath = $modulesDir . '/' . $dir;
                if (is_dir($modulePath)) {
                    $modules[$dir] = [
                        'hasModuleFile' => is_file($modulePath . '/module.php'),
                        'hasDomain'     => is_dir($modulePath . '/Domain'),
                        'hasController' => is_dir($modulePath . '/Infrastructure/Http/Controllers'),
                        'hasViews'      => is_dir($modulePath . '/Infrastructure/Views'),
                    ];
                }
            }
        }

        $frontendFiles = [];
        $frontendDirs = [$this->projectRoot . '/frontend', $this->projectRoot . '/resources/views'];
        foreach ($frontendDirs as $fDir) {
            if (is_dir($fDir)) {
                foreach (scandir($fDir) ?: [] as $f) {
                    if ($f !== '.' && $f !== '..') {
                        $frontendFiles[] = basename($fDir) . '/' . $f;
                    }
                }
            }
        }

        return [
            'existingModules' => $modules,
            'frontendViews'   => $frontendFiles,
            'recentContinuity'=> array_slice($this->continuity->getData()['decisions'] ?? [], -3),
        ];
    }

    private function fallbackAnalysis(string $prompt, array $context): ReasoningResult
    {
        $hasAuth = isset($context['existingModules']['Auth']);
        $suggestions = [
            'Use strict DDD layered structure (Domain, Application, Infrastructure).',
            'Enforce Session-backed CSRF on state mutation routes with @csrf.',
        ];

        $violations = \Spinx\Ai\Guard\AiGuard::detectArchitecturalViolations($prompt);
        foreach ($violations as $v) {
            $suggestions[] = "⚠️ Architectural Warning ({$v['pattern']}): {$v['warning']} -> {$v['guidance']}";
        }

        if (!$hasAuth && (str_contains(strtolower($prompt), 'user') || str_contains(strtolower($prompt), 'auth'))) {
            $suggestions[] = 'Integrate with Auth subsystem using Spinx AuthMiddleware.';
        }

        return new ReasoningResult(
            prompt: $prompt,
            analysis: 'Rule-based analysis formulated a clean DDD blueprint across existing modules.',
            questions: [],
            suggestions: $suggestions,
            inspectedContext: $context,
            proposedPlan: [
                'architecture' => 'Domain-Driven Design (DDD)',
                'framework'    => 'Spinx v1.0.17',
            ],
            readyToBuild: true,
        );
    }
}
