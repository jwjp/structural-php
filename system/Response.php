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

    /** Responses are immutable; this returns a copy with one header added or replaced. */
    public function withHeader(string $name, string $value): self {
        if (!preg_match('/^[A-Za-z0-9-]+$/D', $name) || preg_match('/[\r\n\0]/', $value)) {
            throw new \InvalidArgumentException('Invalid HTTP header.');
        }
        $headers = array_filter(
            $this->headers,
            static fn (string $key): bool => strcasecmp($key, $name) !== 0,
            ARRAY_FILTER_USE_KEY
        );
        return new self($this->body, $this->status, $headers + [$name => $value]);
    }

    /** Reads a header case-insensitively. */
    public function header(string $name): ?string {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
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
