"""Offline builder for the single reviewed phpMyAdmin upgrade, not a runtime dependency."""
from pathlib import Path
import hashlib

ROOT = Path(__file__).resolve().parents[1]
target = ROOT / 'database/migrations/008_commander_standard_load.sql'
migrations = sorted(path for path in (ROOT / 'database/migrations').glob('*.sql') if path.name <= target.name)
assert migrations[-1] == target and len(migrations) == 8
checks = ["(version = '" + path.name + "' AND checksum = '" + hashlib.sha256(path.read_bytes()).hexdigest() + "' AND status = 'applied')"
          for path in migrations[:-1]]
checksum = hashlib.sha256(target.read_bytes()).hexdigest()
statement = '\n'.join(line for line in target.read_text(encoding='utf-8').splitlines() if not line.lstrip().startswith('--')).strip().rstrip(';')
literal = lambda text: "'" + text.replace("'", "''") + "'"
sql = """-- AFEC Logistica: paladas individuales, sobre la instalacion 001-007 existente.
-- Seleccionar u969616855_afeclogisitc, exportar un respaldo y ejecutar COMPLETO.
-- No importar el SQL de instalacion inicial ni eliminar tablas para esta ampliacion.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET @afec_previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_BACKSLASH_ESCAPES';
SET @afec_upgrade_lock_name = CONCAT('afec-migrate-', LEFT(SHA2(DATABASE(), 256),48));
SET @afec_upgrade_lock = GET_LOCK(@afec_upgrade_lock_name, 0);
SET @afec_upgrade_major = CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED);
SET @afec_upgrade_minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED);
SET @afec_upgrade_patch = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 3), '.', -1), '-', 1) AS UNSIGNED);
SET @afec_upgrade_version_ok = IF(LOCATE('MariaDB', VERSION()) > 0,
    (@afec_upgrade_major > 10 OR (@afec_upgrade_major = 10 AND @afec_upgrade_minor >= 11)),
    (@afec_upgrade_major > 8 OR (@afec_upgrade_major = 8 AND (@afec_upgrade_minor > 0 OR @afec_upgrade_patch >= 16))));
"""
sql += "SET @afec_upgrade_prerequisites = (SELECT COUNT(*) FROM schema_migrations WHERE " + ' OR '.join(checks) + ") = 7;\n"
sql += """SET @afec_upgrade_column = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'commanders' AND column_name IN ('standard_load_quantity','load_version'));
SET @afec_upgrade_record = (SELECT COUNT(*) FROM schema_migrations WHERE version = '008_commander_standard_load.sql');
SET @afec_upgrade_allowed = (@afec_upgrade_lock = 1 AND @afec_upgrade_version_ok = 1 AND @afec_upgrade_prerequisites = 1 AND @afec_upgrade_column = 0 AND @afec_upgrade_record = 0);
"""
sql += "INSERT INTO schema_migrations (version, checksum, status) SELECT '008_commander_standard_load.sql', " + literal(checksum) + ", 'running' WHERE @afec_upgrade_allowed = 1;\n"
sql += "SET @afec_upgrade_sql = IF(@afec_upgrade_allowed = 1, " + literal(statement) + ", 'DO 0');\n"
sql += "PREPARE afec_upgrade_statement FROM @afec_upgrade_sql;\nEXECUTE afec_upgrade_statement;\nDEALLOCATE PREPARE afec_upgrade_statement;\n"
schema_complete = "(SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'commanders' AND column_name IN ('standard_load_quantity','load_version') ) = 2 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'commanders' AND constraint_name = 'ck_commander_standard_load' AND constraint_type = 'CHECK') = 1"
sql += "UPDATE schema_migrations SET status = 'applied', applied_at = UTC_TIMESTAMP(6) WHERE version = '008_commander_standard_load.sql' AND checksum = " + literal(checksum) + " AND status = 'running' AND @afec_upgrade_allowed = 1 AND " + schema_complete + ";\n"
sql += "SET @afec_upgrade_done = (SELECT COUNT(*) FROM schema_migrations WHERE version = '008_commander_standard_load.sql' AND checksum = " + literal(checksum) + " AND status = 'applied') = 1 AND " + schema_complete + ";\n"
sql += """SELECT CASE WHEN @afec_upgrade_done = 1 THEN 'AFEC: PALADAS ESTANDAR HABILITADAS'
    ELSE 'AFEC: AMPLIACION OMITIDA O INCOMPLETA; REVISAR VERSIONES, ESTADO Y BLOQUEO' END AS resultado;
DO RELEASE_LOCK(@afec_upgrade_lock_name);
SET SESSION sql_mode = @afec_previous_sql_mode;
"""
output = ROOT / 'database/upgrades/AFEC_actualizar_paladas.sql'
output.parent.mkdir(parents=True, exist_ok=True)
output.write_text(sql, encoding='utf-8', newline='\n')
print('Paquete de ampliación generado:', output.name, 'SHA256', hashlib.sha256(output.read_bytes()).hexdigest())
