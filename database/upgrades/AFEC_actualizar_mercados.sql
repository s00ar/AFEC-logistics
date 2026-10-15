-- AFEC: Mercados del Sistema. Aplicar COMPLETO sobre una base 001-007 verificada.
-- Exportar respaldo antes. 008 es opcional para este modulo y no se aplica aqui.
-- No borrar datos ni importar el instalador inicial. DDL confirma implicitamente.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @afec_market_previous_mode=@@SESSION.sql_mode;
SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_BACKSLASH_ESCAPES';
SET @afec_market_lock_name=CONCAT('afec-migrate-',LEFT(SHA2(DATABASE(),256),48));
SET @afec_market_lock=GET_LOCK(@afec_market_lock_name,0);
SET @afec_market_major=CAST(SUBSTRING_INDEX(VERSION(),'.',1) AS UNSIGNED);
SET @afec_market_minor=CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(),'.',2),'.',-1) AS UNSIGNED);
SET @afec_market_patch=CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(),'.',3),'.',-1),'-',1) AS UNSIGNED);
SET @afec_market_version_ok=IF(LOCATE('MariaDB',VERSION())>0,
(@afec_market_major>10 OR (@afec_market_major=10 AND @afec_market_minor>=11)),
(@afec_market_major>8 OR (@afec_market_major=8 AND (@afec_market_minor>0 OR @afec_market_patch>=16))));
SET @afec_market_prerequisites=(SELECT COUNT(*) FROM schema_migrations WHERE (version='001_initial.sql' AND checksum='a20184f5d35d7ae02c20e160657ddcd1c65d08f89204931a9207e57c28345538' AND status='applied') OR (version='002_user_management.sql' AND checksum='2208796909792cc80971048ed2df9c7f0196337022e9d2cc1ae23291bb6d8ade' AND status='applied') OR (version='003_logistics_lists.sql' AND checksum='46c651910f1ac273fc9f2070a38f17fe39904aadbec6d79462e7823674f6eac2' AND status='applied') OR (version='004_password_operation_verifier.sql' AND checksum='7052f72fe10e513b85060df93eef49450d27771a33fc651081663ece5ae806cf' AND status='applied') OR (version='005_facility_recipes.sql' AND checksum='865564bfd6c93d998b5ce8c7ad45f033f2558ea64056d4c7d7453fda70074e84' AND status='applied') OR (version='006_recipe_provenance.sql' AND checksum='fdc8ac3f3a8275bc628572f3dc8e4cb0cc784fc1bd2f84537d68bfc28766ece1' AND status='applied') OR (version='007_commodity_aliases.sql' AND checksum='d80403eccb19a64cfca5ae3e93bdf5edf71dcfee04f1ed8fb96e0659989c67a3' AND status='applied'))=7;
SET @afec_market_optional=(SELECT COUNT(*) FROM schema_migrations WHERE version='008_commander_standard_load.sql' AND NOT(checksum='858c279a592d9c47ecb5698a4b52a027729d86d4e59679d6d00f1949bb2f6e14' AND status='applied'))=0;
SET @afec_market_tables=(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('market_imports','market_stations','market_products','market_observations','market_stock'));
SET @afec_market_allowed=(@afec_market_lock=1 AND @afec_market_version_ok=1 AND @afec_market_prerequisites=1 AND @afec_market_optional=1 AND @afec_market_tables=0 AND (SELECT COUNT(*) FROM schema_migrations WHERE version='009_system_markets.sql')=0 AND (SELECT COUNT(*) FROM permission_locks WHERE name='system_markets')=0);
INSERT INTO schema_migrations(version,checksum,status) SELECT '009_system_markets.sql','d01504070a5556864232306ad70de21967a4bcc71a23432bd917541b7384759b','running' WHERE @afec_market_allowed=1;
SET @afec_market_sql=IF(@afec_market_allowed=1,'CREATE TABLE market_imports (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
SET @afec_market_sql=IF(@afec_market_allowed=1,'CREATE TABLE market_stations (
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
    CONSTRAINT ck_market_station_pad CHECK (max_pad IS NULL OR max_pad IN (''S'',''M'',''L''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
SET @afec_market_sql=IF(@afec_market_allowed=1,'CREATE TABLE market_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    commodity_id BIGINT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_market_product_name (name_key),
    INDEX idx_market_product_commodity (commodity_id),
    CONSTRAINT fk_market_product_commodity FOREIGN KEY (commodity_id) REFERENCES commodities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
SET @afec_market_sql=IF(@afec_market_allowed=1,'CREATE TABLE market_observations (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
SET @afec_market_sql=IF(@afec_market_allowed=1,'CREATE TABLE market_stock (
    station_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    observation_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (station_id,product_id),
    UNIQUE KEY uq_market_current_observation (observation_id),
    CONSTRAINT fk_market_stock_station FOREIGN KEY (station_id) REFERENCES market_stations(id),
    CONSTRAINT fk_market_stock_product FOREIGN KEY (product_id) REFERENCES market_products(id),
    CONSTRAINT fk_market_stock_observation FOREIGN KEY (observation_id) REFERENCES market_observations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
SET @afec_market_sql=IF(@afec_market_allowed=1,'INSERT INTO permission_locks (name) VALUES (''system_markets'')','DO 0');
PREPARE afec_market_statement FROM @afec_market_sql;
EXECUTE afec_market_statement;
DEALLOCATE PREPARE afec_market_statement;
UPDATE schema_migrations SET status='applied',applied_at=UTC_TIMESTAMP(6) WHERE version='009_system_markets.sql' AND checksum='d01504070a5556864232306ad70de21967a4bcc71a23432bd917541b7384759b' AND status='running' AND @afec_market_allowed=1 AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('market_imports','market_stations','market_products','market_observations','market_stock'))=5 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('uq_market_import_content','uq_market_station_identity','uq_market_product_name','uq_market_import_observation','uq_market_current_observation'))=5 AND (SELECT COUNT(*) FROM permission_locks WHERE name='system_markets')=1;
SET @afec_market_done=((SELECT COUNT(*) FROM schema_migrations WHERE version='009_system_markets.sql' AND checksum='d01504070a5556864232306ad70de21967a4bcc71a23432bd917541b7384759b' AND status='applied')=1 AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('market_imports','market_stations','market_products','market_observations','market_stock'))=5 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('uq_market_import_content','uq_market_station_identity','uq_market_product_name','uq_market_import_observation','uq_market_current_observation'))=5 AND (SELECT COUNT(*) FROM permission_locks WHERE name='system_markets')=1);
SELECT IF(@afec_market_done=1,'AFEC: MERCADOS DEL SISTEMA HABILITADOS','AFEC: AMPLIACION OMITIDA O INCOMPLETA; REVISAR PRERREQUISITOS, BLOQUEO Y ESTADO') AS resultado;
DO RELEASE_LOCK(@afec_market_lock_name);
SET SESSION sql_mode=@afec_market_previous_mode;
