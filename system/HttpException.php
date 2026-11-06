<?php

namespace App\system;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException {
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message === '' ? self::defaultMessage($status) : $message, $status, $previous);
    }

    public function status(): int {
        return $this->status;
    }

    public function headers(): array {
        return $this->headers;
    }

    public static function defaultMessage(int $status): string {
        return match ($status) {
            400 => 'Bad Request',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            415 => 'Unsupported Media Type',
            503 => 'Service Unavailable',
            default => $status >= 500 ? 'Internal Server Error' : 'HTTP Error',
        };
    }
}
