-- Additive account management. Never erase accounts referenced by audit or movements.
ALTER TABLE users ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1, ADD COLUMN deleted_at DATETIME(6) NULL;
CREATE TABLE permission_locks (
    name VARCHAR(32) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO permission_locks (name) VALUES ('users');
