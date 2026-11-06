<?php

namespace App\system\Middleware;

use App\system\HttpException;
use App\system\Request;
use App\system\Response;
use Throwable;

/**
 * Turns exceptions thrown further down the pipeline into JSON error responses.
 *
 * Put it first in the global middleware list so it wraps everything else.
 * HttpException messages are written for clients and are shown as-is. Any
 * other exception is logged and answered with a generic 500 so internal
 * details such as SQL errors or file paths do not leak.
 */
class HandleErrors implements Middleware {
    public function process(Request $request, callable $next): Response {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            return self::render($exception);
        }
    }

    public static function render(Throwable $exception): Response {
        if ($exception instanceof HttpException) {
            return Response::json(
                ['message' => $exception->getMessage(), 'code' => $exception->status()],
                $exception->status(),
                $exception->headers()
            );
        }

        error_log($exception::class . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        return Response::json(['message' => HttpException::defaultMessage(500), 'code' => 500], 500);
    }
}
