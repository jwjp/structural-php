<?php

namespace App\system\View;

use App\system\Response;
use Throwable;

/**
 * Renders templates from the views directory.
 *
 * A template named 'sample/index' lives in views/sample/index.tpl. It is
 * compiled to PHP once and cached; the cache is rebuilt when the template
 * file changes. See Compiler for the syntax and docs/templates.md for how
 * layouts and sections work.
 */
class View {
    private const EXTENSION = '.tpl';

    private readonly Compiler $compiler;

    /** @var array<string, string> Finished sections, available to @yield in layouts. */
    private array $sections = [];

    /** @var list<string> Sections that are open (between @section and @endsection). */
    private array $openSections = [];

    private ?string $layout = null;

    public function __construct(
        private readonly string $viewsPath,
        private readonly string $cachePath
    ) {
        $this->compiler = new Compiler();
    }

    public function render(string $name, array $data = [], int $status = 200): Response {
        return Response::html($this->renderToString($name, $data), $status);
    }

    public function renderToString(string $name, array $data = []): string {
        // Each render starts clean, so a View can be reused across requests and tests.
        [$sections, $open, $layout] = [$this->sections, $this->openSections, $this->layout];
        $this->sections = $this->openSections = [];
        $this->layout = null;
        try {
            $output = $this->evaluate($name, $data);
            // A child template fills sections and names its layout; render layouts until none is left.
            while ($this->layout !== null) {
                $layoutName = $this->layout;
                $this->layout = null;
                $output = $this->evaluate($layoutName, $data);
            }
            return $output;
        } finally {
            [$this->sections, $this->openSections, $this->layout] = [$sections, $open, $layout];
        }
    }

    /** HTML-escapes a value for {{ }}. */
    public function e(mixed $value): string {
        if (is_bool($value)) {
            $value = $value ? '1' : '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @internal Called by @extends. */
    public function extend(string $layout): void {
        $this->layout = $layout;
    }

    /** @internal Called by @section. */
    public function startSection(string $name): void {
        $this->openSections[] = $name;
        ob_start();
    }

    /** @internal Called by @endsection. */
    public function stopSection(): void {
        $name = array_pop($this->openSections) ?? throw new TemplateException('@endsection without @section.');
        $content = (string) ob_get_clean();
        // The first definition wins: a child's section overrides the layout's default.
        $this->sections[$name] ??= $content;
    }

    /** @internal Called by @yield. The content was escaped when its section was rendered. */
    public function yieldSection(string $name, string $default = ''): string {
        return $this->sections[$name] ?? $this->e($default);
    }

    /** @internal Called by @include. */
    public function include(array $scope, string $name, array $extra = []): string {
        return $this->evaluate($name, $extra + $scope);
    }

    /** @internal Drops the compiled template's own variables before passing scope to a partial. */
    public function scope(array $variables): array {
        return array_filter($variables, static fn (string $key): bool => !str_starts_with($key, '__'), ARRAY_FILTER_USE_KEY);
    }

    private function evaluate(string $name, array $data): string {
        $__path = $this->compiled($name);
        $__view = $this;
        $level = ob_get_level();
        $openSections = count($this->openSections);
        ob_start();
        try {
            (static function (string $__path, View $__view, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__path;
            })($__path, $__view, $data);
            if (count($this->openSections) > $openSections) {
                $section = $this->openSections[$openSections];
                array_splice($this->openSections, $openSections);
                throw new TemplateException("@section('{$section}') in template '{$name}' is missing @endsection.");
            }
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            // Close buffers opened by the template, including unfinished sections.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $exception;
        }
    }

    private function compiled(string $name): string {
        if (!preg_match('#^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$#D', $name)) {
            throw new TemplateException("Invalid template name '{$name}'.");
        }
        $source = $this->viewsPath . '/' . $name . self::EXTENSION;
        if (!is_file($source)) {
            throw new TemplateException("Template '{$name}' does not exist.");
        }

        $target = $this->cachePath . '/' . sha1($source) . '.php';
        // "<=" also recompiles when both files changed within the same second.
        if (!is_file($target) || filemtime($target) <= filemtime($source)) {
            if (!is_dir($this->cachePath) && !mkdir($this->cachePath, 0775, true) && !is_dir($this->cachePath)) {
                throw new TemplateException('Cannot create the template cache directory.');
            }
            $php = "<?php /* {$name} */ ?>\n" . $this->compiler->compile((string) file_get_contents($source));
            // Write to a temporary file first so a concurrent request never includes half a file.
            $temporary = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (file_put_contents($temporary, $php) === false || !rename($temporary, $target)) {
                @unlink($temporary);
                throw new TemplateException('Cannot write the compiled template.');
            }
        }
        return $target;
    }
}
