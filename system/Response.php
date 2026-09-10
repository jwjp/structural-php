<?php

namespace App\system;

class Response {
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {
    }

    public static function json(mixed $body, int $status = 200, array $headers = []): self {
        return new self(
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers
        );
    }

    public static function html(string $body, int $status = 200, array $headers = []): self {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public function send(bool $head = false): void {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!$head) {
            echo $this->body;
        }
    }
}
