<?php

use App\Models\Sample\SampleModel;
use App\Services\Sample\SampleService;
use App\system\HttpException;

class SampleTestModel extends SampleModel {
    public array $calls = [];

    public function __construct() {
    }

    public function createFoo(string $message): false|int {
        $this->calls[] = ['createFoo', $message];
        return 1;
    }

    public function readFooById(int $id): false|array {
        $this->calls[] = ['readFooById', $id];
        return [['id' => $id]];
    }

    public function createFooWithQB(string $message): false|array|int|string {
        $this->calls[] = ['createFooWithQB', $message];
        return '1';
    }

    public function createFooBarWithQB(string $message, string $comment): bool {
        $this->calls[] = ['createFooBarWithQB', $message, $comment];
        return true;
    }
}

return [
    'validates ids before opening a database connection' => static function (): void {
        $service = new SampleService(new SampleTestModel());
        testSame([['id' => 9]], $service->readFooById('9'));
        testThrows(fn () => $service->readFooById('0'), HttpException::class, fn (HttpException $e) => testSame(400, $e->status()));
        testThrows(fn () => $service->readFooById('1.2'), HttpException::class);
    },
    'preserves user text without storing escaped HTML' => static function (): void {
        $model = new SampleTestModel();
        $service = new SampleService($model);
        testSame('1', $service->createFooWithQB('<b>Hello</b>'));
        testSame(['createFooWithQB', '<b>Hello</b>'], $model->calls[0]);
    },
    'rejects empty or overlong text values' => static function (): void {
        $service = new SampleService(new SampleTestModel());
        testThrows(fn () => $service->createFooWithQB('   '), HttpException::class);
        testThrows(fn () => $service->createFooWithQB(str_repeat('a', 151)), HttpException::class);
    },
];
