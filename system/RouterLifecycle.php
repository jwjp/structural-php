<?php

namespace App\system;

use Throwable;

abstract class RouterLifecycle {
    protected array $ini;
    protected array $var = [];
    protected array $param = [];
    protected mixed $result = null;
    protected ?Response $response = null;
    protected Request $request;

    private array $routes = [];
    private ?string $activeMethod = null;

    protected function __construct(?Request $request = null, ?array $ini = null, bool $send = true) {
        try {
            $this->request = $request ?? Request::fromGlobals();
            $this->var = $this->request->input();
            $this->ini = $ini ?? (new Ini())->getAll();

            $this->registerRoutes();
            $this->response = $this
                ->middleware()
                ->guard()
                ->interceptor('before')
                ->pipe()
                ->dispatch()
                ->interceptor('after')
                ->normalizeResponse($this->result);
        } catch (Throwable $exception) {
            $this->response = $this->filter($exception);
        } finally {
            $this->end($send);
        }
    }

    protected function exec(string $uri, string $cls, string $fn): static {
        if ($this->activeMethod === null) {
            return $this;
        }
        if (!is_subclass_of($cls, Controller::class)) {
            throw new HttpException(500, "Controller {$cls} must extend " . Controller::class . '.');
        }
        if (!method_exists($cls, $fn)) {
            throw new HttpException(500, "Controller action {$cls}::{$fn} does not exist.");
        }
        $this->routes[] = [
            'method' => $this->activeMethod,
            'uri' => $this->normalizePath($uri),
            'class' => $cls,
            'function' => $fn,
        ];
        return $this;
    }

    protected function middleware(): static {
        return $this;
    }

    protected function guard(): static {
        return $this;
    }

    protected function interceptor(string $when): static {
        return $this;
    }

    protected function pipe(): static {
        return $this;
    }

    protected function filter(Throwable $exception): Response {
        $status = $exception instanceof HttpException ? $exception->status() : 500;
        $headers = $exception instanceof HttpException ? $exception->headers() : [];
        $message = $status >= 500 ? HttpException::defaultMessage($status) : $exception->getMessage();

        return Response::json(['message' => $message, 'code' => $status], $status, $headers);
    }

    protected function end(bool $send = true): void {
        if ($send) {
            $this->response?->send($this->request->method === 'HEAD');
        }
    }

    protected function get(): static {
        return $this;
    }

    protected function post(): static {
        return $this;
    }

    protected function put(): static {
        return $this;
    }

    protected function patch(): static {
        return $this;
    }

    protected function delete(): static {
        return $this;
    }

    private function registerRoutes(): void {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->activeMethod = $method;
            $this->{strtolower($method)}();
        }
        $this->activeMethod = null;
    }

    private function dispatch(): static {
        if ($this->request->method === 'OPTIONS') {
            $allowed = $this->allowedMethods($this->request->path());
            if ($allowed === []) {
                throw new HttpException(404, "'OPTIONS {$this->request->path()}' Not Found.");
            }
            $this->result = Response::json([], 204, ['Allow' => implode(', ', $allowed)]);
            return $this;
        }

        $method = $this->request->method === 'HEAD' ? 'GET' : $this->request->method;
        foreach ($this->routes as $route) {
            $param = $this->matchRoute($route['uri'], $this->request->path());
            if ($route['method'] !== $method || $param === null) {
                continue;
            }

            $this->param = $param;
            $controller = new $route['class']($this->ini, $this->var, $this->param, $this->request);
            $this->result = $controller->{$route['function']}();
            return $this;
        }

        $allowed = $this->allowedMethods($this->request->path());
        if ($allowed !== []) {
            throw new HttpException(405, 'Method Not Allowed', ['Allow' => implode(', ', $allowed)]);
        }

        throw new HttpException(404, "'{$this->request->method} {$this->request->path()}' Not Found.");
    }

    private function normalizeResponse(mixed $result): Response {
        if ($result instanceof Response) {
            return $result;
        }
        return Response::json($result);
    }

    private function allowedMethods(string $path): array {
        $allowed = [];
        foreach ($this->routes as $route) {
            if ($this->matchRoute($route['uri'], $path) !== null) {
                $allowed[] = $route['method'];
            }
        }
        $allowed = array_values(array_unique($allowed));
        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }
        if ($allowed !== []) {
            $allowed[] = 'OPTIONS';
        }
        sort($allowed);
        return $allowed;
    }

    private function matchRoute(string $route, string $path): ?array {
        $routeParts = $this->pathParts($route);
        $pathParts = $this->pathParts($path);
        if (count($routeParts) !== count($pathParts)) {
            return null;
        }

        $param = [];
        foreach ($routeParts as $index => $part) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)}$/', $part, $matches)) {
                $param[$matches[1]] = rawurldecode($pathParts[$index]);
                continue;
            }
            if ($part !== $pathParts[$index]) {
                return null;
            }
        }
        return $param;
    }

    private function normalizePath(string $path): string {
        return '/' . trim($path, '/') ?: '/';
    }

    private function pathParts(string $path): array {
        $path = trim($path, '/');
        return $path === '' ? [] : explode('/', $path);
    }
}
