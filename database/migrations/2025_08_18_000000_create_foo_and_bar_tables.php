<?php

use App\system\Database\Migration;

// The sample tables. Both use soft deletion; deleting a foo does not delete its bars.
return new class implements Migration {
    public function up(PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE foo (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                message VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                deleted_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_foo_deleted_id (deleted_at, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE bar (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                foo_id BIGINT UNSIGNED NOT NULL,
                comment VARCHAR(150) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                deleted_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                INDEX idx_bar_deleted_id (deleted_at, id),
                INDEX idx_bar_foo_deleted_id (foo_id, deleted_at, id),
                CONSTRAINT fk_bar_foo FOREIGN KEY (foo_id) REFERENCES foo (id)
                    ON UPDATE RESTRICT ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(PDO $pdo): void {
        // Drop the child table first because of the foreign key.
        $pdo->exec('DROP TABLE bar');
        $pdo->exec('DROP TABLE foo');
    }
};
