-- Commercial observations are independent from construction requirements and deliveries.
CREATE TABLE market_imports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    stock_filename VARCHAR(255) NULL,
    summary_filename VARCHAR(255) NULL,
    stock_csv MEDIUMTEXT NULL,
    summary_csv MEDIUMTEXT NULL,
    stock_rows INT UNSIGNED NOT NULL,
    summary_rows INT UNSIGNED NOT NULL,
    result_json LONGTEXT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_market_import_content (content_sha256),
    CONSTRAINT fk_market_import_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT ck_market_import_result CHECK (result_json IS NULL OR JSON_VALID(result_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_stations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identity_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    system_name VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    body_name VARCHAR(255) NULL,
    economy VARCHAR(120) NULL,
    max_pad CHAR(1) NULL,
    summary_count INT UNSIGNED NULL,
    summary_source VARCHAR(1000) NULL,
    summary_note VARCHAR(1000) NULL,
    summary_observed_on DATE NULL,
    metadata_observed_on DATE NULL,
    metadata_source VARCHAR(1000) NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_market_station_identity (identity_key),
    INDEX idx_market_station_system (system_name(120)),
    CONSTRAINT ck_market_station_pad CHECK (max_pad IS NULL OR max_pad IN ('S','M','L'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    commodity_id BIGINT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_market_product_name (name_key),
    INDEX idx_market_product_commodity (commodity_id),
    CONSTRAINT fk_market_product_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_observations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id BIGINT UNSIGNED NULL,
    station_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    category VARCHAR(120) NULL,
    quantity BIGINT UNSIGNED NULL,
    available TINYINT(1) NULL,
    verified TINYINT(1) NOT NULL,
    observed_on DATE NULL,
    source VARCHAR(1000) NOT NULL,
    verification VARCHAR(255) NOT NULL,
    applied TINYINT(1) NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_market_import_observation (import_id,station_id,product_id),
    INDEX idx_market_observation_history (station_id,product_id,id),
    CONSTRAINT fk_market_observation_import FOREIGN KEY (import_id) REFERENCES market_imports(id),
    CONSTRAINT fk_market_observation_station FOREIGN KEY (station_id) REFERENCES market_stations(id),
    CONSTRAINT fk_market_observation_product FOREIGN KEY (product_id) REFERENCES market_products(id),
    CONSTRAINT fk_market_observation_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    CONSTRAINT ck_market_observation_available CHECK (available IS NULL OR available IN (0,1)),
    CONSTRAINT ck_market_observation_verified CHECK (verified IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE market_stock (
    station_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    observation_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (station_id,product_id),
    UNIQUE KEY uq_market_current_observation (observation_id),
    CONSTRAINT fk_market_stock_station FOREIGN KEY (station_id) REFERENCES market_stations(id),
    CONSTRAINT fk_market_stock_product FOREIGN KEY (product_id) REFERENCES market_products(id),
    CONSTRAINT fk_market_stock_observation FOREIGN KEY (observation_id) REFERENCES market_observations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permission_locks (name) VALUES ('system_markets');
