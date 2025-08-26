<?php

namespace App\core;

use App\Controllers\Sample\SampleController;
use App\Controllers\Sample\SampleViewController;
use Exception;

class Router extends RouterLifecycle {
    public function __construct() {
        parent::__construct();
    }

    /** @throws Exception */
    protected function post(): static {
        // $this->exec('/foo', SampleController::class, 'createFoo');
        $this->exec('/foo', SampleController::class, 'createFooWithQB');
        $this->exec('/bar', SampleController::class, 'createBarWithFooId');
        $this->exec('/foobar', SampleController::class, 'createFooBarWithQB');

        return $this;
    }

    /** @throws Exception */
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

    /** @throws Exception */
    protected function patch(): static {
        return $this;
    }

    /** @throws Exception */
    protected function put(): static {
        $this->exec('/foo/{fooId}', SampleController::class, 'updateFooById');
        $this->exec('/bar/{barId}', SampleController::class, 'updateBarById');

        return $this;
    }

    /** @throws Exception */
    protected function delete(): static {
        // $this->exec('/foo/{fooId}', SampleController::class, 'deleteFooById');
        $this->exec('/foo/{fooId}', SampleController::class, 'deleteFooByIdWithQB');
        $this->exec('/bar/{barId}', SampleController::class, 'deleteBarById');

        return $this;
    }
}