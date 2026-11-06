# structural-php

`structural-php` is a small PHP framework built from scratch to study how framework internals work. Each part is short enough to read in one sitting and has its own walkthrough in [`docs/`](docs/):

| Part | Code | Walkthrough |
| --- | --- | --- |
| Dependency injection container | `system/Container.php` | [docs/container.md](docs/container.md) |
| Routing and middleware pipeline | `system/Routing/`, `system/Middleware/`, `system/Kernel.php` | [docs/middleware.md](docs/middleware.md) |
| Template engine | `system/View/` | [docs/templates.md](docs/templates.md) |
| Migrations | `system/Database/`, `bin/migrate` | [docs/migrations.md](docs/migrations.md) |
| Query builder | `system/QueryBuilder.php` | [Query Builder](#query-builder) below |

There are no runtime dependencies. Composer is used only for PSR-4 autoloading; everything else is plain PHP, PDO and MySQL.

If you want a framework that shows how to build an application (sign-up, sessions, CSRF, a board), see [php-mvc](https://github.com/jwjp/php-mvc). This project focuses on the machinery underneath.

## Requirements

- PHP 8.2 or newer
- Composer
- MySQL 8 or MariaDB with PDO MySQL enabled
- PHP extensions: `pdo`, `pdo_mysql`, `ctype`

## Install

```bash
composer install
cp .ini.example .ini
```

Edit `.ini` for local development, or provide the same values with environment variables:

```bash
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=structural_php
DB_USERNAME=structural_php
DB_PASSWORD=change-me
```

Create the database, then run the migrations:

```bash
composer migrate -- migrate
```

Start the development server and open `http://127.0.0.1:8000/`:

```bash
composer serve
```

## How a request flows

```text
public/index.php          Request::fromGlobals()
  -> App\Application      builds the container, the route table and the global middleware
  -> Kernel::handle()
       SecurityHeaders    global middleware, outermost first
       HandleErrors
       Routes::match()    404 / 405 / OPTIONS
       ReadOnlyMode       route middleware (write routes only)
       SampleController   built by the container; the action's arguments are injected
  -> Response::send()
```

`src/Application.php` is the one place where services are registered, and `src/routes.php` is the route table:

```php
$routes->get('/foo/{fooId}', [SampleController::class, 'readFooById'])->name('foo.show');

$routes->group('', function (Routes $routes): void {
    $routes->post('/foo', [SampleController::class, 'createFooWithQB'])->name('foo.store');
}, middleware: [ReadOnlyMode::class]);
```

Controllers are plain classes. Constructor dependencies are injected by type, route parameters arrive as arguments with the same name, and the `Request` is injected when an action asks for it:

```php
class SampleController {
    public function __construct(private readonly SampleService $service) {
    }

    public function readFooById(string $fooId): false|array {
        return $this->service->readFooById($fooId);
    }

    public function createFooWithQB(Request $request): false|int|array|string {
        return $this->service->createFooWithQB($request->input()['message'] ?? null);
    }
}
```

An action returns a `Response`, or any other value, which is encoded as JSON. `HttpException` messages are shown to clients; any other exception is logged and becomes a generic 500.

## Configuration

| Setting | Meaning |
| --- | --- |
| `[DATABASE]` `HOST`, `PORT`, `DBNAME`, `USERNAME`, `PASSWORD` | Connection settings. `DB_*` environment variables override them. |
| `[APP]` `READ_ONLY` | `"true"` makes write routes answer `503`. It is an example of route middleware. |

Compiled templates are cached in `.runtime/views/`, which is ignored by Git. The web server needs write access to it.

## Sample routes

| Method | Path | Name | Action |
| --- | --- | --- | --- |
| `GET` | `/`, `/sample/view` | `home`, `sample.view` | Render the sample page |
| `GET` | `/foo` | `foo.index` | List active foo rows |
| `POST` | `/foo` | `foo.store` | Create a foo row |
| `GET` | `/foo/{fooId}` | `foo.show` | Read one foo row |
| `PUT` | `/foo/{fooId}` | `foo.update` | Update one foo row |
| `DELETE` | `/foo/{fooId}` | `foo.destroy` | Soft-delete one foo row |
| `GET` | `/bar` | `bar.index` | List active bar rows |
| `POST` | `/bar` | `bar.store` | Create a bar row for an active foo |
| `GET` | `/bar/{barId}` | `bar.show` | Read one bar row |
| `PUT` | `/bar/{barId}` | `bar.update` | Update one bar row |
| `DELETE` | `/bar/{barId}` | `bar.destroy` | Soft-delete one bar row |
| `GET` | `/foobar/{fooId}` | `foobar.show` | Read active bars for an active foo |
| `POST` | `/foobar` | `foobar.store` | Create related foo and bar rows in one transaction |

JSON and `application/x-www-form-urlencoded` request bodies are supported. `POST` requests with `_method=PUT`, `_method=PATCH` or `_method=DELETE` are accepted for HTML forms.

## Query Builder

`App\system\QueryBuilder` supports:

- `select`, `insert`, `upsert`, `update`, `delete`
- `join`, `where`, `having`, `groupBy`, `orderBy`, `limit`, `offset`
- prepared statement binding
- basic value options such as `PASSWORD`, `NUMBER`, `ALPHABET`, `PHONE` and `EMAIL`
- `transaction(callable $callback)` with rollback on failure
- `debug()` for inspecting SQL and parameters without executing the query

Identifier and sort inputs are validated before SQL is generated. Write queries require a `where` condition.

## Tests

```bash
composer lint
composer test
```

The MySQL integration tests run the migrations, roll them back and send requests through the kernel. They drop and recreate tables, so use a disposable database:

```bash
TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=structural_php_test;charset=utf8mb4' \
TEST_MYSQL_USER=root \
TEST_MYSQL_PASSWORD= \
composer test:mysql
```

GitHub Actions runs all three on PHP 8.2 and 8.3 with a MySQL 8 service.

## Upgrading from the lifecycle router

Earlier versions used `RouterLifecycle` with fixed `middleware()`, `guard()`, `interceptor()`, `pipe()` and `filter()` hooks, a `Controller` base class and PHP views under `public/`.

- Declare routes in `src/routes.php` with `$routes->get()` and friends instead of `exec()` inside `get()` / `post()` methods.
- Move hook code into middleware classes. `filter()` became `HandleErrors`.
- Controllers no longer extend `Controller`. Take dependencies in the constructor and read route parameters and the request from action arguments.
- Views live in `views/*.tpl` and use the template syntax. `public/` now holds only the front controller and static assets.
- `database/schema.sql` became the first migration. On a database created from the old schema, record it as applied instead of running it:
  `INSERT INTO migrations (name, batch) VALUES ('2025_08_18_000000_create_foo_and_bar_tables', 1);` after running `composer migrate -- status` once to create the table.

## Notes

This is a learning framework, not a production one. The parts are deliberately small; each walkthrough ends with what a production implementation adds.
