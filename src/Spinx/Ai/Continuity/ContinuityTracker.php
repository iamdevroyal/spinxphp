<?php

declare(strict_types=1);

namespace Spinx\Ai\Continuity;

/**
 * Manages persistent project memory and continuity tracking in `.spinx/ai/continuity.json`.
 * Ensures context, decisions, contracts, and file histories are preserved across all agents.
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
            'contracts'    => [],
            'history'      => [],
            'last_audit'   => null,
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
        $this->load();
        $this->data['decisions'][] = [
            'date'     => date('Y-m-d H:i:s'),
            'decision' => $decision,
        ];
        $this->save();
    }

    public function recordAction(string $agent, string $action, array $filesModified = []): void
    {
        $this->load();
        $this->data['history'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'agent'     => $agent,
            'action'    => $action,
            'files'     => $filesModified,
        ];
        $this->save();
    }

    public function recordFileChange(string $agent, string $filePath, string $operation = 'write'): void
    {
        $this->load();
        $this->data['history'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'agent'     => $agent,
            'action'    => "{$operation}: {$filePath}",
            'files'     => [$filePath],
        ];
        $this->save();
    }

    public function recordContract(string $module, string $type, string $name, array $details = []): void
    {
        $this->load();
        if (!isset($this->data['contracts'][$module])) {
            $this->data['contracts'][$module] = [];
        }

        $this->data['contracts'][$module][] = [
            'type'       => $type, // 'entity' | 'repository_interface' | 'service' | 'route' | 'view'
            'name'       => $name,
            'details'    => $details,
            'registered' => date('Y-m-d H:i:s'),
        ];
        $this->save();
    }

    public function recordAudit(array $report): void
    {
        $this->load();
        $this->data['last_audit'] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'report'    => $report,
        ];
        $this->save();
    }

    /**
     * Formats comprehensive project continuity context for injection into agent system prompts.
     */
    public function getContextSummary(): string
    {
        $this->load();
        $modules = implode(', ', $this->detectExistingModules());
        $decisions = '';

        if (!empty($this->data['decisions'])) {
            $decisionsList = array_slice($this->data['decisions'], -5);
            $decisions = "\n## Recent Project Decisions:\n" . implode("\n", array_map(fn($d) => "- {$d['decision']}", $decisionsList));
        }

        $recentHistory = '';
        if (!empty($this->data['history'])) {
            $actions = array_slice($this->data['history'], -6);
            $recentHistory = "\n## Recent Agent Actions:\n" . implode("\n", array_map(function($h) {
                $files = !empty($h['files']) ? ' (' . implode(', ', $h['files']) . ')' : '';
                return "- [{$h['agent']}] {$h['action']}{$files}";
            }, $actions));
        }

        $knownContracts = '';
        if (!empty($this->data['contracts'])) {
            $contractsList = [];
            foreach ($this->data['contracts'] as $mod => $items) {
                foreach (array_slice($items, -4) as $item) {
                    $contractsList[] = "- {$mod} {$item['type']}: {$item['name']}";
                }
            }
            if (!empty($contractsList)) {
                $knownContracts = "\n## Active Module Contracts:\n" . implode("\n", $contractsList);
            }
        }

        return <<<CTX
## Active Project Continuity Context:
- Project Directory: {$this->projectRoot}
- Active Modules: {$modules}{$decisions}{$recentHistory}{$knownContracts}
CTX;
    }

    public function getData(): array
    {
        $this->load();
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
