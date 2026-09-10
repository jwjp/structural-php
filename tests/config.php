<?php

use App\system\Database;
use App\system\Ini;
use App\system\QueryBuilder;

return [
    'INI reads literal credentials and sections' => static function (): void {
        $path = tempnam(sys_get_temp_dir(), 'structural-ini-');
        try {
            file_put_contents($path, "[DATABASE]\nPASSWORD = \"false\"\nUSERNAME = \"null\"\n");
            $ini = new Ini($path);
            testSame(['PASSWORD' => 'false', 'USERNAME' => 'null'], $ini->get('DATABASE'));
            testSame(['DATABASE' => $ini->get('DATABASE')], $ini->getAll());
            testThrows(fn () => $ini->get('MISSING'), RuntimeException::class);
        } finally {
            unlink($path);
        }
    },
    'missing and malformed explicit configuration reports errors' => static function (): void {
        $path = tempnam(sys_get_temp_dir(), 'structural-ini-');
        try {
            file_put_contents($path, "[broken\n");
            testThrows(fn () => new Ini($path), RuntimeException::class);
        } finally {
            unlink($path);
        }
        testThrows(fn () => new Ini($path), RuntimeException::class);
    },
    'database accepts an injected PDO without reading configuration' => static function (): void {
        $pdo = new class extends PDO {
            public function __construct() {}
        };
        $database = new class($pdo) extends Database {};
        testTrue($database->queryBuilder() instanceof QueryBuilder);
    },
];
