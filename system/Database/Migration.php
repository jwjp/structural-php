<?php

namespace App\system\Database;

use PDO;

/**
 * One schema change. A migration file returns an object implementing this:
 *
 *     return new class implements Migration {
 *         public function up(PDO $pdo): void   { $pdo->exec('CREATE TABLE ...'); }
 *         public function down(PDO $pdo): void { $pdo->exec('DROP TABLE ...'); }
 *     };
 *
 * down() must undo up() exactly, so `migrate` followed by `rollback` leaves
 * the schema unchanged.
 */
interface Migration {
    public function up(PDO $pdo): void;

    public function down(PDO $pdo): void;
}
