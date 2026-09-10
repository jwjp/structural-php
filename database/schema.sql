-- Select an existing database before importing this schema.
-- Both tables use soft deletion; deleting a foo does not delete its bars.
CREATE TABLE IF NOT EXISTS foo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message VARCHAR(150) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_foo_deleted_id (deleted_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bar (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
