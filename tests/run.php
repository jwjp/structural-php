<?php

require __DIR__ . '/bootstrap.php';

$tests = [];
foreach (['config', 'router', 'query_builder', 'sample'] as $suite) {
    $cases = require __DIR__ . "/{$suite}.php";
    foreach ($cases as $name => $test) {
        $tests["{$suite}: {$name}"] = $test;
    }
}
exit(testRun($tests));
