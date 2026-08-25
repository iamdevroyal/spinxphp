<?php

declare(strict_types=1);

namespace Spinx\Templating;

/**
 * Compiles Spinx directive syntax into plain PHP/HTML.
 *
 * This is "Spinx Directives": server-side control flow
 * (@if, @foreach, @include) stays server-side and compiles to plain PHP,
 * while @island emits markup with data-spinx-* hydration hooks so any
 * frontend framework — Vue by default, React as a swappable adapter — can
 * mount a real component onto that markup. This is deliberately NOT a
 * Blade clone: Blade's directives are PHP-only, with no concept of a
 * client-side mount point. @island is the piece that makes the templating
 * layer frontend-agnostic rather than PHP-only.
 *
 * Directive grammar:
 *   {{ $expr }}              escaped echo
 *   {!! $expr !!}             raw (unescaped) echo
 *   {{-- comment --}}          stripped entirely, never rendered
 *   @if(...) / @elseif(...) / @else / @endif
 *   @foreach(...) / @endforeach
 *   @include('view', [...])    renders a partial, data array is explicit
 *   @island('Name', [...])     emits a data-spinx-island hydration hook
 *   @vite                       emits dev/prod frontend asset tags
 *
 * Directive arguments are extracted with a balanced-parenthesis scanner
 * (findMatchingParen / splitTopLevelArgs) rather than a single regex,
 * because template authors will write nested expressions like
 * @if(($a && $b) || $c) — a naive `/@if\((.*?)\)/` regex breaks on the
 * first inner `)` and silently truncates the condition.
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
        // The negative-lookahead pattern (?:(?!!!}).)+ — rather than a
        // plain .+? — guarantees this can never skip past its OWN
        // closing "!!}" to grab a distant, unrelated one elsewhere in
        // the document. Found the hard way: a template containing
        // literal prose like "the {{ }} syntax" (documenting the
        // directive rather than using it) caused .+? to fail matching
        // locally against whitespace-only content and instead extend
        // forward to the next real "!!}"/"}}" anywhere in the file,
        // silently swallowing and corrupting everything in between. This
        // pattern fails loudly and locally instead — worst case an empty
        // {!! !!} produces a local PHP parse error at that exact spot,
        // never silent corruption of unrelated content elsewhere.
        return (string) preg_replace('/{!!\s*((?:(?!!!}).)+?)\s*!!}/s', '<?php echo $1; ?>', $source);
    }

    private function compileEscapedEchos(string $source): string
    {
        return (string) preg_replace(
            '/{{\s*((?:(?!}}).)+?)\s*}}/s',
            '<?php echo htmlspecialchars((string) ($1), ENT_QUOTES, \'UTF-8\'); ?>',
            $source
        );
    }

    /** Directives with no parenthesized argument list — @else, @endif, @endforeach. */
    private function compileBareDirectives(string $source): string
    {
        $map = [
            '/@else\b/' => '<?php else: ?>',
            '/@endif\b/' => '<?php endif; ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@vite\b/' => '<?php echo $__spinxRenderer->vite(); ?>',
            '/@csrf\b/' => '<?php echo $__spinxRenderer->csrfField(); ?>',
        ];

        foreach ($map as $pattern => $replacement) {
            $source = (string) preg_replace($pattern, $replacement, $source);
        }

        return $source;
    }

    /** Directives that take a parenthesized argument list — @if, @elseif, @foreach, @include, @island. */
    private function compileParenDirectives(string $source): string
    {
        $pattern = '/@(if|elseif|foreach|include|island)\s*\(/';
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
            'if' => "<?php if({$args}): ?>",
            'elseif' => "<?php elseif({$args}): ?>",
            'foreach' => "<?php foreach({$args}): ?>",
            'include' => $this->compileInclude($args),
            'island' => $this->compileIsland($args),
        };
    }

    private function compileInclude(string $args): string
    {
        // @include('partial', ['x' => 1]) -> render('partial', ['x' => 1])
        // Data is explicit, never auto-merged from the parent scope — a
        // partial's inputs should be readable from its @include call alone.
        return "<?php echo \$__spinxRenderer->render({$args}); ?>";
    }

    private function compileIsland(string $args): string
    {
        $parts = $this->splitTopLevelArgs($args);
        $nameArg = trim($parts[0] ?? '', " \t\n\r\0\x0B'\"");
        $propsExpr = trim($parts[1] ?? '[]');

        if ($nameArg === '') {
            throw new \RuntimeException('@island requires a component name as its first argument, e.g. @island(\'ExampleIsland\').');
        }

        $safeName = htmlspecialchars($nameArg, ENT_QUOTES, 'UTF-8');

        return '<div data-spinx-island="' . $safeName . '" data-spinx-props="<?php echo htmlspecialchars(json_encode(' .
            $propsExpr . ', JSON_THROW_ON_ERROR), ENT_QUOTES, \'UTF-8\'); ?>"></div>';
    }

    /**
     * Scans forward from an opening '(' at $openPos to find its matching
     * ')', tracking nesting depth and skipping over quoted strings (so a
     * ')' inside a string literal doesn't prematurely close the directive).
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
     * Splits a directive's argument string on top-level commas only —
     * commas inside nested (), [], {}, or quoted strings don't split.
     * Used to separate @island('Name', ['prop' => $x]) into its two
     * top-level arguments without breaking on the comma inside the array.
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
