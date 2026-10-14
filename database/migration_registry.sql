-- Metadata registry; initial schema is in migrations/001_initial.sql.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(190) PRIMARY KEY,
    checksum CHAR(64) CHARACTER SET ascii NOT NULL,
    status VARCHAR(16) NOT NULL,
    applied_at DATETIME(6) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
