<?php

declare(strict_types=1);

namespace Spinx\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Spinx\Templating\DirectiveCompiler;

final class DirectiveCompilerTest extends TestCase
{
    private DirectiveCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new DirectiveCompiler();
    }

    public function testEscapedEchoSafeArrayImplosion(): void
    {
        $compiled = $this->compiler->compile('{{ $name }}');
        $this->assertStringContainsString('htmlspecialchars(is_array($name ?? null) ? implode(\', \', (array) ($name)) : (string) ($name ?? \'\'), ENT_QUOTES, \'UTF-8\');', $compiled);
    }

    public function testRawEcho(): void
    {
        $compiled = $this->compiler->compile('{!! $html !!}');
        $this->assertStringContainsString('<?php echo $html; ?>', $compiled);
    }

    public function testCommentsStripped(): void
    {
        $compiled = $this->compiler->compile('<h1>Title</h1>{{-- secret comment --}}<p>Text</p>');
        $this->assertEquals('<h1>Title</h1><p>Text</p>', $compiled);
    }

    public function testFormDirectives(): void
    {
        $compiled = $this->compiler->compile('@csrf @method(\'PUT\') @honeypot');
        $this->assertStringContainsString('<?php echo $__spinxRenderer->csrfField(); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->methodField(\'PUT\'); ?>', $compiled);
        $this->assertStringContainsString('name="_spinx_hp_time"', $compiled);
    }

    public function testFormAttributeDirectives(): void
    {
        $input = '<input @checked($isActive) @selected($isDefault) @disabled($isLocked) @readonly(true) @required($req) @autofocus(true) @hidden($isGhost)>';
        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php echo ($isActive) ? \'checked="checked"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo ($isDefault) ? \'selected="selected"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo ($isLocked) ? \'disabled="disabled"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo (true) ? \'readonly="readonly"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo ($req) ? \'required="required"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo (true) ? \'autofocus="autofocus"\' : \'\'; ?>', $compiled);
        $this->assertStringContainsString('<?php echo ($isGhost) ? \'hidden="hidden"\' : \'\'; ?>', $compiled);
    }

    public function testClassAndStyleDirectives(): void
    {
        $input = '<div @class([\'btn\', \'active\' => $isActive]) @style([\'color: red\' => $hasError])>';
        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php echo $__spinxRenderer->classAttr([\'btn\', \'active\' => $isActive]); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->styleAttr([\'color: red\' => $hasError]); ?>', $compiled);
    }

    public function testLayoutSlotAndStackDirectives(): void
    {
        $input = <<<'SPINX'
@layout('Shared::app', ['title' => 'Dashboard'])
@slot('sidebar')
  <aside>Sidebar content</aside>
@endslot

<main>Main body</main>

@push('scripts')
  <script>console.log('pushed');</script>
@endpush
@endlayout
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php $__spinxRenderer->startLayout(\'Shared::app\', [\'title\' => \'Dashboard\']); ?>', $compiled);
        $this->assertStringContainsString('<?php $__spinxRenderer->startSlot(\'sidebar\'); ?>', $compiled);
        $this->assertStringContainsString('<?php $__spinxRenderer->stopSlot(); ?>', $compiled);
        $this->assertStringContainsString('<?php $__spinxRenderer->startPush(\'scripts\'); ?>', $compiled);
        $this->assertStringContainsString('<?php $__spinxRenderer->stopPush(); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->endLayout(); ?>', $compiled);
    }

    public function testSmartLoopAndEmpty(): void
    {
        $input = <<<'SPINX'
@loop($chapters as $chapter)
  <p>{{ $loop->iteration }}: {{ $chapter->title }}</p>
@empty
  <p>No chapters found</p>
@endloop
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('$__loop_t = $chapters;', $compiled);
        $this->assertStringContainsString('$loop = (object)[\'index\' => $__loop_i', $compiled);
        $this->assertStringContainsString('<?php endforeach; else: ?>', $compiled);
        $this->assertStringContainsString('<?php endif; ?>', $compiled);
    }

    public function testErrorAndFlashDirectives(): void
    {
        $input = <<<'SPINX'
@hasErrors
  <div class="alert">Errors present</div>
@endhasErrors

@error('title')
  <span class="err">{{ $message }}</span>
@enderror

@flash('success')
  <div class="toast">{{ $message }}</div>
@endflash
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php if(!empty($errors)): ?>', $compiled);
        $this->assertStringContainsString('<?php if(isset($errors) && !empty($errors[\'title\'])):', $compiled);
        $this->assertStringContainsString('<?php if($__msg = $__spinxRenderer->flash(\'success\')):', $compiled);
    }

    public function testAuthAndRoleGuards(): void
    {
        $input = <<<'SPINX'
@auth
  <p>Welcome, {{ $user->name }}</p>
@endauth

@guest
  <a href="/login">Login</a>
@endguest

@role('admin')
  <button>Admin Panel</button>
@endrole

@can('edit', $post)
  <button>Edit</button>
@endcan
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php if($__spinx_user = $__spinxRenderer->currentUser()): $user = $__spinx_user; ?>', $compiled);
        $this->assertStringContainsString('<?php if(!$__spinxRenderer->currentUser()): ?>', $compiled);
        $this->assertStringContainsString('<?php if($__spinxRenderer->hasRole(\'admin\')):', $compiled);
        $this->assertStringContainsString('<?php if($__spinxRenderer->can(\'edit\', $post)):', $compiled);
    }

    public function testSeoAndMediaDirectives(): void
    {
        $input = <<<'SPINX'
@seo(['title' => 'Page Title', 'description' => 'Summary'])
@svg('icons/pen.svg', ['class' => 'w-5'])
@avatar($user, ['size' => 48])
@timeAgo($post->created_at)
@money(1500, 'USD')
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php echo $__spinxRenderer->seo([\'title\' => \'Page Title\', \'description\' => \'Summary\']); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->svg(\'icons/pen.svg\', [\'class\' => \'w-5\']); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->avatar($user, [\'size\' => 48]); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->timeAgo($post->created_at); ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->formatMoney(1500, \'USD\'); ?>', $compiled);
    }

    public function testJsAndWindowDirectives(): void
    {
        $input = <<<'SPINX'
<script>
  const user = @js($user);
</script>
@window('AppState', ['token' => $token])
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php echo $__spinxRenderer->js($user); ?>', $compiled);
        $this->assertStringContainsString('<script>window.AppState = <?php echo $__spinxRenderer->js([\'token\' => $token]); ?>;</script>', $compiled);
    }

    public function testCacheAndBenchmarkDirectives(): void
    {
        $input = <<<'SPINX'
@cache('sidebar', 3600)
  <div>Cached sidebar</div>
@endcache

@benchmark('complex-query')
  <div>Done</div>
@endbenchmark
SPINX;

        $compiled = $this->compiler->compile($input);

        $this->assertStringContainsString('<?php if($__spinxRenderer->cacheStart(\'sidebar\', 3600)): ?>', $compiled);
        $this->assertStringContainsString('<?php echo $__spinxRenderer->cacheEnd(); endif; ?>', $compiled);
        $this->assertStringContainsString('<?php $__spinx_bench_start = microtime(true);', $compiled);
        $this->assertStringContainsString('<!-- Benchmark:', $compiled);
    }
}
