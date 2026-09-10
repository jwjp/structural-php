<?php

use App\Router;
use App\system\Controller;
use App\system\HttpException;
use App\system\Request;
use App\system\Response;

function routerResponse(Request $request): Response {
    $router = new Router($request, [], false);
    $property = new ReflectionProperty($router, 'response');
    $property->setAccessible(true);
    return $property->getValue($router);
}

class InvalidViewTestController extends Controller {
    public function renderTraversal(): Response {
        return $this->view('../README');
    }
}

function buildRequestFromGlobals(array $server, array $get = [], array $post = []): Request {
    $oldServer = $_SERVER;
    $oldGet = $_GET;
    $oldPost = $_POST;
    try {
        $_SERVER = $server + ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];
        $_GET = $get;
        $_POST = $post;
        return Request::fromGlobals();
    } finally {
        $_SERVER = $oldServer;
        $_GET = $oldGet;
        $_POST = $oldPost;
    }
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
    'normalizes request paths without query strings' => static function (): void {
        $request = new Request('GET', '/foo/7?debug=1');
        testSame('/foo/7', $request->path());
    },
    'supports form method overrides' => static function (): void {
        $request = buildRequestFromGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/foo/7'],
            [],
            ['_method' => 'DELETE']
        );
        testSame('DELETE', $request->method);
        testSame([], $request->body);
    },
    'rejects invalid json and non-object json bodies' => static function (): void {
        $decode = static function (string $body): void {
            $oldServer = $_SERVER;
            $oldGet = $_GET;
            $oldPost = $_POST;
            try {
                $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => 'application/json'];
                $_GET = [];
                $_POST = [];
                Request::fromGlobals($body);
            } finally {
                $_SERVER = $oldServer;
                $_GET = $oldGet;
                $_POST = $oldPost;
            }
        };

        testThrows(fn () => $decode('{'), HttpException::class);
        testThrows(fn () => $decode('"scalar"'), HttpException::class);
        testThrows(fn () => $decode('[1, 2]'), HttpException::class);
    },
    'rejects view traversal paths' => static function (): void {
        $controller = new InvalidViewTestController();
        testThrows(fn () => $controller->renderTraversal(), HttpException::class);
    },
];
