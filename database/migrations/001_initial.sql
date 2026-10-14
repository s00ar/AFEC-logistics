-- Initial structure. Run only through tools/migrate.php after a backup.
-- No business data, administrator account or production migrations are seeded here.
CREATE TABLE commanders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(160) NOT NULL,
    normalized_name VARCHAR(160) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_commander_name (normalized_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carriers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NULL,
    name VARCHAR(160) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_carrier_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(120) NOT NULL,
    display_name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(16) NOT NULL DEFAULT 'viewer',
    active TINYINT(1) NOT NULL DEFAULT 1,
    commander_id BIGINT UNSIGNED NULL,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_username (username),
    CONSTRAINT fk_user_commander FOREIGN KEY (commander_id) REFERENCES commanders(id),
    CONSTRAINT ck_user_role CHECK (role IN ('admin', 'operator', 'viewer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE systems (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    external_key VARCHAR(160) NULL,
    description TEXT NULL,
    archived_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_system_external (external_key),
    INDEX idx_system_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    system_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    body_name VARCHAR(255) NULL,
    location_label VARCHAR(255) NULL,
    tier VARCHAR(20) NULL,
    type VARCHAR(80) NULL,
    state VARCHAR(60) NOT NULL DEFAULT 'planned',
    sort_order INT NOT NULL DEFAULT 0,
    archived_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_installation_system FOREIGN KEY (system_id) REFERENCES systems(id),
    INDEX idx_installation_system (system_id, archived_at, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commodities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(120) NOT NULL,
    name_es VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_commodity_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requirements (
    installation_id BIGINT UNSIGNED NOT NULL,
    commodity_id BIGINT UNSIGNED NOT NULL,
    required_quantity BIGINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (installation_id, commodity_id),
    CONSTRAINT fk_requirement_installation FOREIGN KEY (installation_id) REFERENCES installations(id),
    CONSTRAINT fk_requirement_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    scope VARCHAR(80) NOT NULL,
    idempotency_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_hash BINARY(32) NOT NULL,
    result_json LONGTEXT NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_operation_request (actor_user_id, scope, idempotency_key),
    CONSTRAINT fk_operation_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requirement_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installation_id BIGINT UNSIGNED NOT NULL,
    commodity_id BIGINT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    old_quantity BIGINT UNSIGNED NULL,
    new_quantity BIGINT UNSIGNED NOT NULL,
    old_active TINYINT(1) NULL,
    new_active TINYINT(1) NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    operation_id BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_requirement_revision (installation_id, commodity_id, revision_number),
    CONSTRAINT fk_revision_requirement FOREIGN KEY (installation_id, commodity_id) REFERENCES requirements(installation_id, commodity_id),
    CONSTRAINT fk_revision_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT fk_revision_operation FOREIGN KEY (operation_id) REFERENCES operations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_dataset VARCHAR(80) NOT NULL,
    snapshot_hash BINARY(32) NOT NULL,
    mapping_version VARCHAR(40) NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL,
    report_path VARCHAR(255) NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_import_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    INDEX idx_import_snapshot (source_dataset, snapshot_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_run_id BIGINT UNSIGNED NOT NULL,
    source_dataset VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    source_key VARCHAR(190) NOT NULL,
    source_locator TEXT NOT NULL,
    content_hash BINARY(32) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    provenance_json LONGTEXT NULL,
    UNIQUE KEY uq_import_origin (source_dataset, entity_type, source_key),
    CONSTRAINT fk_import_item_run FOREIGN KEY (import_run_id) REFERENCES import_runs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    installation_id BIGINT UNSIGNED NOT NULL,
    commodity_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    commander_id BIGINT UNSIGNED NULL,
    carrier_id BIGINT UNSIGNED NULL,
    delta_quantity BIGINT NOT NULL,
    movement_type VARCHAR(24) NOT NULL,
    reverses_movement_id BIGINT UNSIGNED NULL,
    operation_id BIGINT UNSIGNED NOT NULL,
    entry_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    occurred_at DATETIME(6) NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    reason TEXT NULL,
    import_item_id BIGINT UNSIGNED NULL,
    legacy_attribution TEXT NULL,
    UNIQUE KEY uq_movement_operation (operation_id, entry_number),
    UNIQUE KEY uq_movement_import (import_item_id),
    -- Provisional complete-reversal policy. Partial reversals require a later reviewed migration.
    UNIQUE KEY uq_movement_reversal (reverses_movement_id),
    CONSTRAINT fk_movement_requirement FOREIGN KEY (installation_id, commodity_id) REFERENCES requirements(installation_id, commodity_id),
    CONSTRAINT fk_movement_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_movement_commander FOREIGN KEY (commander_id) REFERENCES commanders(id),
    CONSTRAINT fk_movement_carrier FOREIGN KEY (carrier_id) REFERENCES carriers(id),
    CONSTRAINT fk_movement_original FOREIGN KEY (reverses_movement_id) REFERENCES movements(id),
    CONSTRAINT fk_movement_operation FOREIGN KEY (operation_id) REFERENCES operations(id),
    CONSTRAINT fk_movement_import FOREIGN KEY (import_item_id) REFERENCES import_items(id),
    CONSTRAINT ck_movement_nonzero CHECK (delta_quantity <> 0),
    CONSTRAINT ck_movement_type CHECK (movement_type IN ('delivery', 'reversal', 'adjustment', 'opening_balance', 'import_delivery')),
    CONSTRAINT ck_delivery_attribution CHECK (movement_type <> 'delivery' OR (commander_id IS NOT NULL AND delta_quantity > 0 AND occurred_at IS NOT NULL)),
    CONSTRAINT ck_reversal_reference CHECK (movement_type <> 'reversal' OR reverses_movement_id IS NOT NULL),
    INDEX idx_movement_requirement (installation_id, commodity_id, recorded_at, id),
    INDEX idx_movement_commander (commander_id, recorded_at, id),
    INDEX idx_movement_carrier (carrier_id, recorded_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    changes_json LONGTEXT NULL,
    reason TEXT NULL,
    operation_id BIGINT UNSIGNED NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT fk_audit_operation FOREIGN KEY (operation_id) REFERENCES operations(id),
    INDEX idx_audit_entity (entity_type, entity_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identity_hash BINARY(32) NOT NULL,
    network_hash BINARY(32) NOT NULL,
    succeeded TINYINT(1) NOT NULL,
    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_login_identity (identity_hash, succeeded, recorded_at),
    INDEX idx_login_network (network_hash, succeeded, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
