<?php

namespace App\system\Middleware;

use App\system\Container;
use App\system\Request;
use App\system\Response;
use Closure;
use LogicException;

/**
 * Runs a request through a list of middleware and then a final handler.
 *
 * The list [A, B] with handler H is wrapped from the inside out, like the
 * layers of an onion, so the call order is:
 *
 *     A before -> B before -> H -> B after -> A after
 *
 * Each entry may be a Middleware class name (built by the container when the
 * request reaches it), a Middleware object, or a closure with the same
 * signature as Middleware::process().
 */
class Pipeline {
    public function __construct(private readonly Container $container) {
    }

    /**
     * @param list<string|Middleware|Closure> $middleware
     * @param Closure(Request): Response $handler
     */
    public function process(Request $request, array $middleware, Closure $handler): Response {
        $next = $handler;
        foreach (array_reverse($middleware) as $entry) {
            $next = function (Request $request) use ($entry, $next): Response {
                $layer = is_string($entry) ? $this->container->get($entry) : $entry;
                if ($layer instanceof Middleware) {
                    $response = $layer->process($request, $next);
                } elseif ($layer instanceof Closure) {
                    $response = $layer($request, $next);
                } else {
                    throw new LogicException('Middleware must implement ' . Middleware::class . ' or be a closure.');
                }
                if (!$response instanceof Response) {
                    throw new LogicException('Middleware must return a ' . Response::class . '.');
                }
                return $response;
            };
        }
        return $next($request);
    }
}
