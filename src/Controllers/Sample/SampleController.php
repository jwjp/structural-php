<?php

namespace App\Controllers\Sample;

use App\Services\Sample\SampleService;
use App\system\Request;

/**
 * JSON actions for the foo/bar sample.
 *
 * The service arrives through constructor injection. Route parameters such as
 * {fooId} arrive as arguments with the same name, and the Request is injected
 * by type when an action needs the request body.
 */
class SampleController {
    public function __construct(private readonly SampleService $service) {
    }

    public function readFooAll(): array|false {
        return $this->service->readFooAll();
    }

    public function readFooById(string $fooId): false|array {
        return $this->service->readFooById($fooId);
    }

    public function updateFooById(string $fooId, Request $request): false|int {
        return $this->service->updateFooById($request->input()['message'] ?? null, $fooId);
    }

    public function createBarWithFooId(Request $request): false|int {
        $input = $request->input();
        return $this->service->createBarWithFooId($input['comment'] ?? null, $input['fooId'] ?? null);
    }

    public function readBarById(string $barId): false|array {
        return $this->service->readBarById($barId);
    }

    public function readBarAll(): false|array {
        return $this->service->readBarAll();
    }

    public function updateBarById(string $barId, Request $request): false|int {
        return $this->service->updateBarById($request->input()['comment'] ?? null, $barId);
    }

    public function deleteBarById(string $barId): false|int {
        return $this->service->deleteBarById($barId);
    }

    public function readFooBarByFooIdWithQB(string $fooId): false|array {
        return $this->service->readFooBarByFooIdWithQB($fooId);
    }

    public function createFooWithQB(Request $request): false|int|array|string {
        return $this->service->createFooWithQB($request->input()['message'] ?? null);
    }

    public function createFooBarWithQB(Request $request): bool {
        $input = $request->input();
        return $this->service->createFooBarWithQB($input['fooMessage'] ?? null, $input['barComment'] ?? null);
    }

    public function deleteFooByIdWithQB(string $fooId): false|int|array|string {
        return $this->service->deleteFooByIdWithQB($fooId);
    }
}
