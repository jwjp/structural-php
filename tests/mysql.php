<?php

use App\Application;
use App\system\Database\Migrator;
use App\system\Request;

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
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$migrations = dirname(__DIR__) . '/database/migrations';

/** The test database starts empty: the tables and the migration history are removed. */
$reset = static function () use ($pdo): void {
    foreach (['bar', 'foo', 'migrations', 'widgets'] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS {$table}");
    }
};

$tables = static fn (): array => $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$scratch = static function (array $files): string {
    $directory = sys_get_temp_dir() . '/structural-migrations-' . bin2hex(random_bytes(4));
    mkdir($directory, 0775, true);
    register_shutdown_function(static function () use ($directory): void {
        array_map('unlink', glob("{$directory}/*.php") ?: []);
        @rmdir($directory);
    });
    foreach ($files as $name => $source) {
        file_put_contents("{$directory}/{$name}.php", $source);
    }
    return $directory;
};

$tests = [
    'migrate runs pending files once and rollback reverts the batch' => static function () use ($pdo, $migrations, $reset, $tables): void {
        $reset();
        $migrator = new Migrator($pdo, $migrations);
        testSame([['name' => '2025_08_18_000000_create_foo_and_bar_tables', 'batch' => null, 'ran_at' => null]], $migrator->status());
        testSame(['2025_08_18_000000_create_foo_and_bar_tables'], $migrator->migrate());
        testSame([], $migrator->migrate(), 'A second migrate has nothing to do.');
        testSame(1, $migrator->status()[0]['batch']);
        testTrue(in_array('bar', $tables(), true));

        testSame(['2025_08_18_000000_create_foo_and_bar_tables'], $migrator->rollback());
        testSame([], $migrator->rollback());
        testSame(['migrations'], $tables());
    },
    'batches roll back newest first' => static function () use ($pdo, $migrations, $reset, $scratch): void {
        $reset();
        $source = static fn (string $table): string => "<?php return new class implements App\\system\\Database\\Migration {
            public function up(PDO \$pdo): void { \$pdo->exec('CREATE TABLE {$table} (id INT)'); }
            public function down(PDO \$pdo): void { \$pdo->exec('DROP TABLE {$table}'); }
        };";
        $directory = $scratch(['2026_01_01_000000_create_widgets' => $source('widgets')]);
        $migrator = new Migrator($pdo, $directory);
        $migrator->migrate();
        copy("{$migrations}/2025_08_18_000000_create_foo_and_bar_tables.php", "{$directory}/2026_02_01_000000_create_foo_and_bar_tables.php");
        $migrator->migrate();
        testSame([1, 2], array_column($migrator->status(), 'batch'));
        testSame(['2026_02_01_000000_create_foo_and_bar_tables'], $migrator->rollback());
        testSame(['2026_01_01_000000_create_widgets'], $migrator->rollback());
    },
    'a failing migration stops the batch and is not recorded' => static function () use ($pdo, $reset, $scratch): void {
        $reset();
        $directory = $scratch([
            '2026_01_01_000000_ok' => "<?php return new class implements App\\system\\Database\\Migration {
                public function up(PDO \$pdo): void { \$pdo->exec('CREATE TABLE widgets (id INT)'); }
                public function down(PDO \$pdo): void { \$pdo->exec('DROP TABLE widgets'); }
            };",
            '2026_01_02_000000_broken' => "<?php return new class implements App\\system\\Database\\Migration {
                public function up(PDO \$pdo): void { \$pdo->exec('CREATE TABLE widgets (id INT)'); }
                public function down(PDO \$pdo): void {}
            };",
        ]);
        $migrator = new Migrator($pdo, $directory);
        testThrows(fn () => $migrator->migrate(), RuntimeException::class,
            fn ($e) => testTrue(str_contains($e->getMessage(), '2026_01_02_000000_broken failed during up()')));
        testSame([1, null], array_column($migrator->status(), 'batch'));
    },
    'make writes a valid empty migration and names are checked' => static function () use ($pdo, $reset, $scratch): void {
        $reset();
        $directory = $scratch([]);
        $path = Migrator::create($directory, 'Create Users table!', mktime(12, 30, 0, 1, 2, 2026));
        testSame('2026_01_02_123000_create_users_table.php', basename($path));
        testTrue((require $path) instanceof App\system\Database\Migration);
        testThrows(fn () => Migrator::create($directory, '!!!'), InvalidArgumentException::class);

        file_put_contents("{$directory}/notes.php", '<?php');
        testThrows(fn () => (new Migrator($pdo, $directory))->status(), RuntimeException::class);
    },
    'the HTTP kernel writes through the injected connection' => static function () use ($pdo, $migrations, $reset): void {
        $reset();
        (new Migrator($pdo, $migrations))->migrate();
        $app = Application::create();
        $app->container()->instance(PDO::class, $pdo);

        $created = $app->handle(new Request('POST', '/foobar', body: ['fooMessage' => 'foo message', 'barComment' => 'bar comment']));
        testSame(200, $created->status);
        $fooId = (int) $pdo->query('SELECT MAX(id) FROM foo')->fetchColumn();
        $response = $app->handle(new Request('GET', '/foobar/' . $fooId));
        testSame(['bar comment'], array_column(json_decode($response->body, true), 'comment'));
        testSame(400, $app->handle(new Request('GET', '/foo/0'))->status);
    },
];

// exit() skips finally blocks, so clean up before exiting.
$code = testRun($tests);
$reset();
exit($code);
