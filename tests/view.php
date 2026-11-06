<?php

use App\system\View\Compiler;
use App\system\View\TemplateException;
use App\system\View\View;

/** A temporary directory that is deleted when the test run ends. */
function viewTempDir(): string {
    $root = sys_get_temp_dir() . '/structural-view-' . bin2hex(random_bytes(4));
    register_shutdown_function(static function () use ($root): void {
        if (!is_dir($root)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    });
    return $root;
}

/** Creates a throwaway views directory with the given templates. */
function viewWith(array $templates): View {
    $root = viewTempDir();
    foreach ($templates as $name => $source) {
        $path = "{$root}/views/{$name}.tpl";
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $source);
    }
    return new View("{$root}/views", "{$root}/cache");
}

return [
    'escapes {{ }} and leaves {!! !!} raw' => static function (): void {
        $view = viewWith(['page' => '{{ $name }}|{!! $html !!}|{{ $flag }}|{{-- hidden --}}|@{{ $name }}']);
        testSame(
            '&lt;b&gt;&quot;Ada&quot;&#039;|<i>ok</i>|1||{{ $name }}',
            $view->renderToString('page', ['name' => '<b>"Ada"\'', 'html' => '<i>ok</i>', 'flag' => true])
        );
    },
    'compiles control structures with nested parentheses and strings' => static function (): void {
        // A directive must not follow a letter or digit, so "mail@example.com" and "x@if" stay text.
        $view = viewWith(['list' => "@foreach(array_filter(\$items, fn (\$i) => \$i !== ')') as \$item)[{{ \$item }}]@endforeach"
            . " @if(count(\$items) > 2) many @elseif(count(\$items) === 2) two @else few @endif mail@example.com x@if(1)"]);
        testSame('[a][b]  many  mail@example.com x@if(1)', $view->renderToString('list', ['items' => ['a', ')', 'b']]));
        testSame('[a][b]  two  mail@example.com x@if(1)', $view->renderToString('list', ['items' => ['a', 'b']]));
    },
    'layouts receive sections, defaults and nested layouts' => static function (): void {
        $view = viewWith([
            'base' => '<title>@yield(\'title\', \'Default & co\')</title><main>@yield(\'content\')</main>',
            'two-column' => "@extends('base')@section('content')<aside>@yield('side', 'none')</aside>@yield('body')@endsection",
            'page' => "@extends('two-column')@section('body')<p>{{ \$text }}</p>@endsection ignored outside sections",
        ]);
        testSame(
            '<title>Default &amp; co</title><main><aside>none</aside><p>&lt;hi&gt;</p></main>',
            $view->renderToString('page', ['text' => '<hi>'])
        );
    },
    'includes share variables and accept extras' => static function (): void {
        $view = viewWith([
            'row' => '<li>{{ $prefix }}{{ $item }}</li>',
            'list' => "@foreach(\$items as \$item)@include('row', ['prefix' => '#'])@endforeach",
        ]);
        testSame('<li>#1</li><li>#2</li>', $view->renderToString('list', ['items' => [1, 2]]));
    },
    'recompiles changed templates and rejects bad names' => static function (): void {
        $root = viewTempDir();
        mkdir("{$root}/views", 0775, true);
        file_put_contents("{$root}/views/page.tpl", 'one');
        $view = new View("{$root}/views", "{$root}/cache");
        testSame('one', $view->renderToString('page'));
        file_put_contents("{$root}/views/page.tpl", 'two');
        touch("{$root}/views/page.tpl", time() + 5);
        clearstatcache();
        testSame('two', $view->renderToString('page'));
        testThrows(fn () => $view->renderToString('../page'), TemplateException::class);
        testThrows(fn () => $view->renderToString('missing'), TemplateException::class);
    },
    'reports syntax mistakes and cleans up output buffers' => static function (): void {
        testThrows(fn () => (new Compiler())->compile('@if'), TemplateException::class);
        $level = ob_get_level();
        $view = viewWith(['open' => "@section('a')never closed", 'boom' => "@section('a'){{ throw new RuntimeException('x') }}@endsection"]);
        testThrows(fn () => $view->renderToString('open'), TemplateException::class);
        testThrows(fn () => $view->renderToString('boom'), RuntimeException::class);
        testSame($level, ob_get_level());
    },
];
