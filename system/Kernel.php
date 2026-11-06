<?php

namespace App\system;

use App\system\Middleware\HandleErrors;
use App\system\Middleware\Middleware;
use App\system\Middleware\Pipeline;
use App\system\Routing\Route;
use App\system\Routing\Routes;
use Closure;
use Throwable;

/**
 * Turns a Request into a Response.
 *
 *     global middleware  ->  route lookup  ->  route middleware  ->  controller action
 *
 * The controller is built by the container, so its constructor dependencies
 * are injected. The action is called through Container::call(), so route
 * parameters ({fooId} -> $fooId) and services (Request $request) are injected
 * as method arguments. A non-Response return value is encoded as JSON.
 */
class Kernel {
    /** @param list<string|Middleware|Closure> $middleware Global middleware, outermost first. */
    public function __construct(
        private readonly Container $container,
        private readonly Routes $routes,
        private readonly array $middleware = []
    ) {
    }

    public function container(): Container {
        return $this->container;
    }

    public function routes(): Routes {
        return $this->routes;
    }

    public function handle(Request $request): Response {
        try {
            return (new Pipeline($this->container))
                ->process($request, $this->middleware, fn (Request $request): Response => $this->dispatch($request));
        } catch (Throwable $exception) {
            // Reached only when no HandleErrors middleware is registered, or it failed itself.
            return HandleErrors::render($exception);
        }
    }

    private function dispatch(Request $request): Response {
        if ($request->method === 'OPTIONS') {
            $allowed = $this->routes->allowedMethods($request->path());
            if ($allowed === []) {
                throw new HttpException(404, "'OPTIONS {$request->path()}' Not Found.");
            }
            return new Response('', 204, ['Allow' => implode(', ', $allowed)]);
        }

        [$route, $parameters] = $this->routes->match($request);
        return (new Pipeline($this->container))->process(
            $request,
            $route->getMiddleware(),
            fn (Request $request): Response => $this->callAction($route, $parameters, $request)
        );
    }

    /** @param array<string, string> $parameters */
    private function callAction(Route $route, array $parameters, Request $request): Response {
        // Middleware may have replaced the request; actions receive the final one.
        $this->container->instance(Request::class, $request);
        [$class, $method] = $route->action;
        $controller = $this->container->get($class);
        $result = $this->container->call([$controller, $method], $parameters);
        return $result instanceof Response ? $result : Response::json($result);
    }
}
