<?php

use App\Application;
use App\system\Container;
use App\system\HttpException;
use App\system\Ini;
use App\system\Kernel;
use App\system\Middleware\HandleErrors;
use App\system\Request;
use App\system\Response;
use App\system\Routing\Routes;

class RouterTestController {
    public function show(string $id, Request $request): array {
        return ['id' => $id, 'method' => $request->method];
    }

    public function fail(): never {
        throw new RuntimeException('database password is secret');
    }

    public function html(): Response {
        return Response::html('<p>ok</p>');
    }

    private function hidden(): void {
    }
}

function iniWith(string $contents): Ini {
    $path = tempnam(sys_get_temp_dir(), 'structural-ini-');
    file_put_contents($path, $contents);
    try {
        return new Ini($path);
    } finally {
        unlink($path);
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
    'renders the sample page through the template engine' => static function (): void {
        $response = Application::create()->handle(new Request('GET', '/sample/view'));
        testSame(200, $response->status);
        testTrue(str_contains($response->header('Content-Type'), 'text/html'));
        testTrue(str_contains($response->body, 'Sample Content'));
        testTrue(str_contains($response->body, '<code>/foo/{fooId}</code>'), 'Route paths must be listed.');
        testSame('nosniff', $response->header('X-Content-Type-Options'));
        testTrue(str_contains((string) $response->header('Content-Security-Policy'), "frame-ancestors 'none'"));
    },
    'returns 404 for undeclared routes with security headers' => static function (): void {
        $response = Application::create()->handle(new Request('GET', '/missing'));
        testSame(404, $response->status);
        testSame(['message' => "'GET /missing' Not Found.", 'code' => 404], json_decode($response->body, true));
        testSame('nosniff', $response->header('X-Content-Type-Options'), 'Error responses also pass through SecurityHeaders.');
    },
    'returns 405 and OPTIONS with allowed methods' => static function (): void {
        $app = Application::create();
        $response = $app->handle(new Request('POST', '/sample/view'));
        testSame(405, $response->status);
        testSame('GET, HEAD, OPTIONS', $response->header('Allow'));
        $response = $app->handle(new Request('OPTIONS', '/foo/7'));
        testSame(204, $response->status);
        testSame('DELETE, GET, HEAD, OPTIONS, PUT', $response->header('Allow'));
        testSame(404, $app->handle(new Request('OPTIONS', '/missing'))->status);
    },
    'read-only mode blocks writes through route middleware only' => static function (): void {
        $app = Application::create(iniWith("[APP]\nREAD_ONLY = \"true\"\n"));
        $response = $app->handle(new Request('POST', '/foo', body: ['message' => 'hi']));
        testSame(503, $response->status);
        testSame('3600', $response->header('Retry-After'));
        testSame('The site is in read-only mode.', json_decode($response->body, true)['message']);
        testTrue(str_contains($app->handle(new Request('GET', '/'))->body, 'blocked'), 'The sample page shows blocked routes.');
    },
    'injects route parameters and the request into actions' => static function (): void {
        $routes = new Routes();
        $routes->get('/items/{id}', [RouterTestController::class, 'show']);
        $kernel = new Kernel(new Container(), $routes, [HandleErrors::class]);
        $response = $kernel->handle(new Request('HEAD', '/items/a%20b'));
        testSame(['id' => 'a b', 'method' => 'HEAD'], json_decode($response->body, true));
    },
    'hides internal exception messages' => static function (): void {
        $routes = new Routes();
        $routes->get('/fail', [RouterTestController::class, 'fail']);
        $logged = ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
        try {
            $response = (new Kernel(new Container(), $routes))->handle(new Request('GET', '/fail'));
        } finally {
            ini_set('error_log', (string) $logged);
        }
        testSame(500, $response->status);
        testTrue(!str_contains($response->body, 'secret'));
    },
    'middleware wraps like an onion and can stop early' => static function (): void {
        $log = [];
        $layer = static function (string $name) use (&$log): Closure {
            return static function (Request $request, callable $next) use ($name, &$log): Response {
                $log[] = "{$name} before";
                $response = $next($request);
                $log[] = "{$name} after";
                return $response->withHeader('X-Layer-' . $name, 'yes');
            };
        };
        $routes = new Routes();
        $routes->group('/admin', static function (Routes $routes) use ($layer): void {
            $routes->get('/page', [RouterTestController::class, 'html'])->middleware($layer('route'));
        }, middleware: [$layer('group')]);
        $routes->get('/closed', [RouterTestController::class, 'html'])->middleware(
            static fn (Request $request, callable $next): Response => Response::json(['closed' => true], 403)
        );
        $kernel = new Kernel(new Container(), $routes, [$layer('global')]);

        $response = $kernel->handle(new Request('GET', '/admin/page'));
        testSame(['global before', 'group before', 'route before', 'route after', 'group after', 'global after'], $log);
        testSame('yes', $response->header('X-Layer-global'));
        testSame(403, $kernel->handle(new Request('GET', '/closed'))->status);
    },
    'groups combine prefixes and names, and url() builds paths' => static function (): void {
        $routes = new Routes();
        $routes->group('/api', static function (Routes $routes): void {
            $routes->group('v1/', static function (Routes $routes): void {
                $routes->get('/items/{id}', [RouterTestController::class, 'show'])->name('items.show');
            }, name: 'v1.');
        }, name: 'api.');
        testSame('/api/v1/items/{id}', $routes->all()[0]->path);
        testSame('/api/v1/items/a%2Fb?page=2', $routes->url('api.v1.items.show', ['id' => 'a/b', 'page' => 2]));
        testThrows(fn () => $routes->url('api.v1.items.show'), InvalidArgumentException::class);
        testThrows(fn () => $routes->url('missing'), InvalidArgumentException::class);
        testThrows(fn () => $routes->get('/x', [RouterTestController::class, 'html'])->name('api.v1.items.show'), LogicException::class);
    },
    'rejects actions that do not exist or are not public' => static function (): void {
        $routes = new Routes();
        testThrows(fn () => $routes->get('/a', [RouterTestController::class, 'missing']), InvalidArgumentException::class);
        testThrows(fn () => $routes->get('/a', [RouterTestController::class, 'hidden']), InvalidArgumentException::class);
        testThrows(fn () => $routes->get('/a', ['RouterTestController::show']), InvalidArgumentException::class);
    },
    'normalizes request paths without query strings' => static function (): void {
        testSame('/foo/7', (new Request('GET', '/foo/7?debug=1'))->path());
    },
    'supports form method overrides' => static function (): void {
        $request = buildRequestFromGlobals(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/foo/7'], [], ['_method' => 'DELETE']);
        testSame('DELETE', $request->method);
        testSame([], $request->body);
    },
    'rejects invalid json and non-object json bodies' => static function (): void {
        $decode = static function (string $body): void {
            $oldServer = $_SERVER;
            try {
                $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => 'application/json'];
                Request::fromGlobals($body);
            } finally {
                $_SERVER = $oldServer;
            }
        };
        testThrows(fn () => $decode('{'), HttpException::class);
        testThrows(fn () => $decode('"scalar"'), HttpException::class);
        testThrows(fn () => $decode('[1, 2]'), HttpException::class);
    },
];
