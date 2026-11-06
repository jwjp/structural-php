# Template engine

Code: [`system/View/`](../system/View/) · Tests: [`tests/view.php`](../tests/view.php) · Example: [`views/`](../views/)

## Why not plain PHP views

Plain PHP views work, but every output needs `htmlspecialchars(...)`, and forgetting it once is an XSS bug. Layouts need manual header and footer includes. The previous views also lived in `public/`, so the web server could request them directly.

The engine makes the safe choice the short one, and moves templates out of the web root to `views/`.

## Syntax

```text
{{ $title }}                     escaped output
{!! $trustedHtml !!}             raw output, only for HTML you built yourself
{{-- comment --}}                removed from the output
@{{ $title }}                    printed literally as {{ $title }}

@if($a) ... @elseif($b) ... @else ... @endif
@foreach($items as $item) ... @endforeach

@extends('layouts/app')
@section('content') ... @endsection
@yield('title', 'Default title')
@include('partials/row', ['extra' => 1])
```

A directive is recognised only when it does not follow a letter, digit or `@`, so `mail@example.com` stays text. Put a space or line break before a directive that follows text.

## Compiling

`Compiler::compile()` turns the template into PHP with a few regular expressions, in this order:

1. Remove `{{-- --}}` comments.
2. Hide `@{{ }}` so later steps skip it.
3. `{!! x !!}` becomes `<?= x ?>`.
4. `{{ x }}` becomes `<?= $__view->e(x) ?>`.
5. Directives become PHP. `@if($a)` becomes `<?php if ($a): ?>`. The argument pattern matches balanced parentheses and skips parentheses inside quoted strings, so `@foreach(array_filter($xs, fn ($x) => $x !== ')') as $x)` works.

The result is written to `.runtime/views/<sha1>.php` and reused until the template file changes. A temporary file plus `rename()` means a concurrent request never includes half a file.

## Layouts and sections

Rendering `sample/index`, which starts with `@extends('layouts/app')`:

1. The child runs first. `@section('content')` starts an output buffer and `@endsection` stores what was captured. Text outside sections is discarded.
2. `@extends` only records the layout name; the layout runs after the child finishes.
3. The layout's `@yield('content')` prints the stored section. Sections were escaped when they were rendered, so `@yield` does not escape again; a default value is escaped.
4. If the layout itself extends another layout, the loop continues. The first definition of a section wins, so a child overrides its parents.

## Trust boundary

Expressions inside `{{ }}` and directive parentheses are PHP code. Templates must come from your repository, never from users or the database.

## What a production engine adds

A real tokenizer and parser instead of regular expressions (with line numbers in error messages), template inheritance with `parent()` blocks, filters (`{{ name|upper }}`), automatic context-aware escaping for attributes, JavaScript and URLs, and sandboxing for untrusted templates.
