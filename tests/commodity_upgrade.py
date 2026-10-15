"""Verify the exact phpMyAdmin upgrade on a fresh local copy of the delivered 001-006 package."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import uuid

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
parser.add_argument('--mysql', required=True)
parser.add_argument('--config', required=True)
parser.add_argument('--initial-sql', required=True)
args = parser.parse_args()
env = os.environ.copy()
for name in list(env):
    if name.startswith('AFEC_DB_') or name in ['AFEC_CONFIG_FILE','AFEC_ENV']: env.pop(name)
env['AFEC_CONFIG_FILE'] = str(Path(args.config).resolve())
php = str(Path(args.php).resolve())
def command(*arguments):
    result = subprocess.run([php,*arguments],cwd=ROOT,env=env,capture_output=True,encoding='utf-8')
    if result.returncode: raise AssertionError('Local PHP verification failed: '+result.stderr)
    return result.stdout
cfg = json.loads(command('-r',"$a=require 'src/bootstrap.php';echo json_encode($a->config);"))
assert cfg['app']['environment']=='test' and cfg['database']['host'] in ['127.0.0.1','localhost'] and cfg['database']['name'].startswith('afec_test_')
db = cfg['database']; name = 'afec_test_upgrade_' + uuid.uuid4().hex[:12]
private = ROOT/'.local/test-artifacts'/name; private.mkdir(parents=True)
env['MYSQL_PWD'] = db['password']
client = [str(Path(args.mysql).resolve()),'--host='+db['host'],'--port='+str(db['port']),'--user='+db['username'],'--batch','--skip-column-names','--default-character-set=utf8mb4']
def sql(text,selected=True):
    result = subprocess.run(client+(['--database='+name] if selected else []),input=text.encode('utf-8'),env=env,cwd=ROOT,capture_output=True)
    if result.returncode:
        (private/'sql-error.log').write_bytes(result.stderr)
        raise AssertionError('SQL upgrade failed; inspect the private sql-error.log')
    return result.stdout
sql('CREATE DATABASE `'+name+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',False)
cfg['database']['name']=name;cfg['storage_path']=str(private/'storage');cfg['session']['cookie_secure']=False
file = private/'config.php';file.write_text("<?php return json_decode(hex2bin('"+json.dumps(cfg).encode('utf-8').hex()+"'),true,32,JSON_THROW_ON_ERROR);",encoding='utf-8')
env['AFEC_CONFIG_FILE']=str(file)
initial = Path(args.initial_sql).read_text(encoding='utf-8')
assert b'INSTALACION COMPLETADA' in sql(initial)
assert command('-r',"$a=require 'src/bootstrap.php';echo (new Afec\\Repositories\\CommodityRepository($a->database))->aliasesReady()?'ready':'legacy';")=='legacy'
# Preserve legacy business rows as well as accounts, catalogue, audit and receipts.
sql("INSERT INTO systems (name) VALUES ('Preserve existing system'); INSERT INTO installations (system_id,name) VALUES (1,'Preserve existing facility'); INSERT INTO requirements (installation_id,commodity_id,required_quantity) VALUES (1,1,100);")
def snapshot():
    tables=sql('SHOW TABLES;').decode('utf-8').splitlines()
    chunks=[]
    for table in tables:
        columns = 'id,code,name_es,name_en,active,created_at,updated_at' if table=='commodities' else '*'
        if table=='schema_migrations':
            chunks.append(sql("SELECT * FROM schema_migrations WHERE version <> '007_commodity_aliases.sql' ORDER BY version;"))
        else: chunks.append(sql('SELECT '+columns+' FROM `'+table+'`;'))
    return hashlib.sha256(b''.join(chunks)).hexdigest()
before=snapshot()
upgrade=(ROOT/'database/upgrades/AFEC_actualizar_mercancias.sql').read_text(encoding='utf-8')
assert b'EQUIVALENCIAS HABILITADAS' in sql(upgrade)
assert snapshot()==before,'Upgrade modified existing data'
assert b'EQUIVALENCIAS HABILITADAS' in sql(upgrade) and snapshot()==before,'Repeated upgrade changed existing data'
assert command('-r',"$a=require 'src/bootstrap.php';echo (new Afec\\Repositories\\CommodityRepository($a->database))->aliasesReady()?'ready':'legacy';")=='ready'
assert 'Pendiente: 007_commodity_aliases.sql' not in command('tools/migrate.php')
checksum = hashlib.sha256((ROOT/'database/migrations/007_commodity_aliases.sql').read_bytes()).hexdigest()
assert sql("SELECT checksum FROM schema_migrations WHERE version='007_commodity_aliases.sql' AND status='applied';").decode('utf-8').strip()==checksum
# A registry mismatch prevents even the attempt to apply the DDL.
sql("DELETE FROM schema_migrations WHERE version='007_commodity_aliases.sql'; ALTER TABLE commodities DROP CONSTRAINT ck_commodity_aliases, DROP COLUMN aliases_json; UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='001_initial.sql';")
before=snapshot()
assert b'OMITIDA' in sql(upgrade) and snapshot()==before
assert command('-r',"$a=require 'src/bootstrap.php';echo (new Afec\\Repositories\\CommodityRepository($a->database))->aliasesReady()?'ready':'legacy';")=='legacy'
print('8 upgrade checks passed: legacy compatibility, existing data preserved, replay safe, checksum registered, mismatched registry rejected.')
