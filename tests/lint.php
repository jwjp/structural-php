<?php

$root = dirname(__DIR__);
$files = [];
foreach (['system', 'src', 'public', 'tests', 'database'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        "{$root}/{$directory}", FilesystemIterator::SKIP_DOTS
    ));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
$files[] = "{$root}/bin/migrate";
sort($files);
foreach ($files as $file) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-l', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to launch PHP lint.');
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        fwrite(STDERR, $output);
        exit(1);
    }
}
echo count($files) . " PHP files passed syntax checks.\n";
