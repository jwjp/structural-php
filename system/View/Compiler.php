<?php

namespace App\system\View;

/**
 * Compiles template syntax into plain PHP.
 *
 *     {{ $value }}             escaped output
 *     {!! $html !!}            raw output (only for HTML you built yourself)
 *     {{-- note --}}           comment, removed from the output
 *     @{{ literal }}           prints "{{ literal }}" unchanged
 *     @if(...) @elseif(...) @else @endif
 *     @foreach(...) @endforeach
 *     @extends('layout')  @section('name') ... @endsection  @yield('name', 'default')
 *     @include('partial', ['extra' => 1])
 *
 * Expressions inside {{ }} and directive parentheses are PHP, so templates are
 * trusted code. Never compile a template string that came from a user.
 */
class Compiler {
    /** Matches balanced parentheses, skipping parentheses inside quoted strings. */
    private const ARGS = '(?<args>\((?:[^()\'"]++|\'(?:\\\\.|[^\'\\\\])*+\'|"(?:\\\\.|[^"\\\\])*+"|(?&args))*\))';

    private const DIRECTIVES = 'if|elseif|else|endif|foreach|endforeach|extends|section|endsection|yield|include';

    public function compile(string $template): string {
        // Comments first, so nothing inside them is compiled.
        $php = preg_replace('/\{\{--.*?--\}\}/s', '', $template);

        // Hide escaped @{{ ... }} until the end so the echo rules skip it.
        $literals = [];
        $php = preg_replace_callback('/@(\{\{.*?\}\})/s', static function (array $match) use (&$literals): string {
            $literals[] = $match[1];
            return "\x00LITERAL" . (count($literals) - 1) . "\x00";
        }, $php);

        $php = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', static fn (array $m): string => "<?= {$m[1]} ?>", $php);
        $php = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', static fn (array $m): string => "<?= \$__view->e({$m[1]}) ?>", $php);

        $php = preg_replace_callback(
            '/(?<![\w@])@(?<name>' . self::DIRECTIVES . ')\b(?:[ \t]*' . self::ARGS . ')?/',
            fn (array $m): string => $this->directive($m['name'], isset($m['args']) && $m['args'] !== '' ? substr($m['args'], 1, -1) : null),
            $php
        );

        return preg_replace_callback('/\x00LITERAL(\d+)\x00/', static fn (array $m): string => $literals[(int) $m[1]], $php);
    }

    private function directive(string $name, ?string $args): string {
        $needsArgs = !in_array($name, ['else', 'endif', 'endforeach', 'endsection'], true);
        if ($needsArgs && ($args === null || trim($args) === '')) {
            throw new TemplateException("@{$name} needs arguments in parentheses.");
        }
        return match ($name) {
            'if' => "<?php if ({$args}): ?>",
            'elseif' => "<?php elseif ({$args}): ?>",
            'else' => '<?php else: ?>',
            'endif' => '<?php endif; ?>',
            'foreach' => "<?php foreach ({$args}): ?>",
            'endforeach' => '<?php endforeach; ?>',
            'extends' => "<?php \$__view->extend({$args}); ?>",
            'section' => "<?php \$__view->startSection({$args}); ?>",
            'endsection' => '<?php $__view->stopSection(); ?>',
            'yield' => "<?= \$__view->yieldSection({$args}) ?>",
            // Passes the current variables (minus internal ones) plus any extras to the partial.
            'include' => "<?= \$__view->include(\$__view->scope(get_defined_vars()), {$args}) ?>",
        };
    }
}
