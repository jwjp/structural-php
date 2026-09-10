<?php

declare(strict_types=1);

use App\system\HttpException;
use App\system\Response;

require_once dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
    $status = $exception instanceof HttpException ? $exception->status() : 500;
    $message = $status >= 500 ? HttpException::defaultMessage($status) : $exception->getMessage();
    Response::json(['message' => $message, 'code' => $status], $status)->send();
});

new App\Router();
