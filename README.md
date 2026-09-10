# structural-php

`structural-php` is a small dependency-free PHP MVC project for studying how framework internals fit together. It keeps the runtime stack intentionally thin: Composer is used for PSR-4 autoloading, and the application code uses plain PHP, PDO and MySQL.

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

Create the database, then import the schema:

```bash
mysql -u structural_php -p structural_php < database/schema.sql
```

Start the development server:

```bash
composer serve
```

Open `http://127.0.0.1:8000/sample/view`.

## Architecture

Requests enter through `public/index.php`, then move through `App\Router`, `App\system\RouterLifecycle`, controllers, services and models.

```text
public/index.php
  -> App\Router
  -> Controller
  -> Service
  -> Model
  -> App\system\Database / QueryBuilder
```

The router now exposes only the routes declared in `src/Router.php`. The old convention-based fallback that mapped URL paths directly to controller classes was removed because it could expose methods unintentionally. The lifecycle still keeps extension points for middleware, guards, interceptors, pipes and filters, but those hooks are intentionally small until a real use case appears.

Controllers return `Response` objects or plain values. Plain values are encoded as JSON. Views are rendered from files under `public/` and returned as HTML without using globals or terminating the process from inside the controller.

## Sample Routes

| Method | Path | Action |
| --- | --- | --- |
| `GET` | `/` | Render the sample page |
| `GET` | `/sample/view` | Render the sample page |
| `GET` | `/foo` | List active foo rows |
| `POST` | `/foo` | Create a foo row |
| `GET` | `/foo/{fooId}` | Read one foo row |
| `PUT` | `/foo/{fooId}` | Update one foo row |
| `DELETE` | `/foo/{fooId}` | Soft-delete one foo row |
| `GET` | `/bar` | List active bar rows |
| `POST` | `/bar` | Create a bar row for an active foo |
| `GET` | `/bar/{barId}` | Read one bar row |
| `PUT` | `/bar/{barId}` | Update one bar row |
| `DELETE` | `/bar/{barId}` | Soft-delete one bar row |
| `GET` | `/foobar/{fooId}` | Read active bars for an active foo |
| `POST` | `/foobar` | Create related foo and bar rows in one transaction |

JSON and `application/x-www-form-urlencoded` request bodies are supported. `POST` requests with `_method=PUT`, `_method=PATCH` or `_method=DELETE` are still accepted for form compatibility.

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

Run the default test suite:

```bash
composer test
composer lint
```

Run MySQL integration tests when a disposable database is available:

```bash
TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=structural_php_test;charset=utf8mb4' \
TEST_MYSQL_USER=root \
TEST_MYSQL_PASSWORD= \
composer test:mysql
```

The MySQL test drops and recreates the `foo` and `bar` tables in the selected database. Use a disposable database only.

## Notes

This project is still a learning framework rather than a production web framework. The extension points are deliberately simple, and the sample domain exists to exercise routing, validation, transactions and soft deletion.
