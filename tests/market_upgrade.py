"""Exact phpMyAdmin package: 001-007, optional 008, replay and guarded failures."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import uuid

ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--php',required=True);parser.add_argument('--config',required=True);parser.add_argument('--mysql',required=True)
args=parser.parse_args()
env=os.environ.copy();env['AFEC_CONFIG_FILE']=str(Path(args.config).resolve());env['AFEC_ENV']='test'
php=str(Path(args.php).resolve())
result=subprocess.run([php,'-r','$a=require "src/bootstrap.php";echo json_encode($a->config);'],cwd=ROOT,env=env,capture_output=True)
assert result.returncode==0,'Local configuration unavailable'
cfg=json.loads(result.stdout);assert cfg['app']['environment']=='test' and cfg['database']['host'] in ['localhost','127.0.0.1'] and cfg['database']['name'].startswith('afec_test_')
name='afec_test_markets_upgrade_'+uuid.uuid4().hex[:10]
db=cfg['database'];env['MYSQL_PWD']=db['password']
client=[str(Path(args.mysql).resolve()),'--host='+db['host'],'--port='+str(db['port']),'--user='+db['username'],'--batch','--skip-column-names','--default-character-set=utf8mb4']
private=ROOT/'.local/test-artifacts'/name;private.mkdir(parents=True)
def sql(text,selected=True):
    result=subprocess.run(client+(['--database='+name] if selected else []),input=text.encode(),env=env,cwd=ROOT,capture_output=True)
    if result.returncode:
        (private/'sql-error.log').write_bytes(result.stderr)
        raise AssertionError('Upgrade SQL failed; inspect private sql-error.log')
    return result.stdout.decode('utf-8').strip()
sql('CREATE DATABASE `'+name+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',False)
sql((ROOT/'database/migration_registry.sql').read_text())
for path in sorted((ROOT/'database/migrations').glob('*.sql')):
    if path.name[:3]>'007':continue
    sql(path.read_text())
    sql("INSERT INTO schema_migrations(version,checksum,status,applied_at) VALUES ('"+path.name+"','"+hashlib.sha256(path.read_bytes()).hexdigest()+"','applied',UTC_TIMESTAMP(6));")
sql("INSERT INTO systems(name) VALUES ('Preserved system'); INSERT INTO installations(system_id,name) VALUES (1,'Preserved construction'); INSERT INTO commodities(code,name_es) VALUES ('steel','Acero'); INSERT INTO requirements(installation_id,commodity_id,required_quantity) VALUES (1,1,100);")
def business():
    return hashlib.sha256('\n'.join(sql('SELECT * FROM '+table) for table in ['systems','installations','commodities','requirements','movements','commanders','carriers']).encode()).hexdigest()
before=business()
package=(ROOT/'database/upgrades/AFEC_actualizar_mercados.sql').read_text()
assert 'MERCADOS DEL SISTEMA HABILITADOS' in sql(package)
assert business()==before,'Existing logistics must remain unchanged'
assert sql("SELECT COUNT(*) FROM schema_migrations WHERE version='008_commander_standard_load.sql';")=='0','Markets must not silently migrate standard loads'
assert sql("SELECT checksum FROM schema_migrations WHERE version='009_system_markets.sql' AND status='applied';")==hashlib.sha256((ROOT/'database/migrations/009_system_markets.sql').read_bytes()).hexdigest()
assert 'MERCADOS DEL SISTEMA HABILITADOS' in sql(package)
assert business()==before
sql("UPDATE schema_migrations SET status='running' WHERE version='009_system_markets.sql';")
assert 'OMITIDA O INCOMPLETA' in sql(package),'Partial migration cannot be marked applied by replay'
assert sql("SELECT status FROM schema_migrations WHERE version='009_system_markets.sql';")=='running'
sql("UPDATE schema_migrations SET status='applied' WHERE version='009_system_markets.sql';")
assert sql("SELECT COUNT(*) FROM market_stations;")=='0'
# Remove only empty market tables in this disposable loopback test database.
sql("DROP TABLE market_stock,market_observations,market_imports,market_products,market_stations; DELETE FROM schema_migrations WHERE version='009_system_markets.sql'; DELETE FROM permission_locks WHERE name='system_markets'; UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='001_initial.sql';")
assert 'OMITIDA O INCOMPLETA' in sql(package),'Changed prerequisite checksum must reject a fresh upgrade'
assert sql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE 'market\\_%';")=='0'
initial=ROOT/'database/migrations/001_initial.sql'
sql("UPDATE schema_migrations SET checksum='"+hashlib.sha256(initial.read_bytes()).hexdigest()+"' WHERE version='001_initial.sql';")
standard=ROOT/'database/migrations/008_commander_standard_load.sql'
sql(standard.read_text())
sql("INSERT INTO schema_migrations(version,checksum,status,applied_at) VALUES ('"+standard.name+"','"+hashlib.sha256(standard.read_bytes()).hexdigest()+"','applied',UTC_TIMESTAMP(6));")
assert 'MERCADOS DEL SISTEMA HABILITADOS' in sql(package),'Upgrade also accepts verified optional 008'
assert 'MERCADOS DEL SISTEMA HABILITADOS' in sql(package)
assert business()==before,'Both upgrade paths preserve operational data'
print('Markets phpMyAdmin upgrade: with/without 008, checksums, idempotent replay, partial-state guard and operational preservation passed.')
print('Isolated upgrade database retained:',name)
