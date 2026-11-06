<?php

namespace App\Middleware;

use App\system\Middleware\Middleware;
use App\system\Request;
use App\system\Response;

/**
 * Adds browser security headers to every response ("after" middleware).
 */
class SecurityHeaders implements Middleware {
    public function process(Request $request, callable $next): Response {
        $response = $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (str_starts_with((string) $response->header('Content-Type'), 'text/html')) {
            $response = $response->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'"
            );
        }
        return $response;
    }
}
