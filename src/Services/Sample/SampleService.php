<?php

namespace App\Services\Sample;

use App\Models\Sample\SampleModel;
use App\system\HttpException;

class SampleService {
    public function __construct(private ?SampleModel $sampleModel = null) {
    }

    public function createFoo(mixed $message): false|int {
        $message = $this->text($message, 'message');
        return $this->model()->createFoo($message);
    }

    public function readFooById(mixed $id): false|array {
        $id = $this->positiveId($id, 'fooId');
        return $this->model()->readFooById($id);
    }

    public function readFooAll(): false|array {
        return $this->model()->readFooAll();
    }

    public function updateFooById(mixed $message, mixed $id): false|int {
        $id = $this->positiveId($id, 'fooId');
        $message = $this->text($message, 'message');
        return $this->model()->updateFooById($message, $id);
    }

    public function deleteFooById(mixed $id): false|int {
        $id = $this->positiveId($id, 'fooId');
        return $this->model()->deleteFooById($id);
    }

    public function createBarWithFooId(mixed $comment, mixed $fooId): false|int {
        $fooId = $this->positiveId($fooId, 'fooId');
        $comment = $this->text($comment, 'comment');
        return $this->model()->createBarWithFooId($comment, $fooId);
    }

    public function readBarById(mixed $id): false|array {
        $id = $this->positiveId($id, 'barId');
        return $this->model()->readBarById($id);
    }

    public function readBarAll(): false|array {
        return $this->model()->readBarAll();
    }

    public function updateBarById(mixed $comment, mixed $id): false|int {
        $id = $this->positiveId($id, 'barId');
        $comment = $this->text($comment, 'comment');
        return $this->model()->updateBarById($comment, $id);
    }

    public function deleteBarById(mixed $id): false|int {
        $id = $this->positiveId($id, 'barId');
        return $this->model()->deleteBarById($id);
    }

    public function readFooBarByFooIdWithQB(mixed $fooId): false|array {
        $fooId = $this->positiveId($fooId, 'fooId');
        return $this->model()->readFooBarByFooIdWithQB($fooId);
    }

    public function createFooWithQB(mixed $message): false|array|int|string {
        $message = $this->text($message, 'message');
        return $this->model()->createFooWithQB($message);
    }

    public function createFooBarWithQB(mixed $fooMessage, mixed $barComment): bool {
        $fooMessage = $this->text($fooMessage, 'fooMessage');
        $barComment = $this->text($barComment, 'barComment');
        return $this->model()->createFooBarWithQB($fooMessage, $barComment);
    }

    public function deleteFooByIdWithQB(mixed $fooId): false|array|int|string {
        $fooId = $this->positiveId($fooId, 'fooId');
        return $this->model()->deleteFooByIdWithQB($fooId);
    }

    private function model(): SampleModel {
        // Validation completes before the first database connection is opened.
        return $this->sampleModel ??= new SampleModel();
    }

    private function positiveId(mixed $value, string $field): int {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/^[1-9][0-9]*$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new HttpException(400, "$field must be a positive integer.");
        }

        return (int) $value;
    }

    private function text(mixed $value, string $field): string {
        if (!is_string($value) || !preg_match('//u', $value) || !preg_match('/[^\s\p{Z}]/u', $value)) {
            throw new HttpException(400, "$field must be a non-empty UTF-8 string.");
        }

        // Count Unicode code points without requiring the mbstring extension.
        if (preg_match_all('/./us', $value) > 150) {
            throw new HttpException(400, "$field must not exceed 150 characters.");
        }

        return $value;
    }
}
