<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function testSame(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(($message === '' ? 'Values differ.' : $message)
            . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function testTrue(bool $condition, string $message = 'Expected true.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function testThrows(callable $callback, string $class, ?callable $inspect = null): Throwable {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            throw new RuntimeException('Expected ' . $class . ', received ' . $exception::class, 0, $exception);
        }
        if ($inspect !== null) {
            $inspect($exception);
        }
        return $exception;
    }
    throw new RuntimeException('Expected ' . $class . ' to be thrown.');
}

function testRun(array $tests): int {
    $failed = 0;
    // Keep CLI output buffered so response tests can exercise HTTP headers.
    ob_start();
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS {$name}\n";
        } catch (Throwable $exception) {
            $failed++;
            echo "FAIL {$name}: {$exception}\n";
        }
    }
    echo count($tests) . " tests, {$failed} failures.\n";
    ob_end_flush();
    return $failed === 0 ? 0 : 1;
}
