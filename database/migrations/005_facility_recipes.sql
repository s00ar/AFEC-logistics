-- Editable facility recipes. No unverified game quantities are seeded.
CREATE TABLE facility_recipes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    type VARCHAR(80) NOT NULL,
    tier VARCHAR(20) NULL,
    source_note VARCHAR(1000) NOT NULL,
    source_url VARCHAR(1000) NULL,
    archived_at DATETIME(6) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_recipe_active (archived_at, name, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE facility_recipe_materials (
    recipe_id BIGINT UNSIGNED NOT NULL,
    commodity_id BIGINT UNSIGNED NOT NULL,
    required_quantity BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (recipe_id, commodity_id),
    CONSTRAINT fk_recipe_material_recipe FOREIGN KEY (recipe_id) REFERENCES facility_recipes(id),
    CONSTRAINT fk_recipe_material_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id),
    CONSTRAINT ck_recipe_quantity CHECK (required_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE installations
    ADD COLUMN facility_recipe_id BIGINT UNSIGNED NULL,
    ADD COLUMN facility_recipe_version INT UNSIGNED NULL,
    ADD CONSTRAINT fk_installation_recipe FOREIGN KEY (facility_recipe_id) REFERENCES facility_recipes(id);
