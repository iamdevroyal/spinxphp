<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Spinx\Templating\DirectiveCompiler;
use Spinx\Templating\TemplateRenderer;
use Spinx\Templating\TemplateCache;
use Spinx\Templating\ViewFinder;
use Spinx\Templating\Vite;

echo "\n=======================================================\n";
echo "    Spinx Framework Directives Engine Integration Test\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $description, ?string $error = null): void
{
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$description}\n";
        if ($error) {
            echo "         Error: {$error}\n";
        }
    }
}

$compiler = new DirectiveCompiler();

// 1. Safe Echo & Comments
$res1 = $compiler->compile('Hello {{ $user }}, comments: {{-- secret --}}');
assertTest(str_contains($res1, 'htmlspecialchars') && !str_contains($res1, 'secret'), '1. Safe echo & comment stripping compiled');

// 2. Forms & Attributes
$res2 = $compiler->compile('<form>@csrf @method(\'PUT\') @honeypot <input @checked(true) @selected($isSel) @disabled(false)></form>');
assertTest(str_contains($res2, 'csrfField') && str_contains($res2, 'methodField(\'PUT\')') && str_contains($res2, '_spinx_hp_token') && str_contains($res2, 'checked="checked"'), '2. Form directives & attribute flags compiled');

// 3. Dynamic Classes & Styles
$res3 = $compiler->compile('<div @class([\'btn\', \'btn-primary\' => true]) @style([\'color:red\' => true])>');
assertTest(str_contains($res3, 'classAttr') && str_contains($res3, 'styleAttr'), '3. Dynamic @class and @style compiled');

// 4. Layouts, Slots & Stacks
$res4 = $compiler->compile("@layout('app', ['title' => 'Home'])\n@slot('side')SideContent@endslot\nContent\n@push('scripts')<script></script>@endpush\n@endlayout");
assertTest(str_contains($res4, 'startLayout') && str_contains($res4, 'startSlot') && str_contains($res4, 'startPush') && str_contains($res4, 'endLayout'), '4. Layouts, slots & stack directives compiled');

// 5. Smart Loops & Empty Fallbacks
$res5 = $compiler->compile("@loop(\$items as \$item)\nItem: {{ \$item }}\n@empty\nEmptyList\n@endloop");
assertTest(str_contains($res5, '$__loop_t = $items') && str_contains($res5, '$loop = (object)') && str_contains($res5, 'endforeach; else:'), '5. Smart @loop with $loop meta & @empty compiled');

// 6. Errors, Validation & Flash
$res6 = $compiler->compile("@hasErrors\n@error('email')\n<p>{{ \$message }}</p>\n@enderror\n@endhasErrors\n@flash('status')\n<p>{{ \$message }}</p>\n@endflash");
assertTest(str_contains($res6, '!empty($errors)') && str_contains($res6, '$errors[\'email\']') && str_contains($res6, 'flash(\'status\')'), '6. Error alerts & flash message directives compiled');

// 7. Auth, Roles & Guards
$res7 = $compiler->compile("@auth\nHello {{ \$user->name }}\n@endauth\n@guest\nGuest\n@endguest\n@role('admin')\nAdmin\n@endrole");
assertTest(str_contains($res7, '$__spinx_user = $__spinxRenderer->currentUser()') && str_contains($res7, '!$__spinxRenderer->currentUser()') && str_contains($res7, 'hasRole(\'admin\')'), '7. Auth, guest & role guards compiled');

// 8. SEO, Media & Formatting
$res8 = $compiler->compile("@seo(['title' => 'A'])\n@svg('icons/pen.svg')\n@avatar(\$user)\n@date('2026-08-28')\n@timeAgo('2026-08-28 08:00:00')\n@money(2500, 'USD')");
assertTest(str_contains($res8, 'seo([\'title\' => \'A\'])') && str_contains($res8, 'svg(\'icons/pen.svg\')') && str_contains($res8, 'formatMoney(2500, \'USD\')'), '8. SEO, SVG, avatar & formatting helpers compiled');

// 9. JavaScript & Window State
$res9 = $compiler->compile("<script>const u = @js(\$data);</script>\n@window('AppConfig', ['key' => 'val'])");
assertTest(str_contains($res9, 'js($data)') && str_contains($res9, 'window.AppConfig ='), '9. JS state serialization & @window compiled');

// 10. Fragment Caching & Benchmarking
$res10 = $compiler->compile("@cache('nav', 600)\n<nav></nav>\n@endcache\n@benchmark('render-header')\n<header></header>\n@endbenchmark");
assertTest(str_contains($res10, 'cacheStart(\'nav\', 600)') && str_contains($res10, 'cacheEnd()') && str_contains($res10, '__spinx_bench_start'), '10. Fragment caching & benchmark profiling compiled');

echo "\n=======================================================\n";
echo "  Results: {$passCount} passed, {$failCount} failed\n";
echo "=======================================================\n\n";

if ($failCount > 0) {
    exit(1);
}
