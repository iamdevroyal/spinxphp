<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Ai\AiManager;
use Spinx\Ai\Anthropic\PromptTemplates;
use Spinx\Ai\Context\FrameworkArchitectureContext;
use Spinx\Ai\Guard\AiGuard;
use Spinx\Ai\Tools\ArchitectureValidatorTool;

$passed = 0;
$failed = 0;

function assertAiTest(string $name, bool $condition, string $msg = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name} - {$msg}\n";
        $failed++;
    }
}

echo "\n========================================================\n";
echo "    Spinx AI Builder Architecture & Guardrails Test\n";
echo "========================================================\n\n";

$projectRoot = dirname(__DIR__, 2);

// ==========================================
// 1. Framework Architecture Context
// ==========================================
echo "1. Framework Architecture Context:\n";

$contextLoader = new FrameworkArchitectureContext($projectRoot);
$context = $contextLoader->getFullContext();

assertAiTest("1a. Architecture context loaded successfully", strlen($context) > 500);
assertAiTest("1b. Architecture context contains strict DDD rules", str_contains($context, 'app/Modules/<ModuleName>/'));
assertAiTest("1c. Architecture context contains Spinx facades", str_contains($context, 'Spinx\Http\Request') && str_contains($context, 'Spinx\Queue\Queue'));
assertAiTest("1d. Architecture context defines explicit anti-patterns", str_contains($context, 'Create global non-DDD folders') && str_contains($context, '$_SESSION'));

// ==========================================
// 2. PromptTemplates Context Injection
// ==========================================
echo "\n2. PromptTemplates System Prompt Injection:\n";

$systemPrompt = PromptTemplates::baseSystemPrompt('Test project context');
assertAiTest("2a. Base system prompt includes authoritative context", str_contains($systemPrompt, 'AUTHORITATIVE SPINX ARCHITECTURE'));
assertAiTest("2b. Base system prompt includes anti-pattern rejection rules", str_contains($systemPrompt, 'Never Assume Laravel'));

// ==========================================
// 3. Intelligent Anti-Pattern Guard
// ==========================================
echo "\n3. Intelligent Anti-Pattern Guard (AiGuard):\n";

// 3a. Detect app/Models violation
$violations1 = AiGuard::detectArchitecturalViolations('Please create a User model in app/Models/User.php');
assertAiTest("3a. AiGuard flags app/Models violation", count($violations1) > 0 && str_contains($violations1[0]['guidance'], 'app/Modules/<ModuleName>/Infrastructure/Persistence/Models/'));

// 3b. Detect routes/web.php violation
$violations2 = AiGuard::detectArchitecturalViolations('Add these routes in routes/web.php');
assertAiTest("3b. AiGuard flags routes/web.php violation", count($violations2) > 0 && str_contains($violations2[0]['guidance'], 'app/Modules/<ModuleName>/module.php'));

// 3c. Detect $_SESSION violation
$violations3 = AiGuard::detectArchitecturalViolations('Store the active cart in $_SESSION["cart"]');
assertAiTest("3c. AiGuard flags \$_SESSION superglobal violation", count($violations3) > 0 && str_contains($violations3[0]['guidance'], 'Spinx\Session\SessionInterface'));

// 3d. Clean prompt has no violations
$cleanPrompt = 'Build a Billing module with Invoice entity and Stripe webhook route';
$cleanViolations = AiGuard::detectArchitecturalViolations($cleanPrompt);
assertAiTest("3d. AiGuard permits clean Spinx DDD prompt", empty($cleanViolations));

// ==========================================
// 4. Architecture Validator Tool
// ==========================================
echo "\n4. Architecture Validator Tool:\n";

$validator = new ArchitectureValidatorTool();

// 4a. Clean Spinx DDD code
$cleanCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Entities;

final class Invoice
{
    public function __construct(private readonly int $id) {}
}
PHP;

$resClean = $validator->execute(['code' => $cleanCode, 'filePath' => 'app/Modules/Billing/Domain/Entities/Invoice.php']);
assertAiTest("4a. Validator approves clean Domain entity", ($resClean['valid'] ?? false) === true);

// 4b. Dirty Domain entity importing DBAL
$dirtyDomainCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Entities;

use Spinx\Database\Model;

final class Invoice extends Model {}
PHP;

$resDirty = $validator->execute(['code' => $dirtyDomainCode, 'filePath' => 'app/Modules/Billing/Domain/Entities/Invoice.php']);
assertAiTest("4b. Validator rejects Domain entity with DBAL/Model import", ($resDirty['valid'] ?? true) === false);

// 4c. Superglobal detection
$superglobalCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Http\Controllers;

final class CartController
{
    public function index() {
        return $_SESSION['user'];
    }
}
PHP;

$resSuper = $validator->execute(['code' => $superglobalCode, 'filePath' => 'app/Modules/Billing/Infrastructure/Http/Controllers/CartController.php']);
assertAiTest("4c. Validator rejects superglobal in controller", ($resSuper['valid'] ?? true) === false);

// ==========================================
// 5. Complete Agent Fleet Registration
// ==========================================
echo "\n5. Agent Fleet & Tools Registration:\n";

$aiManager = new AiManager($projectRoot);

$expectedAgents = [
    'orchestrator',
    'architect',
    'database',
    'routing',
    'frontend',
    'security',
    'devops',
    'async',
    'storage_vector',
];

$allAgentsRegistered = true;
foreach ($expectedAgents as $agentName) {
    try {
        $agent = $aiManager->agent($agentName);
        if ($agent->getName() !== $agentName) {
            $allAgentsRegistered = false;
        }
    } catch (\Throwable $e) {
        $allAgentsRegistered = false;
        echo "  [FAIL] Missing agent [{$agentName}]: " . $e->getMessage() . "\n";
    }
}

assertAiTest("5a. All 9 specialized agents registered in AiManager", $allAgentsRegistered);
assertAiTest("5b. ArchitectureValidatorTool registered in ToolRegistry", $aiManager->getTools()->has('validate_architecture'));
assertAiTest("5c. DelegateToAgentTool registered in ToolRegistry", $aiManager->getTools()->has('delegate_to_agent'));

echo "\n========================================================\n";
echo "  Results: {$passed} assertions passed, {$failed} failed\n";
echo "========================================================\n\n";

if ($failed > 0) {
    exit(1);
}
