<?php

use App\Router;
use App\system\Request;
use App\system\Response;

function routerResponse(Request $request): Response {
    $router = new Router($request, [], false);
    $property = new ReflectionProperty($router, 'response');
    $property->setAccessible(true);
    return $property->getValue($router);
}

return [
    'renders explicit view routes' => static function (): void {
        $response = routerResponse(new Request('GET', '/sample/view'));
        testSame(200, $response->status);
        testTrue(str_contains($response->headers['Content-Type'], 'text/html'));
        testTrue(str_contains($response->body, 'Sample Content'));
    },
    'returns 404 for undeclared routes' => static function (): void {
        $response = routerResponse(new Request('GET', '/missing'));
        testSame(404, $response->status);
        testSame(['message' => "'GET /missing' Not Found.", 'code' => 404], json_decode($response->body, true));
    },
    'returns 405 with allowed methods for known paths' => static function (): void {
        $response = routerResponse(new Request('POST', '/sample/view'));
        testSame(405, $response->status);
        testSame('GET, HEAD, OPTIONS', $response->headers['Allow']);
    },
    'returns allowed methods for OPTIONS requests' => static function (): void {
        $response = routerResponse(new Request('OPTIONS', '/sample/view'));
        testSame(204, $response->status);
        testSame('GET, HEAD, OPTIONS', $response->headers['Allow']);
    },
    'returns 404 for OPTIONS on unknown paths' => static function (): void {
        $response = routerResponse(new Request('OPTIONS', '/missing'));
        testSame(404, $response->status);
    },
];
