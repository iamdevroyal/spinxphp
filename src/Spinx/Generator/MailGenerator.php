<?php

declare(strict_types=1);

namespace Spinx\Generator;

/**
 * Provisions everything needed to orchestrate one email end to end
 * (build spec — requested addition beyond the original CLI table): a
 * Mailable (subject/view/recipient config), its .spinx.html view, and a
 * Job that dispatches it through the queue rather than sending inline
 * during a request (an SMTP call taking 2 seconds shouldn't make an HTTP
 * response wait 2 seconds).
 */
final class MailGenerator extends AbstractGenerator
{
    /** @return array{files: string[], snippet: string} */
    public function generate(string $moduleName, string $name): array
    {
        $moduleDir = $this->assertModuleExists($moduleName);
        $this->assertValidClassName($name);

        $mailableClass = str_ends_with($name, 'Mailable') ? $name : $name . 'Mailable';
        $jobClass = 'Send' . str_replace('Mailable', '', $mailableClass) . 'Job';
        $viewName = self::toSnakeCase(str_replace('Mailable', '', $mailableClass));

        $mailablePath = $moduleDir . '/Application/Mail/' . $mailableClass . '.php';
        $jobPath = $moduleDir . '/Application/Jobs/' . $jobClass . '.php';
        $viewPath = $moduleDir . '/Infrastructure/Http/Views/mail/' . $viewName . '.spinx.html';

        $replacements = [
            '{{MODULE}}' => $moduleName,
            '{{MAILABLE_CLASS}}' => $mailableClass,
            '{{JOB_CLASS}}' => $jobClass,
            '{{VIEW_NAME}}' => $viewName,
        ];

        $this->writeFile($mailablePath, $this->renderStub('mailable.php.stub', $replacements));
        $this->writeFile($jobPath, $this->renderStub('mail-job.php.stub', $replacements));
        $this->writeFile($viewPath, $this->renderStub('mail-view.spinx.html.stub', $replacements));

        $snippet = <<<PHP
            // Dispatch from a controller or service:
            use App\\Modules\\{$moduleName}\\Application\\Jobs\\{$jobClass};
            use Spinx\\Queue\\QueueManager;

            public function __construct(private readonly QueueManager \$queue) {}

            public function someAction(): void
            {
                \$this->queue->dispatch(new {$jobClass}('user@example.com'));
            }

            // {$jobClass} needs QueueManager + Mailer available — both are
            // already registered in config/container.php. Run a worker to
            // actually process dispatched jobs:
            //   php spinx queue:work
            PHP;

        return ['files' => [$mailablePath, $jobPath, $viewPath], 'snippet' => $snippet];
    }
}
