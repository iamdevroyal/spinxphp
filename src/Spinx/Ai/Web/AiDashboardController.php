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
            ? '<div style="background:rgba(225,29,99,0.15); border:1px solid #E11D63; color:#ffb2bf; padding:12px 18px; border-radius:12px; margin-bottom:20px; font-size:14px;">⚠️ <strong>ANTHROPIC_API_KEY</strong> is missing in <code>.env</code>. Add your key to start building autonomously.</div>' 
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
            height: 140px;
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
        .btn-build {
            background: linear-gradient(135deg, #E11D63, #BE185D);
            color: #FFF;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn-build:hover { transform: translateY(-1px); box-shadow: 0 10px 25px var(--primary-glow); }
        .btn-build:disabled { opacity: 0.5; cursor: not-allowed; }
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
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo-wrap">
                <div class="logo-badge">SPINX AI</div>
                <div>
                    <h1 class="title">Autonomous Framework Builder</h1>
                    <p class="subtitle">Powered by Anthropic Claude Sonnet 4.6 • Strict DDD Architecture</p>
                </div>
            </div>
            <div class="agent-pill">⚡ Orchestrator Ready</div>
        </header>

        {$apiKeyWarning}

        <div class="grid">
            <div style="display:flex; flex-direction:column; gap:24px;">
                <div class="card prompt-box">
                    <h2 style="font-size:18px; font-weight:700;">Prompt the Builder</h2>
                    <textarea id="promptInput" placeholder="Describe the module or feature you want to build (e.g. 'Create an Invoicing module with Customer entities, payment repository, and Stripe webhooks')"></textarea>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:13px; color:var(--text-muted);">Strict DDD rules applied automatically</span>
                        <button id="btnBuild" class="btn-build" onclick="runBuild()">
                            <span>Generate & Build Module</span>
                            <span>⚡</span>
                        </button>
                    </div>
                </div>

                <div class="card">
                    <h2 style="font-size:18px; font-weight:700; margin-bottom:14px;">Build Execution Stream</h2>
                    <div id="logTerminal" class="log-terminal">
                        <span style="color:#71717A;">// AI Builder ready. Enter a prompt above to orchestrate Domain, Application, and Infrastructure code.</span>
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

        async function runBuild() {
            const prompt = document.getElementById('promptInput').value.trim();
            if (!prompt) return;

            const btn = document.getElementById('btnBuild');
            const term = document.getElementById('logTerminal');
            btn.disabled = true;
            btn.innerHTML = '<span>Building...</span> <span>⏳</span>';
            term.innerHTML = '<span style="color:#E11D63;">[Orchestrator]</span> Analyzing prompt and formulating DDD execution plan...<br>';

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
                btn.innerHTML = '<span>Generate & Build Module</span> <span>⚡</span>';
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
