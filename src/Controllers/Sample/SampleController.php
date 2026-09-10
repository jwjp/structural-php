<?php

namespace App\Controllers\Sample;

use App\Services\Sample\SampleService;
use App\system\Controller;
use App\system\Response;

class SampleController extends Controller {
    public function index(): Response {
        return $this->view('sample/index', ['title' => 'Sample', 'content' => 'Sample Content']);
    }

    public function createFoo(): false|int {
        return $this->service()->createFoo($this->var['message'] ?? null);
    }

    public function readFooById(): false|array {
        return $this->service()->readFooById($this->param['fooId'] ?? null);
    }

    public function readFooAll(): array|false {
        return $this->service()->readFooAll();
    }

    public function updateFooById(): false|int {
        return $this->service()->updateFooById($this->var['message'] ?? null, $this->param['fooId'] ?? null);
    }

    public function deleteFooById(): false|int {
        return $this->service()->deleteFooById($this->param['fooId'] ?? null);
    }

    public function createBarWithFooId(): false|int {
        return $this->service()->createBarWithFooId($this->var['comment'] ?? null, $this->var['fooId'] ?? null);
    }

    public function readBarById(): false|array {
        return $this->service()->readBarById($this->param['barId'] ?? null);
    }

    public function readBarAll(): false|array {
        return $this->service()->readBarAll();
    }

    public function updateBarById(): false|int {
        return $this->service()->updateBarById($this->var['comment'] ?? null, $this->param['barId'] ?? null);
    }

    public function deleteBarById(): false|int {
        return $this->service()->deleteBarById($this->param['barId'] ?? null);
    }

    public function readFooBarByFooIdWithQB(): false|array {
        return $this->service()->readFooBarByFooIdWithQB($this->param['fooId'] ?? null);
    }

    public function createFooWithQB(): false|int|array|string {
        return $this->service()->createFooWithQB($this->var['message'] ?? null);
    }

    public function createFooBarWithQB(): bool {
        return $this->service()->createFooBarWithQB($this->var['fooMessage'] ?? null, $this->var['barComment'] ?? null);
    }

    public function deleteFooByIdWithQB(): false|int|array|string {
        return $this->service()->deleteFooByIdWithQB($this->param['fooId'] ?? null);
    }

    protected function service(): SampleService {
        return new SampleService();
    }
}
