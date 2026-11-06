<?php

namespace App\system\Routing;

use App\system\HttpException;
use App\system\Middleware\Middleware;
use App\system\Request;
use Closure;
use InvalidArgumentException;
use LogicException;
use ReflectionMethod;

/**
 * The route table.
 *
 *     $routes->get('/foo/{fooId}', [FooController::class, 'show'])->name('foo.show');
 *
 *     $routes->group('/admin', function (Routes $routes): void {
 *         $routes->get('/stats', [AdminController::class, 'stats']);   // GET /admin/stats
 *     }, middleware: [RequireAdmin::class], name: 'admin.');
 *
 * Routes are matched in the order they are declared.
 */
class Routes {
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $names = [];

    /** @var list<array{prefix: string, middleware: list<string|Middleware|Closure>, name: string}> */
    private array $groups = [];

    public function get(string $path, array $action): Route {
        return $this->add('GET', $path, $action);
    }

    public function post(string $path, array $action): Route {
        return $this->add('POST', $path, $action);
    }

    public function put(string $path, array $action): Route {
        return $this->add('PUT', $path, $action);
    }

    public function patch(string $path, array $action): Route {
        return $this->add('PATCH', $path, $action);
    }

    public function delete(string $path, array $action): Route {
        return $this->add('DELETE', $path, $action);
    }

    /**
     * Declares routes that share a path prefix, middleware and name prefix.
     * Groups can be nested; prefixes and middleware accumulate from the outside in.
     *
     * @param callable(Routes): void $define
     * @param list<string|Middleware|Closure> $middleware
     */
    public function group(string $prefix, callable $define, array $middleware = [], string $name = ''): void {
        $this->groups[] = ['prefix' => $prefix, 'middleware' => $middleware, 'name' => $name];
        try {
            $define($this);
        } finally {
            array_pop($this->groups);
        }
    }

    /**
     * Finds the route for a request.
     *
     * @return array{0: Route, 1: array<string, string>}
     * @throws HttpException 404 when no route has this path, 405 when the path exists for other methods
     */
    public function match(Request $request): array {
        $path = $request->path();
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        foreach ($this->routes as $route) {
            if ($route->method === $method && ($parameters = $route->match($path)) !== null) {
                return [$route, $parameters];
            }
        }

        $allowed = $this->allowedMethods($path);
        if ($allowed !== []) {
            throw new HttpException(405, 'Method Not Allowed', ['Allow' => implode(', ', $allowed)]);
        }
        throw new HttpException(404, "'{$request->method} {$path}' Not Found.");
    }

    /** @return list<Route> Every route in declaration order. */
    public function all(): array {
        return $this->routes;
    }

    /** @return list<string> Methods declared for $path, plus HEAD and OPTIONS. */
    public function allowedMethods(string $path): array {
        $allowed = [];
        foreach ($this->routes as $route) {
            if ($route->match($path) !== null) {
                $allowed[] = $route->method;
            }
        }
        if ($allowed === []) {
            return [];
        }
        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }
        $allowed[] = 'OPTIONS';
        $allowed = array_values(array_unique($allowed));
        sort($allowed);
        return $allowed;
    }

    /**
     * Builds the path of a named route. Values are URL-encoded; parameters that
     * are not part of the path become the query string.
     */
    public function url(string $name, array $parameters = []): string {
        $route = $this->names[$name] ?? throw new InvalidArgumentException("No route is named '{$name}'.");
        $path = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)}/', function (array $match) use (&$parameters, $name): string {
            if (!array_key_exists($match[1], $parameters) || !is_scalar($parameters[$match[1]]) || (string) $parameters[$match[1]] === '') {
                throw new InvalidArgumentException("Route '{$name}' needs a value for {{$match[1]}}.");
            }
            $value = rawurlencode((string) $parameters[$match[1]]);
            unset($parameters[$match[1]]);
            return $value;
        }, $route->path);
        return $parameters === [] ? $path : $path . '?' . http_build_query($parameters);
    }

    /** @internal Called by Route::name(). */
    public function registerName(string $name, Route $route): void {
        if (isset($this->names[$name])) {
            throw new LogicException("The route name '{$name}' is already used.");
        }
        $this->names[$name] = $route;
    }

    private function add(string $method, string $path, array $action): Route {
        $this->assertAction($action);
        // '/api' + 'v1/' + '/items' -> '/api/v1/items'
        $segments = [...array_column($this->groups, 'prefix'), $path];
        $path = '/' . implode('/', array_filter(array_map(static fn (string $part): string => trim($part, '/'), $segments), 'strlen'));

        // Group name prefixes such as 'foo.' are applied when the route is named.
        $route = new Route($method, $path, $action, $this, implode('', array_column($this->groups, 'name')));
        foreach ($this->groups as $group) {
            $route->middleware(...$group['middleware']);
        }
        $this->routes[] = $route;
        return $route;
    }

    /** Fails at declaration time, not on the first request, when an action does not exist. */
    private function assertAction(array $action): void {
        if (!array_is_list($action) || count($action) !== 2 || !is_string($action[0]) || !is_string($action[1])) {
            throw new InvalidArgumentException('A route action must be [ControllerClass::class, \'method\'].');
        }
        [$class, $method] = $action;
        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new InvalidArgumentException("Route action {$class}::{$method}() does not exist.");
        }
        $reflection = new ReflectionMethod($class, $method);
        if (!$reflection->isPublic() || $reflection->isStatic() || str_starts_with($method, '__')) {
            throw new InvalidArgumentException("Route action {$class}::{$method}() must be a public instance method.");
        }
    }
}
