<?php

declare(strict_types=1);

use App\Application;
use App\system\HttpException;
use App\system\Middleware\HandleErrors;
use App\system\Request;

// The PHP development server sends every request here; let it serve real files itself.
if (PHP_SAPI === 'cli-server') {
    $file = realpath(__DIR__ . rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)));
    if ($file !== false && $file !== __FILE__ && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Last resort for failures while the application itself is being built.
set_exception_handler(static function (Throwable $exception): void {
    HandleErrors::render($exception)->send();
});

try {
    $request = Request::fromGlobals();
} catch (HttpException $exception) {
    // A malformed body is rejected before routing.
    HandleErrors::render($exception)->send();
    return;
}

Application::create()->handle($request)->send($request->method === 'HEAD');
