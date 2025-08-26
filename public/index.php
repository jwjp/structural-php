<?php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);

// PHP 에러를 exception 핸들러에서 처리
set_error_handler(/** @throws ErrorException */function($errNo, $errStr, $errFile, $errLine) {
    throw new ErrorException($errStr, $errNo, 0, $errFile, $errLine);
});

set_exception_handler(function(Throwable $exception) {
    header('Content-type: application/json');
    http_response_code($exception->getCode());

    exit(json_encode([
        'message' => $exception->getMessage(),
        'code' => $exception->getCode(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ], JSON_UNESCAPED_UNICODE));
});

// Composer 오토로더 불러오기 (경로 기준은 현재 파일 위치)
require __DIR__ . '/../vendor/autoload.php';

// 라우터 실행
new App\Router();

// TODO: composer dump-autoload 명령어 실행 (composer.json 확인)