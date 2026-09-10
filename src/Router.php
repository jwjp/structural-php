<?php

namespace App;

use App\Controllers\Sample\SampleController;
use App\Controllers\Sample\SampleViewController;
use App\system\Request;
use App\system\RouterLifecycle;

class Router extends RouterLifecycle {
    public function __construct(?Request $request = null, ?array $ini = null, bool $send = true) {
        parent::__construct($request, $ini, $send);
    }

    protected function post(): static {
        $this->exec('/foo', SampleController::class, 'createFooWithQB');
        $this->exec('/bar', SampleController::class, 'createBarWithFooId');
        $this->exec('/foobar', SampleController::class, 'createFooBarWithQB');

        return $this;
    }

    protected function get(): static {
        $this->exec('/', SampleController::class, 'index');
        $this->exec('/foo', SampleController::class, 'readFooAll');
        $this->exec('/foo/{fooId}', SampleController::class, 'readFooById');
        $this->exec('/bar', SampleController::class, 'readBarAll');
        $this->exec('/bar/{barId}', SampleController::class, 'readBarById');
        $this->exec('/foobar/{fooId}', SampleController::class, 'readFooBarByFooIdWithQB');

        $this->exec('/sample/view', SampleViewController::class, 'index');

        return $this;
    }

    protected function patch(): static {
        return $this;
    }

    protected function put(): static {
        $this->exec('/foo/{fooId}', SampleController::class, 'updateFooById');
        $this->exec('/bar/{barId}', SampleController::class, 'updateBarById');

        return $this;
    }

    protected function delete(): static {
        $this->exec('/foo/{fooId}', SampleController::class, 'deleteFooByIdWithQB');
        $this->exec('/bar/{barId}', SampleController::class, 'deleteBarById');

        return $this;
    }
}
