# Dependency injection container

Code: [`system/Container.php`](../system/Container.php) · Tests: [`tests/container.php`](../tests/container.php)

## The problem

Before the container, the sample controller built its own service, and the service built its own model, which read `.ini` and opened its own PDO connection:

```php
protected function service(): SampleService {
    return new SampleService();          // which calls new SampleModel(), which calls new PDO(...)
}
```

Every class decided where its collaborators came from. Tests had to subclass things to swap a model, and two models in one request opened two connections.

With injection, a class only *declares* what it needs:

```php
class SampleController {
    public function __construct(private readonly SampleService $service) {}
}
```

Someone else has to build the object graph. That someone is the container.

## Registering services

```php
$container = new Container();
$container->instance(Ini::class, new Ini());                         // an object you already have
$container->singleton(PDO::class, fn (Container $c) => Database::connect(...)); // built once, then shared
$container->bind('report', fn (Container $c) => new Report(...));    // built on every get()
```

Most classes need no registration at all. `src/Application.php` registers only the things the container cannot guess: configuration, the database connection, the template engine and the route table.

## Autowiring

`get(SampleController::class)` with no registration for it:

1. `ReflectionClass` reads the constructor: one parameter, `SampleService $service`.
2. `SampleService` is a class, so the container calls `get(SampleService::class)` recursively.
3. That constructor needs `SampleModel`, whose parent constructor is `__construct(?PDO $pdo = null)`.
4. `PDO` is registered as a singleton, so the shared connection is injected.

Each parameter is resolved by these rules, in order (`Container::argument()`):

| Rule | Example |
| --- | --- |
| A value passed by name wins | `make(Foo::class, ['limit' => 5])` |
| A registered class type is injected | `?PDO $pdo = null` gets the shared PDO |
| Otherwise a default value is kept | `int $limit = 10`, `?Logger $logger = null` |
| Otherwise a concrete class is built | `SampleService $service` |
| Otherwise `null` if allowed | `?Clock $clock` |
| Otherwise it fails with the parameter name | `string $name` |

The third rule is a deliberate choice: an optional dependency is only filled when you registered it. The container does not guess.

## Method injection

`call()` applies the same rules to a function or method. The kernel uses it to call controller actions, passing route parameters by name:

```php
// Route /foo/{fooId}
public function updateFooById(string $fooId, Request $request): false|int
//                            ^ from the URL  ^ from the container
```

## Errors you will see

- `Cannot resolve parameter $clock of Report::__construct(): Clock is an interface or abstract class. Bind a concrete class to it.`
- `Cannot resolve parameter $name of Greeting::__construct(). Give it a class type, a default value, or pass it by name.`
- `Circular dependency: A -> B -> A` — the container keeps a stack of classes being built (`guard()`), so a cycle fails immediately instead of overflowing the stack.

## What a production container adds

Interface-to-class aliases and tags, contextual bindings ("give `Mailer` to X but `FakeMailer` to Y"), compiling the graph ahead of time instead of using Reflection on every request, lazy proxies, scopes beyond "shared" and "new", and the PSR-11 `ContainerInterface`.
