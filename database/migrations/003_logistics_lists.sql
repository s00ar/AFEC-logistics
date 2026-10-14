-- Legacy free-text state is preserved. Existing installations require manual classification.
ALTER TABLE installations ADD COLUMN construction_status VARCHAR(24) NULL,
    ADD CONSTRAINT ck_construction_status CHECK (construction_status IS NULL OR construction_status IN ('under_construction','completed'));
-- Keep the FK index while allowing multiple bounded partial compensations.
ALTER TABLE movements ADD INDEX idx_movement_reversals (reverses_movement_id);
ALTER TABLE movements DROP INDEX uq_movement_reversal;
CREATE TABLE wishlist_entries (
    list_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    installation_id BIGINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    added_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (list_key, installation_id),
    CONSTRAINT ck_list_owner CHECK ((list_key = 'fleet' AND owner_user_id IS NULL) OR (owner_user_id IS NOT NULL AND list_key = CONCAT('personal:', owner_user_id))),
    CONSTRAINT fk_list_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_list_installation FOREIGN KEY (installation_id) REFERENCES installations(id),
    CONSTRAINT fk_list_added FOREIGN KEY (added_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_list_updated FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE commodity_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commodity_id BIGINT UNSIGNED NOT NULL,
    system_name VARCHAR(255) NOT NULL,
    facility_name VARCHAR(255) NOT NULL,
    facility_type VARCHAR(24) NOT NULL,
    notes VARCHAR(1000) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NOT NULL,
    updated_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_commodity_source (commodity_id,system_name,facility_name),
    CONSTRAINT ck_source_type CHECK (facility_type IN ('station','settlement')),
    CONSTRAINT fk_source_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id),
    CONSTRAINT fk_source_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    CONSTRAINT fk_source_updater FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
