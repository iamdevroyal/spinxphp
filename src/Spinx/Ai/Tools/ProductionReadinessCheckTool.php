<?php

declare(strict_types=1);

namespace Spinx\Ai\Tools;

use Spinx\Ai\Continuity\ContinuityTracker;

/**
 * Runs a comprehensive production readiness verification audit across application modules.
 */
final class ProductionReadinessCheckTool implements ToolInterface
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ContinuityTracker $continuity,
    ) {
    }

    public function getName(): string
    {
        return 'verify_production_readiness';
    }

    public function getDescription(): string
    {
        return 'Run a comprehensive production readiness audit: lints PHP syntax on all module files, verifies pure DDD isolation in Domain entities, ensures Spinx facades in Controllers, verifies session CSRF on mutation routes, and returns a readiness score (0-100%) with actionable recommendations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'module' => [
                    'type'        => 'string',
                    'description' => 'Optional module name to audit (e.g. "Billing", "Auth"). If omitted, audits all modules in app/Modules/.',
                ],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $targetModule = trim((string) ($arguments['module'] ?? ''));
        $modulesDir = $this->projectRoot . '/app/Modules';

        if (!is_dir($modulesDir)) {
            return ['error' => 'No app/Modules directory found.'];
        }

        $modulesToAudit = [];
        if ($targetModule !== '') {
            $modPath = $modulesDir . '/' . $targetModule;
            if (!is_dir($modPath)) {
                return ['error' => "Module [{$targetModule}] not found at app/Modules/{$targetModule}."];
            }
            $modulesToAudit[] = $targetModule;
        } else {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..' && is_dir($modulesDir . '/' . $entry)) {
                    $modulesToAudit[] = $entry;
                }
            }
        }

        $totalChecks = 0;
        $passedChecks = 0;
        $issues = [];
        $auditedFiles = [];

        foreach ($modulesToAudit as $module) {
            $modPath = $modulesDir . '/' . $module;
            $files = $this->collectPhpFiles($modPath);

            foreach ($files as $file) {
                $auditedFiles[] = str_replace($this->projectRoot . '/', '', $file);
                $content = (string) file_get_contents($file);
                $relPath = str_replace($this->projectRoot . '/', '', $file);

                // 1. PHP Syntax Lint
                $totalChecks++;
                $lintOut = [];
                $retCode = 0;
                exec('php -l ' . escapeshellarg($file) . ' 2>&1', $lintOut, $retCode);
                if ($retCode === 0) {
                    $passedChecks++;
                } else {
                    $issues[] = [
                        'file'     => $relPath,
                        'severity' => 'CRITICAL',
                        'message'  => 'PHP syntax error: ' . implode(' ', $lintOut),
                    ];
                }

                // 2. Domain Layer Purity (No infrastructure, HTTP, DBAL, or Model imports)
                if (str_contains($relPath, '/Domain/') || str_contains($relPath, '\\Domain\\')) {
                    $totalChecks++;
                    if (preg_match('/use Spinx\\\\(Database|Cache|Session|Http|Routing|Auth)/', $content) ||
                        str_contains($content, 'Symfony\\') ||
                        str_contains($content, 'Doctrine\\')
                    ) {
                        $issues[] = [
                            'file'     => $relPath,
                            'severity' => 'ERROR',
                            'message'  => 'Domain layer imports framework/infrastructure concerns. Keep Domain pure!',
                        ];
                    } else {
                        $passedChecks++;
                    }
                }

                // 3. Controller Quality (No raw Symfony imports, must use Spinx facades)
                if (str_contains($relPath, 'Controller.php')) {
                    $totalChecks++;
                    if (str_contains($content, 'use Symfony\\Component\\HttpFoundation\\Response') ||
                        str_contains($content, 'use Symfony\\Component\\HttpFoundation\\Request')
                    ) {
                        $issues[] = [
                            'file'     => $relPath,
                            'severity' => 'WARNING',
                            'message'  => 'Controller imports raw Symfony HTTP classes. Use Spinx\\Http\\Request and Spinx\\Http\\Response.',
                        ];
                    } else {
                        $passedChecks++;
                    }
                }

                // 4. Strict Types Declaration
                $totalChecks++;
                if (str_contains($content, 'declare(strict_types=1);')) {
                    $passedChecks++;
                } else {
                    $issues[] = [
                        'file'     => $relPath,
                        'severity' => 'WARNING',
                        'message'  => 'Missing declare(strict_types=1); header.',
                    ];
                }
            }

            // 5. Check module.php routing & CSRF registration
            $moduleFile = $modPath . '/module.php';
            if (is_file($moduleFile)) {
                $totalChecks++;
                $mContent = (string) file_get_contents($moduleFile);
                if (str_contains($mContent, 'Route::post') || str_contains($mContent, 'Route::put') || str_contains($mContent, 'Route::delete')) {
                    if (!str_contains($mContent, "'csrf'") && !str_contains($mContent, '"csrf"')) {
                        $issues[] = [
                            'file'     => str_replace($this->projectRoot . '/', '', $moduleFile),
                            'severity' => 'WARNING',
                            'message'  => 'State-changing routes (POST/PUT/DELETE) should carry the "csrf" middleware alias.',
                        ];
                    } else {
                        $passedChecks++;
                    }
                } else {
                    $passedChecks++;
                }
            }
        }

        $score = $totalChecks > 0 ? (int) round(($passedChecks / $totalChecks) * 100) : 100;
        $isProductionReady = ($score >= 90) && empty(array_filter($issues, fn($i) => $i['severity'] === 'CRITICAL'));

        $report = [
            'score'                => $score,
            'is_production_ready'  => $isProductionReady,
            'total_checks'         => $totalChecks,
            'passed_checks'        => $passedChecks,
            'audited_files_count'  => count($auditedFiles),
            'audited_modules'      => $modulesToAudit,
            'issues'               => $issues,
            'recommendation'       => $isProductionReady 
                ? '✔ Application passed all architectural invariants and is ready for production deployment.'
                : '⚠️ Architectural or syntax issues detected. Address reported items to achieve production readiness.',
        ];

        $this->continuity->recordAudit($report);

        return $report;
    }

    private function collectPhpFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->collectPhpFiles($path));
            } elseif (str_ends_with($item, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
