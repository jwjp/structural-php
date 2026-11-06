<?php

namespace App\Middleware;

use App\system\HttpException;
use App\system\Ini;
use App\system\Middleware\Middleware;
use App\system\Request;
use App\system\Response;

/**
 * Rejects write requests while [APP] READ_ONLY is true ("before" middleware).
 *
 * It is attached to the write routes only, to show route-level middleware.
 * The Ini dependency is injected by the container.
 */
class ReadOnlyMode implements Middleware {
    public function __construct(private readonly Ini $ini) {
    }

    public function enabled(): bool {
        $app = $this->ini->getAll()['APP'] ?? [];
        return is_array($app) && filter_var($app['READ_ONLY'] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function process(Request $request, callable $next): Response {
        if ($this->enabled() && !in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            throw new HttpException(503, 'The site is in read-only mode.', ['Retry-After' => '3600']);
        }
        return $next($request);
    }
}
