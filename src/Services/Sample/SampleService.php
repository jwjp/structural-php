<?php

namespace App\Services\Sample;

use App\Models\Sample\SampleModel;
use Exception;

class SampleService {
    public function __construct() {
    }

    /**
     * @param string $message
     * @return false|int
     * @throws Exception
     */
    public function createFoo(string $message): false|int {
        if (empty($message)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $message = htmlspecialchars($message, ENT_QUOTES);
        if (strlen($message) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->createFoo($message);
    }

    /**
     * @param int $id
     * @return false|array
     * @throws Exception
     */
    public function readFooById(int $id): false|array {
        if (empty($id)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->readFooById($id);
    }

    /**
     * @return false|array
     */
    public function readFooAll(): false|array {
        $sampleModel = new SampleModel();
        return $sampleModel->readFooAll();
    }

    /**
     * @param string $message
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function updateFooById(string $message, int $id): false|int {
        if (empty($id) || empty($message)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $message = htmlspecialchars($message, ENT_QUOTES);
        if (strlen($message) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->updateFooById($message, $id);
    }

    /**
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function deleteFooById(int $id): false|int {
        if (empty($id)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->deleteFooById($id);
    }

    /**
     * @param string $comment
     * @param int $fooId
     * @return false|int
     * @throws Exception
     */
    public function createBarWithFooId(string $comment, int $fooId): false|int {
        if (empty($fooId) || empty($comment)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $comment = htmlspecialchars($comment, ENT_QUOTES);
        if (strlen($comment) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->createBarWithFooId($comment, $fooId);
    }

    /**
     * @param int $id
     * @return false|array
     * @throws Exception
     */
    public function readBarById(int $id): false|array {
        if (empty($id)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->readBarById($id);
    }

    /**
     * @return false|array
     */
    public function readBarAll(): false|array {
        $sampleModel = new SampleModel();
        return $sampleModel->readBarAll();
    }

    /**
     * @param string $comment
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function updateBarById(string $comment, int $id): false|int {
        if (empty($id) || empty($comment)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $comment = htmlspecialchars($comment, ENT_QUOTES);
        if (strlen($comment) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->updateBarById($comment, $id);
    }

    /**
     * @param int $id
     * @return false|int
     * @throws Exception
     */
    public function deleteBarById(int $id): false|int {
        if (empty($id)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->deleteBarById($id);
    }

    /**
     * @param int $fooId
     * @return array
     * @throws Exception
     */
    public function readFooBarByFooIdWithQB(int $fooId): array {
        if (empty($fooId)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->readFooBarByFooIdWithQB($fooId);
    }

    /**
     * @param string $message
     * @return false|array|int|string
     * @throws Exception
     */
    public function createFooWithQB(string $message): false|array|int|string {
        if (empty($message)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $convertedMessage = htmlspecialchars($message, ENT_QUOTES);
        if (strlen($convertedMessage) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->createFooWithQB($convertedMessage);
    }

    /**
     * @param string $fooMessage
     * @param string $barComment
     * @return bool
     * @throws Exception
     */
    public function createFooBarWithQB(string $fooMessage, string $barComment): bool {
        if (empty($fooMessage) || empty($barComment)) {
            throw new Exception('입력 값이 올바르지 않습니다.', 400);
        }

        $convertedMessage = htmlspecialchars($fooMessage, ENT_QUOTES);
        $convertedComment = htmlspecialchars($barComment, ENT_QUOTES);
        if (strlen($convertedMessage) >= 150 || strlen($convertedComment) >= 150) {
            throw new Exception('메시지의 제한 길이 150자를 초과하였습니다.', 400);
        }

        $sampleModel = new SampleModel();
        return $sampleModel->createFooBarWithQB($convertedMessage, $convertedComment);
    }

    /**
     * @param string $fooId
     * @return false|array|int|string
     * @throws Exception
     */
    public function deleteFooByIdWithQB(string $fooId): false|array|int|string {
        $sampleModel = new SampleModel();
        return $sampleModel->deleteFooByIdWithQB($fooId);
    }
}