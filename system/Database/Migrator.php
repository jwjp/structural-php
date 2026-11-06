<?php

namespace App\system\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Applies and reverts migration files in database/migrations.
 *
 * Files are named YYYY_MM_DD_HHMMSS_description.php and run in name order.
 * The `migrations` table records which files ran and in which batch; one
 * `migrate` call is one batch, and `rollback` reverts the latest batch.
 *
 * MySQL commits DDL statements (CREATE, ALTER, DROP) immediately, so a
 * migration cannot be rolled back as a transaction. If one fails, the runner
 * stops, does not record it, and reports which file failed; fix the file and
 * clean up any partial change by hand. See docs/migrations.md.
 */
class Migrator {
    private const NAME = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+$/D';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory
    ) {
    }

    /**
     * @return list<array{name: string, batch: ?int, ran_at: ?string}> Every migration file, applied or not.
     */
    public function status(): array {
        $this->ensureTable();
        $applied = [];
        foreach ($this->pdo->query('SELECT name, batch, ran_at FROM migrations')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $applied[$row['name']] = $row;
        }

        $status = [];
        foreach ($this->files() as $name => $path) {
            $status[] = [
                'name' => $name,
                'batch' => isset($applied[$name]) ? (int) $applied[$name]['batch'] : null,
                'ran_at' => $applied[$name]['ran_at'] ?? null,
            ];
            unset($applied[$name]);
        }
        foreach ($applied as $name => $row) {
            throw new RuntimeException("Migration {$name} is recorded as applied but its file is missing.");
        }
        return $status;
    }

    /**
     * Runs every pending migration as one new batch.
     *
     * @return list<string> Names of the migrations that ran.
     */
    public function migrate(): array {
        $pending = array_values(array_filter($this->status(), static fn (array $row): bool => $row['batch'] === null));
        if ($pending === []) {
            return [];
        }

        $batch = (int) $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $files = $this->files();
        $ran = [];
        foreach ($pending as $row) {
            $this->run($row['name'], $files[$row['name']], 'up');
            $this->pdo->prepare('INSERT INTO migrations (name, batch) VALUES (:name, :batch)')
                ->execute([':name' => $row['name'], ':batch' => $batch]);
            $ran[] = $row['name'];
        }
        return $ran;
    }

    /**
     * Reverts the latest $batches batches, newest migration first.
     *
     * @return list<string> Names of the migrations that were reverted.
     */
    public function rollback(int $batches = 1): array {
        if ($batches < 1) {
            throw new InvalidArgumentException('Roll back at least one batch.');
        }
        $this->status();
        $statement = $this->pdo->prepare(
            'SELECT name FROM migrations
             WHERE batch > (SELECT COALESCE(MAX(batch), 0) FROM migrations) - :batches
             ORDER BY batch DESC, name DESC'
        );
        $statement->bindValue(':batches', $batches, PDO::PARAM_INT);
        $statement->execute();

        $files = $this->files();
        $reverted = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $this->run($name, $files[$name], 'down');
            $this->pdo->prepare('DELETE FROM migrations WHERE name = :name')->execute([':name' => $name]);
            $reverted[] = $name;
        }
        return $reverted;
    }

    /**
     * Writes an empty migration file and returns its path. Needs no database connection.
     */
    public static function create(string $directory, string $description, ?int $timestamp = null): string {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($description)), '_');
        if ($slug === '') {
            throw new InvalidArgumentException('Describe the migration, e.g. "create users table".');
        }
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create the migrations directory.');
        }
        $path = $directory . '/' . date('Y_m_d_His', $timestamp ?? time()) . "_{$slug}.php";
        if (file_exists($path)) {
            throw new RuntimeException('A migration with this name already exists: ' . basename($path));
        }
        $template = <<<'PHP'
            <?php

            use App\system\Database\Migration;

            return new class implements Migration {
                public function up(PDO $pdo): void {
                    $pdo->exec('');
                }

                public function down(PDO $pdo): void {
                    $pdo->exec('');
                }
            };

            PHP;
        file_put_contents($path, $template);
        return $path;
    }

    private function run(string $name, string $path, string $direction): void {
        $migration = (static fn (string $path): mixed => require $path)($path);
        if (!$migration instanceof Migration) {
            throw new RuntimeException("{$name} must return an object implementing " . Migration::class . '.');
        }
        try {
            $migration->{$direction}($this->pdo);
        } catch (Throwable $exception) {
            throw new RuntimeException("Migration {$name} failed during {$direction}(): " . $exception->getMessage(), 0, $exception);
        }
    }

    /** @return array<string, string> Migration name => file path, sorted by name. */
    private function files(): array {
        $files = [];
        foreach (glob($this->directory . '/*.php') ?: [] as $path) {
            $name = basename($path, '.php');
            if (!preg_match(self::NAME, $name)) {
                throw new RuntimeException("Unexpected migration file name: {$name}.php (use YYYY_MM_DD_HHMMSS_description.php).");
            }
            $files[$name] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function ensureTable(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
