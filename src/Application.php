<?php

namespace App;

use App\Middleware\SecurityHeaders;
use App\system\Container;
use App\system\Database;
use App\system\Ini;
use App\system\Kernel;
use App\system\Middleware\HandleErrors;
use App\system\Routing\Routes;
use App\system\View\View;
use PDO;

/**
 * Wires the application together: services in the container, the route
 * table and the global middleware. public/index.php and the tests both
 * start here.
 */
final class Application {
    public static function create(?Ini $ini = null): Kernel {
        $root = dirname(__DIR__);
        $container = new Container();

        $container->instance(Ini::class, $ini ?? new Ini());

        // One connection per request, opened the first time something needs it.
        $container->singleton(PDO::class, static fn (Container $c): PDO => Database::connect(
            $c->get(Ini::class)->getAll()['DATABASE'] ?? []
        ));

        $container->singleton(View::class, static fn (): View => new View($root . '/views', $root . '/.runtime/views'));

        $routes = new Routes();
        (require __DIR__ . '/routes.php')($routes);
        $container->instance(Routes::class, $routes);

        // Outermost first. HandleErrors sits inside SecurityHeaders so that an
        // exception becomes an error response before SecurityHeaders adds its
        // headers; error pages get the same headers as normal pages.
        return new Kernel($container, $routes, [
            SecurityHeaders::class,
            HandleErrors::class,
        ]);
    }
}
