<?php

declare(strict_types=1);

namespace Spinx\Ai\Web;

use Spinx\Ai\Ai;
use Spinx\Http\Request;
use Spinx\Http\Response;

/**
 * Controller serving the local Spinx AI Builder Web Dashboard at `/_spinx/ai`.
 */
final class AiDashboardController
{
    public function index(): Response
    {
        $continuity = Ai::continuity()->getData();
        $apiKeySet  = !empty(env('ANTHROPIC_API_KEY'));

        return Response::html($this->renderHtml($continuity, $apiKeySet));
    }

    public function reason(): Response
    {
        $prompt = (string) Request::input('prompt', '');

        if (trim($prompt) === '') {
            return Response::jsonError('Prompt cannot be empty.', 422);
        }

        try {
            $reasoning = Ai::reason($prompt);
            return Response::jsonSuccess($reasoning->toArray());
        } catch (\Throwable $e) {
            return Response::jsonError($e->getMessage(), 500);
        }
    }

    public function build(): Response
    {
        $prompt = (string) Request::input('prompt', '');

        if (trim($prompt) === '') {
            return Response::jsonError('Prompt cannot be empty.', 422);
        }

        try {
            $result = Ai::build($prompt);
            return Response::jsonSuccess($result);
        } catch (\Throwable $e) {
            return Response::jsonError($e->getMessage(), 500);
        }
    }

    public function context(): Response
    {
        return Response::jsonSuccess(Ai::continuity()->getData());
    }

    private function renderHtml(array $continuity, bool $apiKeySet): string
    {
        $modulesJson = json_encode($continuity['modules'] ?? ['Auth', 'Todo', 'Health']);
        $apiKeyWarning = !$apiKeySet 
            ? '<div style="background:rgba(225,29,99,0.15); border:1px solid #E11D63; color:#ffb2bf; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-size:14px;">⚠️ <strong>API Key</strong> is missing in <code>.env</code>. Add your key to enable full autonomous execution.</div>' 
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spinx AI Framework Builder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #0A0A0B;
            --bg-card: rgba(18, 18, 20, 0.7);
            --border: rgba(255, 255, 255, 0.08);
            --primary: #E11D63;
            --primary-glow: rgba(225, 29, 99, 0.25);
            --text-main: #FFFFFF;
            --text-muted: #A1A1AA;
            --font-sans: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 40px 24px; width: 100%; flex: 1; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border); }
        .logo-wrap { display: flex; align-items: center; gap: 12px; }
        .logo-badge { background: linear-gradient(135deg, #E11D63, #8B5CF6); padding: 8px 14px; border-radius: 10px; font-weight: 800; font-size: 16px; letter-spacing: -0.5px; }
        .title { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .subtitle { font-size: 14px; color: var(--text-muted); }
        .grid { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
        @media(max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .prompt-box { display: flex; flex-direction: column; gap: 16px; }
        textarea {
            width: 100%;
            height: 130px;
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            color: #FFF;
            font-family: var(--font-sans);
            font-size: 15px;
            resize: vertical;
            outline: none;
            transition: border-color 0.2s;
        }
        textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .btn-primary {
            background: linear-gradient(135deg, #E11D63, #BE185D);
            color: #FFF;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 10px 25px var(--primary-glow); }
        .btn-secondary {
            background: rgba(255,255,255,0.06);
            color: #FFF;
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.12); }
        .log-terminal {
            background: #050506;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            font-family: var(--font-mono);
            font-size: 13px;
            min-height: 280px;
            max-height: 420px;
            overflow-y: auto;
            color: #D4D4D8;
            line-height: 1.6;
        }
        .agent-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(225, 29, 99, 0.15);
            border: 1px solid rgba(225, 29, 99, 0.3);
            color: #ffb2bf;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .module-tag {
            display: inline-block;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-family: var(--font-mono);
            margin: 4px 4px 4px 0;
        }
        .reason-box {
            background: rgba(225,29,99,0.06);
            border: 1px solid rgba(225,29,99,0.25);
            border-radius: 12px;
            padding: 16px;
            margin-top: 12px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo-wrap">
                <div class="logo-badge">SPINX AI</div>
                <div>
                    <h1 class="title">Autonomous Framework Builder</h1>
                    <p class="subtitle">Strict DDD Architecture • Bidirectional Grounding • Sandboxed Execution</p>
                </div>
            </div>
            <div class="agent-pill">⚡ Orchestrator Ready</div>
        </header>

        {$apiKeyWarning}

        <div class="grid">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <div class="card prompt-box">
                    <h2 style="font-size:18px; font-weight:700;">Prompt the Builder</h2>
                    <textarea id="promptInput" placeholder="Describe the module or feature you want to build (e.g. 'Create a Subscription Billing module with Stripe checkout, plan repository, and dashboard view')"></textarea>
                    
                    <div id="reasoningPanel" style="display:none;" class="reason-box">
                        <h4 style="font-weight:700; color:#ffb2bf; margin-bottom:8px;">🧠 Architectural Blueprint & Reasoning</h4>
                        <p id="reasoningText" style="color:#D4D4D8; margin-bottom:10px;"></p>
                        <div id="questionsContainer" style="margin-top:8px;"></div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <span style="font-size:13px; color:var(--text-muted);">Strict DDD rules applied automatically</span>
                        <div style="display:flex; gap:10px;">
                            <button id="btnReason" class="btn-secondary" onclick="runReason()">
                                <span>1. Reason & Blueprint</span>
                            </button>
                            <button id="btnBuild" class="btn-primary" onclick="runBuild()">
                                <span>2. Execute Build</span>
                                <span>⚡</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2 style="font-size:18px; font-weight:700; margin-bottom:14px;">Build Execution Stream</h2>
                    <div id="logTerminal" class="log-terminal">
                        <span style="color:#71717A;">// AI Builder ready. Click "1. Reason & Blueprint" to analyze architecture or "2. Execute Build" to generate code.</span>
                    </div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:24px;">
                <div class="card">
                    <h3 style="font-size:16px; font-weight:700; margin-bottom:12px;">Active Modules</h3>
                    <div id="moduleList"></div>
                </div>

                <div class="card">
                    <h3 style="font-size:16px; font-weight:700; margin-bottom:12px;">Specialized Core Agents</h3>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:10px; font-size:13px; color:var(--text-muted);">
                        <li>🏛️ <strong>Architect:</strong> Domain Entities & Contracts</li>
                        <li>🗄️ <strong>Database:</strong> Migrations & DBAL Models</li>
                        <li>🛣️ <strong>Routing:</strong> Multi-Action Controllers</li>
                        <li>🎨 <strong>Frontend:</strong> Templates & @islands</li>
                        <li>🛡️ <strong>Security:</strong> Session CSRF & Auth</li>
                        <li>⚙️ <strong>DevOps:</strong> Workers, Queues & Caches</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modules = {$modulesJson};
        const moduleList = document.getElementById('moduleList');
        modules.forEach(m => {
            const span = document.createElement('span');
            span.className = 'module-tag';
            span.textContent = m;
            moduleList.appendChild(span);
        });

        async function runReason() {
            const prompt = document.getElementById('promptInput').value.trim();
            if (!prompt) return;

            const btn = document.getElementById('btnReason');
            const term = document.getElementById('logTerminal');
            const rPanel = document.getElementById('reasoningPanel');
            const rText = document.getElementById('reasoningText');
            const qBox = document.getElementById('questionsContainer');

            btn.disabled = true;
            btn.textContent = 'Reasoning...';
            term.innerHTML = '<span style="color:#E11D63;">[ReasoningEngine]</span> Inspecting project context and cross-referencing sibling modules...<br>';

            try {
                const res = await fetch('/_spinx/ai/reason', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt })
                });
                const data = await res.json();

                if (data.success) {
                    const result = data.data;
                    rPanel.style.display = 'block';
                    rText.textContent = result.analysis;
                    
                    if (result.questions && result.questions.length > 0) {
                        qBox.innerHTML = '<strong style="color:#ff92ad;">Clarifying Questions:</strong><ul style="margin:6px 0 0 16px; color:#A1A1AA;">' + 
                            result.questions.map(q => '<li>' + q + '</li>').join('') + '</ul>';
                    } else {
                        qBox.innerHTML = '<span style="color:#10B981;">✔ Requirements clear. Ready for autonomous build.</span>';
                    }

                    term.innerHTML += '<br><span style="color:#10B981;">✔ Reasoning complete!</span><br><br>' + 
                        '<pre style="white-space:pre-wrap; color:#E4E4E7;">' + JSON.stringify(result.proposedPlan, null, 2) + '</pre>';
                } else {
                    term.innerHTML += '<br><span style="color:#EF4444;">✖ Error: ' + (data.message || 'Unknown error') + '</span>';
                }
            } catch(e) {
                term.innerHTML += '<br><span style="color:#EF4444;">✖ Network error: ' + e.message + '</span>';
            } finally {
                btn.disabled = false;
                btn.textContent = '1. Reason & Blueprint';
            }
        }

        async function runBuild() {
            const prompt = document.getElementById('promptInput').value.trim();
            if (!prompt) return;

            const btn = document.getElementById('btnBuild');
            const term = document.getElementById('logTerminal');
            btn.disabled = true;
            btn.innerHTML = '<span>Building...</span> <span>⏳</span>';
            term.innerHTML = '<span style="color:#E11D63;">[Orchestrator]</span> Starting multi-agent autonomous DDD build loop...<br>';

            try {
                const res = await fetch('/_spinx/ai/build', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ prompt })
                });
                const data = await res.json();

                if (data.success) {
                    term.innerHTML += '<br><span style="color:#10B981;">✔ Autonomous build complete!</span><br><br>' + 
                        '<pre style="white-space:pre-wrap; color:#E4E4E7;">' + (data.data.response || JSON.stringify(data.data, null, 2)) + '</pre>';
                } else {
                    term.innerHTML += '<br><span style="color:#EF4444;">✖ Build error: ' + (data.message || 'Unknown error') + '</span>';
                }
            } catch(e) {
                term.innerHTML += '<br><span style="color:#EF4444;">✖ Network error: ' + e.message + '</span>';
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>2. Execute Build</span> <span>⚡</span>';
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
