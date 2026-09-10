<?php

require __DIR__ . '/bootstrap.php';

$dsn = getenv('TEST_MYSQL_DSN');
if ($dsn === false || $dsn === '') {
    echo "SKIP mysql: set TEST_MYSQL_DSN, TEST_MYSQL_USER and TEST_MYSQL_PASSWORD to run integration tests.\n";
    exit(0);
}

$user = getenv('TEST_MYSQL_USER') ?: '';
$password = getenv('TEST_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tests = [
    'schema imports and sample transaction writes related rows' => static function () use ($pdo): void {
        $pdo->exec('DROP TABLE IF EXISTS bar');
        $pdo->exec('DROP TABLE IF EXISTS foo');
        $pdo->exec(file_get_contents(dirname(__DIR__) . '/database/schema.sql'));

        $model = new App\Models\Sample\SampleModel($pdo);
        testTrue($model->createFooBarWithQB('foo message', 'bar comment'));
        testSame([['comment' => 'bar comment']], array_map(
            fn (array $row): array => ['comment' => $row['comment']],
            $model->readFooBarByFooIdWithQB(1)
        ));
    },
];

exit(testRun($tests));
