# Routing and the middleware pipeline

Code: [`system/Routing/`](../system/Routing/), [`system/Middleware/`](../system/Middleware/), [`system/Kernel.php`](../system/Kernel.php) · Tests: [`tests/router.php`](../tests/router.php)

## From fixed hooks to a pipeline

The previous router ran the same five hooks for every request, in a fixed order:

```text
middleware() -> guard() -> interceptor('before') -> pipe() -> controller -> interceptor('after') -> filter()
```

Every concern had to fit one of those slots, every hook ran for every route, and code that needed to act both before and after the controller (timing, headers) was split across two methods.

A pipeline replaces the slots with a list. Each middleware receives the request and a `$next` callable for "everything after me":

```php
class SecurityHeaders implements Middleware {
    public function process(Request $request, callable $next): Response {
        $response = $next($request);                     // run the rest of the application
        return $response->withHeader('X-Frame-Options', 'DENY');
    }
}
```

A middleware can work before `$next` (check the request and stop early by returning or throwing), after it (change the response), or both.

## How the onion is built

`Pipeline::process()` wraps the handler from the inside out. For `[A, B]` and handler `H`:

```php
$next = H;
$next = fn ($request) => B->process($request, $next);   // B wraps H
$next = fn ($request) => A->process($request, $next);   // A wraps B
return $next($request);
```

So the call order is `A before -> B before -> H -> B after -> A after`. The test *middleware wraps like an onion and can stop early* checks exactly this order.

## Where middleware is attached

| Level | Declared in | Example |
| --- | --- | --- |
| Global | `Application::create()` | `SecurityHeaders`, `HandleErrors` |
| Group | `$routes->group(..., middleware: [...])` | `ReadOnlyMode` on all write routes |
| Route | `->middleware(...)` | anything specific to one route |

The kernel runs them in that order: global, then group, then route. Global middleware runs even for 404 responses because routing happens inside it.

Order matters. `SecurityHeaders` is listed before `HandleErrors`, so an exception is first turned into an error response by `HandleErrors`, and that response still passes back through `SecurityHeaders`. Swap them and error responses would lose the security headers.

## Routes

```php
$routes->get('/foo/{fooId}', [SampleController::class, 'readFooById'])->name('foo.show');

$routes->group('/api', function (Routes $routes): void {
    $routes->get('/items/{id}', [ItemController::class, 'show'])->name('items.show');  // GET /api/items/{id}, name api.items.show
}, middleware: [RequireToken::class], name: 'api.');

$routes->url('api.items.show', ['id' => 'a/b', 'page' => 2]);   // /api/items/a%2Fb?page=2
```

- Each route compiles its path to a regular expression: `{fooId}` becomes the named group `(?P<fooId>[^/]+)`.
- Routes are tried in declaration order. If the path matches but the method does not, the response is `405` with an `Allow` header; `OPTIONS` answers with the same list.
- Actions are checked when they are declared, so a typo fails at startup rather than on the first request to that URL.
- `url()` encodes values, fails when a parameter is missing, and turns extra parameters into the query string.

## What a production router adds

Parameter constraints (`{id:\d+}`), optional segments, a compiled single regex or tree for fast matching, cached route tables, domain and scheme routing, and the PSR-7 / PSR-15 interfaces so middleware can be shared between frameworks.
