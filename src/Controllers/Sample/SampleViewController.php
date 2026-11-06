<?php

namespace App\Controllers\Sample;

use App\Middleware\ReadOnlyMode;
use App\system\Response;
use App\system\Routing\Routes;
use App\system\View\View;

class SampleViewController {
    /**
     * Method injection: the container passes the View, the route table and the
     * ReadOnlyMode middleware (to read its setting) as arguments.
     */
    public function index(View $view, Routes $routes, ReadOnlyMode $readOnlyMode): Response {
        $endpoints = [];
        foreach ($routes->all() as $route) {
            if (str_starts_with($route->path, '/foo') || str_starts_with($route->path, '/bar')) {
                $endpoints[] = ['method' => $route->method, 'path' => $route->path, 'name' => $route->getName()];
            }
        }

        return $view->render('sample/index', [
            'title' => 'Sample',
            'content' => 'Sample Content',
            'endpoints' => $endpoints,
            'readOnly' => $readOnlyMode->enabled(),
        ]);
    }
}
