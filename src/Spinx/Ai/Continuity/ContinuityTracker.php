<?php

declare(strict_types=1);

namespace Spinx\Ai\Continuity;

/**
 * Manages persistent project memory and continuity tracking in `.spinx/ai/continuity.json`.
 */
final class ContinuityTracker
{
    private string $filePath;
    private array $data = [];

    public function __construct(
        private readonly string $projectRoot,
    ) {
        $this->filePath = $this->projectRoot . '/.spinx/ai/continuity.json';
        $this->load();
    }

    public function load(): array
    {
        if (is_file($this->filePath)) {
            $content = (string) @file_get_contents($this->filePath);
            $decoded = @json_decode($content, true);
            if (is_array($decoded)) {
                return $this->data = $decoded;
            }
        }

        return $this->data = [
            'project_name' => basename($this->projectRoot),
            'modules'      => $this->detectExistingModules(),
            'decisions'    => [],
            'history'      => [],
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
    }

    public function save(): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->data['updated_at'] = date('Y-m-d H:i:s');
        $this->data['modules'] = array_values(array_unique(array_merge($this->data['modules'] ?? [], $this->detectExistingModules())));

        return @file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
    }

    public function recordDecision(string $decision): void
    {
        $this->data['decisions'][] = [
            'date'     => date('Y-m-d H:i:s'),
            'decision' => $decision,
        ];
        $this->save();
    }

    public function recordAction(string $agent, string $action, array $filesModified = []): void
    {
        $this->data['history'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'agent'     => $agent,
            'action'    => $action,
            'files'     => $filesModified,
        ];
        $this->save();
    }

    /**
     * Formats project continuity context for injection into Claude system prompts.
     */
    public function getContextSummary(): string
    {
        $modules = implode(', ', $this->detectExistingModules());
        $decisions = '';

        if (!empty($this->data['decisions'])) {
            $decisionsList = array_slice($this->data['decisions'], -5);
            $decisions = "\n## Recent Project Decisions:\n" . implode("\n", array_map(fn($d) => "- {$d['decision']}", $decisionsList));
        }

        return <<<CTX
## Active Project Context:
- Project Directory: {$this->projectRoot}
- Active Modules: {$modules}{$decisions}
CTX;
    }

    public function getData(): array
    {
        return $this->data;
    }

    private function detectExistingModules(): array
    {
        $modulesDir = $this->projectRoot . '/app/Modules';
        if (!is_dir($modulesDir)) {
            return [];
        }

        $modules = [];
        foreach (scandir($modulesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($modulesDir . '/' . $entry) && is_file($modulesDir . '/' . $entry . '/module.php')) {
                $modules[] = $entry;
            }
        }

        return $modules;
    }
}
