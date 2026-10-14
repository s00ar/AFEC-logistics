-- AFEC Logistica: ampliacion de mercancias, sobre la instalacion 001-006 existente.
-- Seleccionar u969616855_afeclogisitc, exportar un respaldo y ejecutar COMPLETO.
-- No importar el SQL de instalacion inicial ni eliminar tablas para esta ampliacion.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @afec_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_BACKSLASH_ESCAPES';
SET @afec_upgrade_lock_name = CONCAT('afec-migrate-', LEFT(SHA2(DATABASE(), 256),48));
SET @afec_upgrade_lock = GET_LOCK(@afec_upgrade_lock_name, 0);
SET @afec_upgrade_major = CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED);
SET @afec_upgrade_minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED);
SET @afec_upgrade_patch = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 3), '.', -1) AS UNSIGNED);
SET @afec_upgrade_version_ok = IF(LOCATE('MariaDB', VERSION()) > 0,
    (@afec_upgrade_major > 10 OR (@afec_upgrade_major = 10 AND @afec_upgrade_minor >= 11)),
    (@afec_upgrade_major > 8 OR (@afec_upgrade_major = 8 AND (@afec_upgrade_minor > 0 OR @afec_upgrade_patch >= 16))));
SET @afec_upgrade_prerequisites = (SELECT COUNT(*) FROM schema_migrations WHERE (version = '001_initial.sql' AND checksum = 'a20184f5d35d7ae02c20e160657ddcd1c65d08f89204931a9207e57c28345538' AND status = 'applied') OR (version = '002_user_management.sql' AND checksum = '2208796909792cc80971048ed2df9c7f0196337022e9d2cc1ae23291bb6d8ade' AND status = 'applied') OR (version = '003_logistics_lists.sql' AND checksum = '46c651910f1ac273fc9f2070a38f17fe39904aadbec6d79462e7823674f6eac2' AND status = 'applied') OR (version = '004_password_operation_verifier.sql' AND checksum = '7052f72fe10e513b85060df93eef49450d27771a33fc651081663ece5ae806cf' AND status = 'applied') OR (version = '005_facility_recipes.sql' AND checksum = '865564bfd6c93d998b5ce8c7ad45f033f2558ea64056d4c7d7453fda70074e84' AND status = 'applied') OR (version = '006_recipe_provenance.sql' AND checksum = 'fdc8ac3f3a8275bc628572f3dc8e4cb0cc784fc1bd2f84537d68bfc28766ece1' AND status = 'applied')) = 6;
SET @afec_upgrade_column = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'commodities' AND column_name = 'aliases_json');
SET @afec_upgrade_record = (SELECT COUNT(*) FROM schema_migrations WHERE version = '007_commodity_aliases.sql');
SET @afec_upgrade_allowed = (@afec_upgrade_lock = 1 AND @afec_upgrade_version_ok = 1 AND @afec_upgrade_prerequisites = 1 AND @afec_upgrade_column = 0 AND @afec_upgrade_record = 0);
INSERT INTO schema_migrations (version, checksum, status) SELECT '007_commodity_aliases.sql', 'd80403eccb19a64cfca5ae3e93bdf5edf71dcfee04f1ed8fb96e0659989c67a3', 'running' WHERE @afec_upgrade_allowed = 1;
SET @afec_upgrade_sql = IF(@afec_upgrade_allowed = 1, 'ALTER TABLE commodities
    ADD COLUMN aliases_json LONGTEXT NULL,
    ADD CONSTRAINT ck_commodity_aliases CHECK (aliases_json IS NULL OR (JSON_VALID(aliases_json) AND JSON_TYPE(aliases_json) = ''ARRAY''))', 'DO 0');
PREPARE afec_upgrade_statement FROM @afec_upgrade_sql;
EXECUTE afec_upgrade_statement;
DEALLOCATE PREPARE afec_upgrade_statement;
UPDATE schema_migrations SET status = 'applied', applied_at = UTC_TIMESTAMP(6) WHERE version = '007_commodity_aliases.sql' AND checksum = 'd80403eccb19a64cfca5ae3e93bdf5edf71dcfee04f1ed8fb96e0659989c67a3' AND status = 'running' AND @afec_upgrade_allowed = 1 AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'commodities' AND column_name = 'aliases_json' AND data_type = 'longtext') = 1 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'commodities' AND constraint_name = 'ck_commodity_aliases' AND constraint_type = 'CHECK') = 1;
SET @afec_upgrade_done = (SELECT COUNT(*) FROM schema_migrations WHERE version = '007_commodity_aliases.sql' AND checksum = 'd80403eccb19a64cfca5ae3e93bdf5edf71dcfee04f1ed8fb96e0659989c67a3' AND status = 'applied') = 1 AND (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'commodities' AND column_name = 'aliases_json' AND data_type = 'longtext') = 1 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'commodities' AND constraint_name = 'ck_commodity_aliases' AND constraint_type = 'CHECK') = 1;
SELECT CASE WHEN @afec_upgrade_done = 1 THEN 'AFEC: MERCANCIAS ACTUALIZADAS; EQUIVALENCIAS HABILITADAS'
    ELSE 'AFEC: AMPLIACION OMITIDA O INCOMPLETA; REVISAR VERSIONES, ESTADO Y BLOQUEO' END AS resultado;
DO RELEASE_LOCK(@afec_upgrade_lock_name);
SET SESSION sql_mode = @afec_previous_sql_mode;
