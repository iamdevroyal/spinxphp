<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Compiles Spinx directive syntax into plain PHP/HTML.
 *
 * Spinx Directives support:
 *   1. Layouts & Stacks: @layout, @slot, @renderSlot, @push, @prepend, @stack, @once
 *   2. Forms & Attributes: @csrf, @method, @honeypot, @checked, @selected, @disabled, @readonly, @required, @autofocus, @hidden, @old
 *   3. Styling & CSS: @class, @style, @css, @dark, @light
 *   4. Scripts & JS: @js, @script, @window, @island, @islandLazy, @broadcast, @vite
 *   5. Control Flow: @if, @elseif, @else, @endif, @unless, @when, @has, @missing
 *   6. Iteration: @foreach, @endforeach, @loop, @empty, @endloop
 *   7. Auth & Access: @auth, @guest, @role, @can
 *   8. Errors & Flash: @error, @hasErrors, @flash, @flashAny
 *   9. Media & Formatting: @svg, @image, @avatar, @fileSize, @truncate, @plural, @date, @timeAgo, @money
 *  10. SEO & Metadata: @seo, @title, @meta, @schema
 *  11. Developer & Performance: @dev, @production, @testing, @cache, @benchmark, @dump, @dd
 */
final class DirectiveCompiler
{
    public function compile(string $source): string
    {
        $source = $this->compileComments($source);
        $source = $this->compileRawEchos($source);
        $source = $this->compileEscapedEchos($source);
        $source = $this->compileParenDirectives($source);
        $source = $this->compileBareDirectives($source);

        return $source;
    }

    private function compileComments(string $source): string
    {
        return (string) preg_replace('/{{--.*?--}}/s', '', $source);
    }

    private function compileRawEchos(string $source): string
    {
        return (string) preg_replace('/{!!\s*((?:(?!!!}).)+?)\s*!!}/s', '<?php echo $1; ?>', $source);
    }

    private function compileEscapedEchos(string $source): string
    {
        return (string) preg_replace(
            '/{{\s*((?:(?!}}).)+?)\s*}}/s',
            '<?php echo htmlspecialchars(is_array($1 ?? null) ? implode(\', \', (array) ($1)) : (string) ($1 ?? \'\'), ENT_QUOTES, \'UTF-8\'); ?>',
            $source
        );
    }

    /** Directives with no parenthesized argument list. */
    private function compileBareDirectives(string $source): string
    {
        $map = [
            '/@else\b/'         => '<?php else: ?>',
            '/@endif\b/'        => '<?php endif; ?>',
            '/@endforeach\b/'   => '<?php endforeach; ?>',
            '/@empty\b/'        => '<?php endforeach; else: ?>',
            '/@endloop\b/'      => '<?php endif; ?>',
            '/@endunless\b/'    => '<?php endif; ?>',
            '/@endwhen\b/'      => '<?php endif; ?>',
            '/@endhas\b/'       => '<?php endif; ?>',
            '/@endmissing\b/'   => '<?php endif; ?>',
            '/@endauth\b/'      => '<?php unset($user); endif; ?>',
            '/@endguest\b/'     => '<?php endif; ?>',
            '/@endrole\b/'      => '<?php endif; ?>',
            '/@endcan\b/'       => '<?php endif; ?>',
            '/@enderror\b/'     => '<?php unset($message); endif; ?>',
            '/@hasErrors\b/'    => '<?php if(!empty($errors)): ?>',
            '/@endhasErrors\b/' => '<?php endif; ?>',
            '/@endflash\b/'     => '<?php unset($message); endif; ?>',
            '/@endflashAny\b/'  => '<?php unset($message, $type); endforeach; endif; ?>',
            '/@endlayout\b/'    => '<?php echo $__spinxRenderer->endLayout(); ?>',
            '/@endslot\b/'      => '<?php $__spinxRenderer->stopSlot(); ?>',
            '/@endpush\b/'      => '<?php $__spinxRenderer->stopPush(); ?>',
            '/@endprepend\b/'   => '<?php $__spinxRenderer->stopPrepend(); ?>',
            '/@endonce\b/'      => '<?php endif; ?>',
            '/@endcache\b/'     => '<?php echo $__spinxRenderer->cacheEnd(); endif; ?>',
            '/@endcss\b/'       => '<?php $__spinxRenderer->stopPush(); ?>',
            '/@endscript\b/'    => '<?php $__spinxRenderer->stopPush(); ?>',
            '/@enddev\b/'       => '<?php endif; ?>',
            '/@endproduction\b/'=> '<?php endif; ?>',
            '/@endtesting\b/'   => '<?php endif; ?>',
            '/@enddark\b/'      => '<?php endif; ?>',
            '/@endlight\b/'     => '<?php endif; ?>',
            '/@endbenchmark\b/' => '<?php echo "<!-- Benchmark: " . round((microtime(true) - $__spinx_bench_start) * 1000, 2) . "ms | " . round((memory_get_usage() - $__spinx_bench_mem) / 1024, 2) . "KB -->"; ?>',

            '/@csrf\b/'         => '<?php echo $__spinxRenderer->csrfField(); ?>',
            '/@honeypot\b/'     => '<div style="display:none !important;"><input type="text" name="_spinx_hp_time" value="<?php echo time(); ?>"><input type="text" name="_spinx_hp_token" value="" tabindex="-1" autocomplete="off"></div>',
            '/@vite\b/'         => '<?php echo $__spinxRenderer->vite(); ?>',

            '/@dev\b/'          => '<?php if(in_array(strtolower((string)(\Spinx\Support\Config::get(\'app.env\') ?: env(\'APP_ENV\', \'local\'))), [\'local\', \'dev\', \'development\'], true)): ?>',
            '/@production\b/'   => '<?php if(strtolower((string)(\Spinx\Support\Config::get(\'app.env\') ?: env(\'APP_ENV\', \'production\'))) === \'production\'): ?>',
            '/@testing\b/'      => '<?php if(strtolower((string)(\Spinx\Support\Config::get(\'app.env\') ?: env(\'APP_ENV\', \'testing\'))) === \'testing\'): ?>',
            '/@auth\b/'         => '<?php if($__spinx_user = $__spinxRenderer->currentUser()): $user = $__spinx_user; ?>',
            '/@guest\b/'        => '<?php if(!$__spinxRenderer->currentUser()): ?>',
            '/@dark\b/'         => '<?php if($__spinxRenderer->isDarkTheme($theme ?? null)): ?>',
            '/@light\b/'        => '<?php if(!$__spinxRenderer->isDarkTheme($theme ?? null)): ?>',
            '/@css\b/'          => '<?php $__spinxRenderer->startPush(\'styles\'); ?>',
            '/@script\b/'       => '<?php $__spinxRenderer->startPush(\'scripts\'); ?>',
            '/@flashAny\b/'     => '<?php if($__activeFlashes = $__spinxRenderer->allFlashes()): foreach($__activeFlashes as $type => $message): ?>',
        ];


        foreach ($map as $pattern => $replacement) {
            $source = (string) preg_replace($pattern, $replacement, $source);
        }

        return $source;
    }

    /** Directives that take a parenthesized argument list. */
    private function compileParenDirectives(string $source): string
    {
        $directives = [
            'if', 'elseif', 'unless', 'when', 'has', 'missing',
            'foreach', 'loop',
            'layout', 'slot', 'renderSlot', 'push', 'prepend', 'stack', 'once',
            'include', 'includeIf', 'includeWhen', 'includeUnless',
            'island', 'islandLazy', 'broadcast',
            'method', 'old', 'checked', 'selected', 'disabled', 'readonly', 'required', 'autofocus', 'hidden',
            'class', 'style', 'js', 'window',
            'error', 'flash',
            'role', 'can',
            'seo', 'title', 'meta', 'schema',
            'svg', 'image', 'avatar', 'fileSize', 'truncate', 'plural', 'date', 'timeAgo', 'money',
            'cache', 'benchmark', 'dump', 'dd',
        ];

        $pattern = '/@(' . implode('|', $directives) . ')\s*\(/';
        $offset = 0;
        $result = '';

        while (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            [$fullMatch, $matchStart] = $matches[0];
            $directive = $matches[1][0];
            $parenStart = $matchStart + strlen($fullMatch) - 1;
            $parenEnd = $this->findMatchingParen($source, $parenStart);
            $args = substr($source, $parenStart + 1, $parenEnd - $parenStart - 1);

            $result .= substr($source, $offset, $matchStart - $offset);
            $result .= $this->compileDirective($directive, $args);

            $offset = $parenEnd + 1;
        }

        return $result . substr($source, $offset);
    }

    private function compileDirective(string $name, string $args): string
    {
        return match ($name) {
            // Control flow
            'if'             => "<?php if({$args}): ?>",
            'elseif'         => "<?php elseif({$args}): ?>",
            'unless'         => "<?php if(!({$args})): ?>",
            'when'           => "<?php if({$args}): ?>",
            'has'            => "<?php if(isset({$args}) && !empty({$args})): ?>",
            'missing'        => "<?php if(!isset({$args}) || empty({$args})): ?>",

            // Iteration
            'foreach'        => "<?php foreach({$args}): ?>",
            'loop'           => $this->compileLoop($args),

            // Layouts, Slots & Stacks
            'layout'         => "<?php \$__spinxRenderer->startLayout({$args}); ?>",
            'slot'           => "<?php \$__spinxRenderer->startSlot({$args}); ?>",
            'renderSlot'     => "<?php echo \$__spinxRenderer->renderSlot({$args}); ?>",
            'push'           => "<?php \$__spinxRenderer->startPush({$args}); ?>",
            'prepend'        => "<?php \$__spinxRenderer->startPrepend({$args}); ?>",
            'stack'          => "<?php echo \$__spinxRenderer->yieldStack({$args}); ?>",
            'once'           => $this->compileOnce($args),

            // Partials
            'include'        => "<?php echo \$__spinxRenderer->render({$args}); ?>",
            'includeIf'      => "<?php echo \$__spinxRenderer->renderIf({$args}); ?>",
            'includeWhen'    => $this->compileIncludeWhen($args),
            'includeUnless'  => $this->compileIncludeUnless($args),

            // Islands & Realtime
            'island'         => $this->compileIsland($args, false),
            'islandLazy'     => $this->compileIsland($args, true),
            'broadcast'      => $this->compileBroadcast($args),

            // Form helpers & attributes
            'method'         => "<?php echo \$__spinxRenderer->methodField({$args}); ?>",
            'old'            => "<?php echo htmlspecialchars((string)\$__spinxRenderer->old({$args}), ENT_QUOTES, 'UTF-8'); ?>",
            'checked'        => "<?php echo ({$args}) ? 'checked=\"checked\"' : ''; ?>",
            'selected'       => "<?php echo ({$args}) ? 'selected=\"selected\"' : ''; ?>",
            'disabled'       => "<?php echo ({$args}) ? 'disabled=\"disabled\"' : ''; ?>",
            'readonly'       => "<?php echo ({$args}) ? 'readonly=\"readonly\"' : ''; ?>",
            'required'       => "<?php echo ({$args}) ? 'required=\"required\"' : ''; ?>",
            'autofocus'      => "<?php echo ({$args}) ? 'autofocus=\"autofocus\"' : ''; ?>",
            'hidden'         => "<?php echo ({$args}) ? 'hidden=\"hidden\"' : ''; ?>",

            // Styling & JavaScript
            'class'          => "<?php echo \$__spinxRenderer->classAttr({$args}); ?>",
            'style'          => "<?php echo \$__spinxRenderer->styleAttr({$args}); ?>",
            'js'             => "<?php echo \$__spinxRenderer->js({$args}); ?>",
            'window'         => $this->compileWindow($args),

            // Errors & Alerts
            'error'          => "<?php if(isset(\$errors) && !empty(\$errors[{$args}])): \$message = is_array(\$errors[{$args}]) ? (\$errors[{$args}][0] ?? '') : \$errors[{$args}]; ?>",
            'flash'          => "<?php if(\$__msg = \$__spinxRenderer->flash({$args})): \$message = \$__msg; ?>",

            // Auth & Permissions
            'role'           => "<?php if(\$__spinxRenderer->hasRole({$args})): ?>",
            'can'            => "<?php if(\$__spinxRenderer->can({$args})): ?>",

            // SEO & Structured Data
            'seo'            => "<?php echo \$__spinxRenderer->seo({$args}); ?>",
            'title'          => "<title><?php echo htmlspecialchars((string)({$args}), ENT_QUOTES, 'UTF-8'); ?></title>",
            'meta'           => $this->compileMeta($args),
            'schema'         => $this->compileSchema($args),

            // Media & Formatting
            'svg'            => "<?php echo \$__spinxRenderer->svg({$args}); ?>",
            'image'          => "<?php echo \$__spinxRenderer->image({$args}); ?>",
            'avatar'         => "<?php echo \$__spinxRenderer->avatar({$args}); ?>",
            'fileSize'       => "<?php echo \$__spinxRenderer->fileSize({$args}); ?>",
            'truncate'       => "<?php echo \$__spinxRenderer->truncate({$args}); ?>",
            'plural'         => "<?php echo \$__spinxRenderer->plural({$args}); ?>",
            'date'           => "<?php echo \$__spinxRenderer->formatDate({$args}); ?>",
            'timeAgo'        => "<?php echo \$__spinxRenderer->timeAgo({$args}); ?>",
            'money'          => "<?php echo \$__spinxRenderer->formatMoney({$args}); ?>",

            // Caching & Debugging
            'cache'          => "<?php if(\$__spinxRenderer->cacheStart({$args})): ?>",
            'benchmark'      => "<?php \$__spinx_bench_start = microtime(true); \$__spinx_bench_mem = memory_get_usage(); ?>",
            'dump'           => "<?php if(class_exists(\\Spinx\\Support\\Dumper::class)){ \\Spinx\\Support\\Dumper::dump({$args}); } else { var_dump({$args}); } ?>",
            'dd'             => "<?php if(class_exists(\\Spinx\\Support\\Dumper::class)){ \\Spinx\\Support\\Dumper::dd({$args}); } else { var_dump({$args}); exit(1); } ?>",

            default          => "@{$name}({$args})",
        };
    }

    private function compileLoop(string $args): string
    {
        // Support: $items as $item  OR  $items as $key => $item
        if (preg_match('/^\s*(.+?)\s+as\s+(.+)$/s', $args, $m)) {
            $target = $m[1];
            $asPart = $m[2];

            return "<?php \$__loop_t = {$target}; if(!empty(\$__loop_t)): \$__loop_total = is_countable(\$__loop_t) ? count(\$__loop_t) : 0; \$__loop_i = 0; foreach(\$__loop_t as {$asPart}): \$loop = (object)['index' => \$__loop_i, 'iteration' => \$__loop_i + 1, 'first' => \$__loop_i === 0, 'last' => \$__loop_i === \$__loop_total - 1, 'even' => (\$__loop_i % 2) === 0, 'odd' => (\$__loop_i % 2) !== 0, 'count' => \$__loop_total]; \$__loop_i++; ?>";
        }

        return "<?php foreach({$args}): ?>";
    }

    private function compileOnce(string $args): string
    {
        $id = trim($args, " \t\n\r\0\x0B'\"");
        if ($id === '') {
            $id = md5((string) mt_rand());
        }

        return "<?php if(!\$__spinxRenderer->hasRenderedOnce('" . addslashes($id) . "')): ?>";
    }

    private function compileIncludeWhen(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $cond = $parts[0] ?? 'true';
        $view = $parts[1] ?? "''";
        $data = $parts[2] ?? '[]';

        return "<?php if({$cond}){ echo \$__spinxRenderer->render({$view}, {$data}); } ?>";
    }

    private function compileIncludeUnless(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $cond = $parts[0] ?? 'false';
        $view = $parts[1] ?? "''";
        $data = $parts[2] ?? '[]';

        return "<?php if(!({$cond})){ echo \$__spinxRenderer->render({$view}, {$data}); } ?>";
    }

    private function compileWindow(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $name = trim($parts[0] ?? 'SpinxState', " \t\n\r\0\x0B'\"");
        $data = $parts[1] ?? '[]';

        return "<script>window.{$name} = <?php echo \$__spinxRenderer->js({$data}); ?>;</script>";
    }

    private function compileMeta(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $name = $parts[0] ?? "''";
        $content = $parts[1] ?? "''";

        return "<meta name=\"<?php echo htmlspecialchars((string)({$name}), ENT_QUOTES, 'UTF-8'); ?>\" content=\"<?php echo htmlspecialchars((string)({$content}), ENT_QUOTES, 'UTF-8'); ?>\">";
    }

    private function compileSchema(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $type = $parts[0] ?? "'WebPage'";
        $data = $parts[1] ?? '[]';

        return "<script type=\"application/ld+json\"><?php echo json_encode(array_merge(['@context' => 'https://schema.org', '@type' => {$type}], (array)({$data})), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>";
    }

    private function compileIsland(string $args, bool $lazy): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $nameArg = trim($parts[0] ?? '', " \t\n\r\0\x0B'\"");
        $propsExpr = trim($parts[1] ?? '[]');

        if ($nameArg === '') {
            throw new \RuntimeException('@island requires a component name as its first argument, e.g. @island(\'ExampleIsland\').');
        }

        $safeName = htmlspecialchars($nameArg, ENT_QUOTES, 'UTF-8');
        $lazyAttr = $lazy ? ' data-spinx-lazy="true"' : '';

        return '<div data-spinx-island="' . $safeName . '"' . $lazyAttr . ' data-spinx-props="<?php echo htmlspecialchars(json_encode(' .
            $propsExpr . ', JSON_THROW_ON_ERROR), ENT_QUOTES, \'UTF-8\'); ?>"></div>';
    }

    private function compileBroadcast(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $channel = $parts[0] ?? "''";
        $event = $parts[1] ?? "''";

        return '<div data-spinx-broadcast-channel="<?php echo htmlspecialchars((string)(' . $channel . '), ENT_QUOTES, \'UTF-8\'); ?>" data-spinx-broadcast-event="<?php echo htmlspecialchars((string)(' . $event . '), ENT_QUOTES, \'UTF-8\'); ?>" style="display:none;"></div>';
    }

    /**
     * Scans forward from an opening '(' at $openPos to find its matching ')'.
     */
    private function findMatchingParen(string $source, int $openPos): int
    {
        $depth = 0;
        $length = strlen($source);
        $inString = null;

        for ($i = $openPos; $i < $length; $i++) {
            $char = $source[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    $i++; // skip escaped character
                    continue;
                }
                if ($char === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        throw new \RuntimeException('Unbalanced parentheses in a Spinx directive — template failed to compile.');
    }

    /**
     * Splits a directive's argument string on top-level commas only.
     *
     * @return string[]
     */
    private function splitTopLevelArgs(string $args): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = null;
        $length = strlen($args);

        for ($i = 0; $i < $length; $i++) {
            $char = $args[$i];

            if ($inString !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $args[++$i];
                    continue;
                }
                if ($char === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }
}
