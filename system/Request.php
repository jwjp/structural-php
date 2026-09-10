<?php

namespace App\system;

class Request {
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly ?string $rawBody = null
    ) {
    }

    public function path(): string {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        return '/' . trim($path, '/') ?: '/';
    }

    public function input(): array {
        return $this->method === 'GET' ? $this->query : $this->body;
    }

    public static function fromGlobals(?string $rawBodyOverride = null): self {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $headers = self::headersFromGlobals();
        $rawBody = $rawBodyOverride ?? (file_get_contents('php://input') ?: '');
        $body = $_POST;

        if ($method === 'POST' && isset($body['_method'])) {
            $override = strtoupper((string) $body['_method']);
            if (in_array($override, ['PATCH', 'PUT', 'DELETE'], true)) {
                $method = $override;
                unset($body['_method']);
            }
        }

        if ($body === [] && $rawBody !== '' && $method !== 'GET') {
            $contentType = strtolower(strtok($headers['content-type'] ?? '', ';') ?: '');
            if ($contentType === 'application/json') {
                $decoded = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new HttpException(400, 'Request body must contain valid JSON.');
                }
                if (!is_array($decoded) || array_is_list($decoded)) {
                    throw new HttpException(400, 'JSON request body must be an object.');
                }
                $body = $decoded;
            } elseif ($contentType === 'application/x-www-form-urlencoded' || $contentType === '') {
                parse_str($rawBody, $body);
            } else {
                throw new HttpException(415, 'Unsupported request content type.');
            }
        }

        return new self($method, $_SERVER['REQUEST_URI'] ?? '/', $_GET, $body, $headers, $rawBody);
    }

    private static function headersFromGlobals(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }
}
