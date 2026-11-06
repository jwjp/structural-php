<?php

namespace App\system\Middleware;

use App\system\Request;
use App\system\Response;

/**
 * One layer of the request pipeline.
 *
 * A middleware can act before the rest of the application runs (inspect or
 * reject the request), after it (change the response), or both:
 *
 *     public function process(Request $request, callable $next): Response {
 *         // before
 *         $response = $next($request);
 *         // after
 *         return $response;
 *     }
 *
 * Returning a response without calling $next stops the request early.
 */
interface Middleware {
    /** @param callable(Request): Response $next */
    public function process(Request $request, callable $next): Response;
}
