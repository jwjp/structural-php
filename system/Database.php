<?php

namespace App\system;

use PDO;
use RuntimeException;

abstract class Database {
    protected PDO $pdo;

    /**
     * The container injects the shared PDO connection. Without one, the model
     * connects with the .ini / DB_* settings itself.
     */
    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? self::connect((new Ini())->getAll()['DATABASE'] ?? []);
    }

    /**
     * Opens a PDO connection from DATABASE settings. DB_* environment variables override them.
     */
    public static function connect(mixed $settings): PDO {
        if (!is_array($settings)) {
            throw new RuntimeException('DATABASE must be a configuration section.');
        }
        $environment = [
            'HOST' => 'DB_HOST',
            'PORT' => 'DB_PORT',
            'DBNAME' => 'DB_DATABASE',
            'USERNAME' => 'DB_USERNAME',
            'PASSWORD' => 'DB_PASSWORD',
        ];
        foreach ($environment as $key => $name) {
            $value = getenv($name);
            if ($value !== false) {
                $settings[$key] = $value;
            }
        }
        $settings += ['HOST' => '127.0.0.1', 'PORT' => '3306'];
        foreach (['HOST', 'DBNAME', 'USERNAME', 'PASSWORD'] as $key) {
            if (!isset($settings[$key]) || !is_string($settings[$key])
                || ($key !== 'PASSWORD' && $settings[$key] === '')) {
                throw new RuntimeException("Database {$key} is required; configure .ini or the DB_* environment variables.");
            }
        }
        $port = filter_var($settings['PORT'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($port === false || preg_match('/[;\x00-\x1f]/', $settings['HOST'] . $settings['DBNAME'])) {
            throw new RuntimeException('The database host, port or name is invalid.');
        }

        return new PDO(
            "mysql:host={$settings['HOST']};port={$port};dbname={$settings['DBNAME']};charset=utf8mb4",
            $settings['USERNAME'],
            $settings['PASSWORD'],
            [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function queryBuilder(): QueryBuilder {
        return new QueryBuilder($this->pdo);
    }
}
