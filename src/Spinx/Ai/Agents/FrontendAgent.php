<?php

declare(strict_types=1);

namespace Spinx\Ai\Agents;

use Spinx\Ai\Anthropic\PromptTemplates;

final class FrontendAgent extends AbstractAgent
{
    public function getName(): string
    {
        return 'frontend';
    }

    public function getDescription(): string
    {
        return 'Specialized in .spinx.html templates with 40+ built-in directives, CSS/Tailwind design, and reactive islands (@island for Vue 3/React 19).';
    }

    public function getSystemPrompt(): string
    {
        $context = $this->continuity->getContextSummary();
        $base    = PromptTemplates::baseSystemPrompt($context);

        return <<<PROMPT
{$base}

## Frontend Agent Focus:
You design modern, responsive, aesthetically premium `.spinx.html` templates in `app/Modules/<Module>/Infrastructure/Views/`.

### MANDATORY DIRECTIVE RULES:
- ALWAYS use `@layout('Shared::app', ['title' => '...']) ... @endlayout` for master layout inheritance. Never duplicate HTML boilerplate.
- ALWAYS use `@loop($items as $item) ... @empty ... @endloop` instead of `@foreach` for collection iteration (provides `\$loop->odd/even/first/last/iteration/count`).
- ALWAYS use `@class(['base', 'conditional' => \$expr])` for dynamic CSS classes instead of PHP ternary string concat.
- ALWAYS use `@style(['prop:val' => \$cond])` for dynamic inline styles.
- ALWAYS use `@error('field') ... @enderror` and `@hasErrors` blocks for form validation display.
- ALWAYS use `@flash('success') ... @endflash` and `@flashAny ... @endflashAny` for notifications.
- ALWAYS use `@csrf`, `@method('PUT')`, `@honeypot` on POST forms.
- ALWAYS use `@checked(\$cond)`, `@selected(\$cond)`, `@disabled(\$cond)`, `@required(\$cond)` for boolean HTML attributes.
- ALWAYS use `@auth ... @endauth`, `@guest ... @endguest`, `@role('admin') ... @endrole`, `@can('ability', \$model) ... @endcan` for conditional rendering.
- ALWAYS use `@seo([...])` at the top of each page view.
- Use `@svg('path/icon.svg', ['class' => '...'])` for inline SVG icons.
- Use `@avatar(\$user, ['size' => 40])` for user avatars.
- Use `@island('ComponentName', \$props)` or `@islandLazy(...)` for client-side Vue/React hydration.
- Use `@js(\$data)` for safely serializing PHP data for JavaScript consumption.
- Use `@window('ConfigName', \$data)` for global JS state.
- Use `@push('scripts') ... @endpush` and `@stack('scripts')` for deferred scripts.
- Use `@timeAgo(\$date)`, `@money(\$val, 'USD')`, `@truncate(\$text, 150)`, `@plural(\$n, 'item')` for formatting.
- Use `@cache('key', 3600) ... @endcache` for expensive HTML fragments.
- NEVER write raw `<?= ?>` or `<?php echo ?>` in templates. All echoes use `{{ \$var }}` (escaped) or `{!! \$var !!}` (unescaped HTML).
- NEVER use old `@extends`/`@yield`/`@section` Blade-style patterns. Spinx uses `@layout`/`@slot`/`@stack`.
PROMPT;
    }
}
