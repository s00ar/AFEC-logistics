"""Build the reviewed additive phpMyAdmin upgrade, without contacting a database."""
from pathlib import Path
import hashlib

ROOT=Path(__file__).resolve().parents[1]
target=ROOT/'database/migrations/009_system_markets.sql'
files=sorted((ROOT/'database/migrations').glob('*.sql'))
prerequisites=[p for p in files if p.name[:3] in [f'{n:03}' for n in range(1,8)]]
assert len(prerequisites)==7
quote=lambda text:"'"+text.replace("'","''")+"'"
checks=["(version="+quote(p.name)+" AND checksum="+quote(hashlib.sha256(p.read_bytes()).hexdigest())+" AND status='applied')" for p in prerequisites]
checksum=hashlib.sha256(target.read_bytes()).hexdigest()
optional=ROOT/'database/migrations/008_commander_standard_load.sql'
optional_hash=hashlib.sha256(optional.read_bytes()).hexdigest()
tables=['market_imports','market_stations','market_products','market_observations','market_stock']
table_list=','.join(quote(t) for t in tables)
sql="""-- AFEC: Mercados del Sistema. Aplicar COMPLETO sobre una base 001-007 verificada.
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
"""
sql+="SET @afec_market_prerequisites=(SELECT COUNT(*) FROM schema_migrations WHERE "+' OR '.join(checks)+")=7;\n"
sql+="SET @afec_market_optional=(SELECT COUNT(*) FROM schema_migrations WHERE version='008_commander_standard_load.sql' AND NOT(checksum="+quote(optional_hash)+" AND status='applied'))=0;\n"
sql+="SET @afec_market_tables=(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ("+table_list+"));\n"
sql+="SET @afec_market_allowed=(@afec_market_lock=1 AND @afec_market_version_ok=1 AND @afec_market_prerequisites=1 AND @afec_market_optional=1 AND @afec_market_tables=0 AND (SELECT COUNT(*) FROM schema_migrations WHERE version='009_system_markets.sql')=0 AND (SELECT COUNT(*) FROM permission_locks WHERE name='system_markets')=0);\n"
sql+="INSERT INTO schema_migrations(version,checksum,status) SELECT '009_system_markets.sql',"+quote(checksum)+",'running' WHERE @afec_market_allowed=1;\n"
source='\n'.join(line for line in target.read_text(encoding='utf-8').splitlines() if not line.lstrip().startswith('--'))
for statement in source.split(';'):
    if not statement.strip(): continue
    sql+="SET @afec_market_sql=IF(@afec_market_allowed=1,"+quote(statement.strip())+",'DO 0');\nPREPARE afec_market_statement FROM @afec_market_sql;\nEXECUTE afec_market_statement;\nDEALLOCATE PREPARE afec_market_statement;\n"
complete="(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ("+table_list+"))=5 AND (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND constraint_name IN ('uq_market_import_content','uq_market_station_identity','uq_market_product_name','uq_market_import_observation','uq_market_current_observation'))=5 AND (SELECT COUNT(*) FROM permission_locks WHERE name='system_markets')=1"
sql+="UPDATE schema_migrations SET status='applied',applied_at=UTC_TIMESTAMP(6) WHERE version='009_system_markets.sql' AND checksum="+quote(checksum)+" AND status='running' AND @afec_market_allowed=1 AND "+complete+";\n"
sql+="SET @afec_market_done=((SELECT COUNT(*) FROM schema_migrations WHERE version='009_system_markets.sql' AND checksum="+quote(checksum)+" AND status='applied')=1 AND "+complete+");\n"
sql+="SELECT IF(@afec_market_done=1,'AFEC: MERCADOS DEL SISTEMA HABILITADOS','AFEC: AMPLIACION OMITIDA O INCOMPLETA; REVISAR PRERREQUISITOS, BLOQUEO Y ESTADO') AS resultado;\nDO RELEASE_LOCK(@afec_market_lock_name);\nSET SESSION sql_mode=@afec_market_previous_mode;\n"
output=ROOT/'database/upgrades/AFEC_actualizar_mercados.sql'
output.write_text(sql,encoding='utf-8',newline='\n')
print('Markets upgrade generated; migration SHA256:',checksum)
