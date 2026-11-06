<?php

namespace App\system\Routing;

use App\system\Middleware\Middleware;
use Closure;
use LogicException;

/**
 * One declared route: an HTTP method, a path pattern and a controller action.
 *
 * Path segments written as {name} capture one URL segment. The captured
 * values are passed to the action as named arguments.
 */
class Route {
    /** @var list<string|Middleware|Closure> */
    private array $middleware = [];
    private ?string $name = null;
    private string $pattern;

    /** @param array{0: class-string, 1: string} $action */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $action,
        private readonly Routes $routes,
        private readonly string $namePrefix = ''
    ) {
        $this->pattern = '#^' . preg_replace_callback(
            '/\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\}/',
            static fn (array $match): string => '(?P<' . $match[1] . '>[^/]+)',
            preg_quote($path, '#')
        ) . '$#D';
    }

    /** Names the route so URLs can be generated with Routes::url(). Group name prefixes are added. */
    public function name(string $name): static {
        if ($this->name !== null) {
            throw new LogicException("Route {$this->method} {$this->path} is already named '{$this->name}'.");
        }
        $name = $this->namePrefix . $name;
        $this->name = $name;
        $this->routes->registerName($name, $this);
        return $this;
    }

    public function getName(): ?string {
        return $this->name;
    }

    /** Adds middleware that runs only for this route, after group and global middleware. */
    public function middleware(string|Middleware|Closure ...$middleware): static {
        $this->middleware = [...$this->middleware, ...$middleware];
        return $this;
    }

    /** @return list<string|Middleware|Closure> */
    public function getMiddleware(): array {
        return $this->middleware;
    }

    /**
     * Returns the captured parameters when $path matches, otherwise null.
     *
     * @return array<string, string>|null
     */
    public function match(string $path): ?array {
        if (!preg_match($this->pattern, $path, $matches)) {
            return null;
        }
        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = rawurldecode($value);
            }
        }
        return $parameters;
    }
}
