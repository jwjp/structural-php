<?php

use App\Controllers\Sample\SampleController;
use App\Controllers\Sample\SampleViewController;
use App\Middleware\ReadOnlyMode;
use App\system\Routing\Routes;

/**
 * The application's routes. Each action is [ControllerClass::class, 'method'];
 * {name} segments are passed to the action as the argument $name.
 */
return static function (Routes $routes): void {
    $routes->get('/', [SampleViewController::class, 'index'])->name('home');
    $routes->get('/sample/view', [SampleViewController::class, 'index'])->name('sample.view');

    // Reads
    $routes->get('/foo', [SampleController::class, 'readFooAll'])->name('foo.index');
    $routes->get('/foo/{fooId}', [SampleController::class, 'readFooById'])->name('foo.show');
    $routes->get('/bar', [SampleController::class, 'readBarAll'])->name('bar.index');
    $routes->get('/bar/{barId}', [SampleController::class, 'readBarById'])->name('bar.show');
    $routes->get('/foobar/{fooId}', [SampleController::class, 'readFooBarByFooIdWithQB'])->name('foobar.show');

    // Writes share route-level middleware through a group without a path prefix.
    $routes->group('', static function (Routes $routes): void {
        $routes->post('/foo', [SampleController::class, 'createFooWithQB'])->name('foo.store');
        $routes->put('/foo/{fooId}', [SampleController::class, 'updateFooById'])->name('foo.update');
        $routes->delete('/foo/{fooId}', [SampleController::class, 'deleteFooByIdWithQB'])->name('foo.destroy');

        $routes->post('/bar', [SampleController::class, 'createBarWithFooId'])->name('bar.store');
        $routes->put('/bar/{barId}', [SampleController::class, 'updateBarById'])->name('bar.update');
        $routes->delete('/bar/{barId}', [SampleController::class, 'deleteBarById'])->name('bar.destroy');

        $routes->post('/foobar', [SampleController::class, 'createFooBarWithQB'])->name('foobar.store');
    }, middleware: [ReadOnlyMode::class]);
};
