<?php

namespace App\Controllers\Sample;

use App\core\Controller;
use App\Models\Sample\SampleModel;
use App\Services\Sample\SampleService;
use Exception;
use JetBrains\PhpStorm\NoReturn;

class SampleController extends Controller {
    /**
     * @return void
     */
    #[NoReturn] public function index(): void {
        $var = ['title' => 'title', 'content' => 'content'];
        $this->view('sample/index', $var);
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function createFoo(): false|int {
        $sampleService = new SampleService();
        return $sampleService->createFoo($this->var['message']);
    }

    /**
     * @return false|array
     * @throws Exception
     */
    public function readFooById(): false|array {
        $sampleService = new SampleService();
        return $sampleService->readFooById($this->param['fooId']);
    }

    /**
     * @return array|false
     */
    public function readFooAll(): array|false {
        $sampleService = new SampleService();
        return $sampleService->readFooAll();
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function updateFooById(): false|int {
        $sampleService = new SampleService();
        return $sampleService->updateFooById($this->var['message'], $this->param['id']);
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function deleteFooById(): false|int {
        $sampleService = new SampleService();
        return $sampleService->deleteFooById($this->param['id']);
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function createBarWithFooId(): false|int {
        $sampleService = new SampleService();
        return $sampleService->createBarWithFooId($this->var['comment'], $this->var['fooId']);
    }

    /**
     * @return false|array
     * @throws Exception
     */
    public function readBarById(): false|array {
        $sampleService = new SampleService();
        return $sampleService->readBarById($this->param['id']);
    }

    /**
     * @return false|array
     */
    public function readBarAll(): false|array {
        $sampleService = new SampleService();
        return $sampleService->readBarAll();
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function updateBarById(): false|int {
        $sampleService = new SampleService();
        return $sampleService->updateBarById($this->var['comment'], $this->param['id']);
    }

    /**
     * @return false|int
     * @throws Exception
     */
    public function deleteBarById(): false|int {
        $sampleService = new SampleService();
        return $sampleService->deleteBarById($this->param['id']);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function readFooBarByFooIdWithQB(): array {
        $sampleService = new SampleService();
        return $sampleService->readFooBarByFooIdWithQB($this->param['fooId']);
    }

    /**
     * @return false|int|array|string
     * @throws Exception
     */
    public function createFooWithQB(): false|int|array|string {
        $sampleService = new SampleService();
        return $sampleService->createFooWithQB($this->var['message']);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function createFooBarWithQB(): bool {
        $sampleService = new SampleService();
        return $sampleService->createFooBarWithQB($this->var['fooMessage'], $this->var['barComment']);
    }

    /**
     * @return false|int|array|string
     * @throws Exception
     */
    public function deleteFooByIdWithQB(): false|int|array|string {
        $sampleService = new SampleService();
        return $sampleService->deleteFooByIdWithQB($this->param['fooId']);
    }
}