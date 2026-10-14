"""Build and import a private first-install package using isolated loopback databases.

No existing database is reset. Never connects to Hostinger. Keeps the delivered
SQL and first administrator password under .local, excluded from Git.
"""
import argparse
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import secrets
import socket
import subprocess
import time
import urllib.error
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
parser.add_argument('--config', required=True, help='Existing guarded local test connection')
parser.add_argument('--mysql', required=True, help='Local MariaDB/MySQL client executable')
parser.add_argument('--output-dir', required=True, help='New private directory under .local')
args = parser.parse_args()
php, mysql = str(Path(args.php).resolve()), str(Path(args.mysql).resolve())
private = Path(args.output_dir).resolve()
assert private.is_relative_to(ROOT / '.local') and not private.exists(), 'Use a new directory under .local'
environment = os.environ.copy()
# Avoid inheriting a production DB override from the caller.
for name in list(environment):
    if name.startswith('AFEC_DB_') or name in ['AFEC_CONFIG_FILE', 'AFEC_ENV']:
        environment.pop(name)
environment['AFEC_CONFIG_FILE'] = str(Path(args.config).resolve())


def command(env, *arguments):
    result = subprocess.run([php, *arguments], cwd=ROOT, env=env, capture_output=True, encoding='utf-8')
    if result.returncode:
        raise AssertionError('PHP command failed: ' + arguments[0] + '\n' + result.stderr)
    return result.stdout


configuration = json.loads(command(environment, '-r', "$a=require 'src/bootstrap.php';echo json_encode($a->config);"))
connection = configuration['database']
assert configuration['app']['environment'] == 'test'
assert connection['host'] in ['localhost', '127.0.0.1'] and connection['name'].startswith('afec_test_')
private.mkdir(mode=0o700, parents=True)
suffix = time.strftime('%Y%m%d') + '_' + secrets.token_hex(4)
databases = {kind: 'afec_test_install_' + kind + '_' + suffix for kind in ['source', 'target', 'sentinel']}
client_environment = environment.copy()
client_environment['MYSQL_PWD'] = connection['password']
client_arguments = [mysql, '--host=' + connection['host'], '--port=' + str(connection['port']),
                    '--user=' + connection['username'], '--default-character-set=utf8mb4', '--batch', '--skip-column-names']


def sql(database, text, raw=False):
    arguments = client_arguments + (['--database=' + database] if database else [])
    result = subprocess.run(arguments, input=text.encode('utf-8'), cwd=ROOT, env=client_environment,
                            capture_output=True)
    if result.returncode:
        # Raw failed SQL can include the private account hash. Save it privately.
        (private / 'import-error.log').write_bytes(result.stderr)
        raise AssertionError('SQL failed; inspect private import-error.log')
    return result.stdout if raw else result.stdout.decode('utf-8')


for database in databases.values():
    assert database.startswith('afec_test_install_') and database.replace('_', '').isalnum()
    sql(None, 'CREATE DATABASE `' + database + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')


def private_environment(kind):
    config_file = private / (kind + '-config.php')
    # JSON -> PHP is produced by PHP itself; config paths never come from HTTP.
    template = json.dumps({'database': connection, 'app': {'environment': 'test'},
                           'session': {'cookie_secure': False}, 'storage_path': str(private / ('storage-' + kind))})
    payload = json.loads(template)
    payload['database']['name'] = databases[kind]
    encoded = json.dumps(payload).encode('utf-8').hex()
    config_file.write_text("<?php\nreturn json_decode(hex2bin('" + encoded + "'), true, 32, JSON_THROW_ON_ERROR);\n", encoding='utf-8')
    config_file.chmod(0o600)
    env = environment.copy()
    env['AFEC_CONFIG_FILE'] = str(config_file)
    return env


source_env, target_env = private_environment('source'), private_environment('target')
password = secrets.token_urlsafe(24)
password_file = private / 'admin-password.txt'
password_file.write_text(password, encoding='utf-8')
password_file.chmod(0o600)
command(source_env, 'tools/migrate.php', '--apply')
command(source_env, 'tools/create-admin.php', '--username=admin', '--name=Administrador AFEC', '--password-file=' + str(password_file))
command(source_env, 'tools/seed-recipes.php', '--user=admin', '--apply')
package = private / 'AFEC_Logistica_instalacion_inicial.sql'
metadata = json.loads(command(source_env, 'tools/export-install.php', '--output=' + str(package)))
(private / 'package-manifest.json').write_text(json.dumps(metadata, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
text = package.read_text(encoding='utf-8')
assert password not in text
if connection['password']:
    assert connection['password'] not in text
assert 'DROP TABLE' not in text.replace('-- Sin DROP TABLE', '') and 'TRUNCATE ' not in text
assert 'CREATE DATABASE' not in text and '\nUSE ' not in text
assert 'AFEC: INSTALACION COMPLETADA' in sql(databases['target'], text)


def digest(database):
    table_names = sql(database, 'SHOW TABLES;').splitlines()
    lines = [sql(database, 'SHOW CREATE TABLE `' + table + '`;', raw=True)
             + sql(database, 'SELECT * FROM `' + table + '`;', raw=True) for table in table_names]
    return hashlib.sha256(b''.join(lines)).hexdigest()


before = digest(databases['target'])
assert 'IMPORTACION OMITIDA' in sql(databases['target'], text)
assert digest(databases['target']) == before, 'Repeated import changed the installation'
sql(databases['sentinel'], "CREATE TABLE user_data (message VARCHAR(100)); INSERT INTO user_data VALUES ('Conservar información');")
before = digest(databases['sentinel'])
assert 'IMPORTACION OMITIDA' in sql(databases['sentinel'], text)
assert digest(databases['sentinel']) == before, 'Import changed an unrelated nonempty database'
assert 'No hay migraciones pendientes' in command(target_env, 'tools/migrate.php')
assert 'No hay migraciones pendientes' in command(target_env, 'tools/migrate.php', '--apply')
query = "SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM commodities), (SELECT COUNT(*) FROM facility_recipes), (SELECT COUNT(*) FROM facility_recipe_materials), (SELECT COUNT(*) FROM schema_migrations), (SELECT COUNT(*) FROM systems), (SELECT COUNT(*) FROM movements);"
assert sql(databases['target'], query).strip() == '1\t55\t71\t1418\t' + str(metadata['migrations']) + '\t0\t0'
checks = 8

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
base = 'http://127.0.0.1:' + str(port)
flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(route, method='GET', body=None, csrf=None, key=None):
    headers = {'Content-Type': 'application/json'}
    if csrf: headers['X-CSRF-Token'] = csrf
    if key: headers['Idempotency-Key'] = key
    request = urllib.request.Request(base + '/api/index.php?route=' + route, method=method,
                                    data=json.dumps(body).encode('utf-8') if body is not None else None, headers=headers)
    try: response = opener.open(request, timeout=10)
    except urllib.error.HTTPError as error: response = error
    with response: return response.status, json.loads(response.read())


with (private / 'http-server.log').open('w', encoding='utf-8') as log:
    server = subprocess.Popen([php, '-S', '127.0.0.1:' + str(port), '-t', 'public'], cwd=ROOT,
                              env=target_env, stdout=log, stderr=log, creationflags=flags)
    try:
        for _ in range(80):
            try: session = call('session')[1]['data']; break
            except (OSError, ValueError): time.sleep(0.1)
        else: raise AssertionError('HTTP server did not start')
        assert call('systems')[1]['data'] == []
        assert len(call('commodities')[1]['data']) == 55
        response = call('login', 'POST', {'username': 'admin', 'password': password}, session['csrf_token'])
        assert response[0] == 200 and response[1]['data']['user']['role'] == 'admin'
        csrf = response[1]['data']['csrf_token']
        preview = call('facility/catalogue')[1]['data']
        assert preview['new_recipes'] == 0 and preview['existing_recipes_preserved'] == 71
        recipe = call('facility/recipes')[1]['data'][0]
        recipe = call('facility/recipe&id=' + recipe['id'])[1]['data']
        system = call('systems', 'POST', {'name': 'Verificación privada del paquete'}, csrf, str(uuid.uuid4()))
        assert system[0] == 201
        system_id = system[1]['data']['id']
        installation = call('installations&system_id=' + system_id, 'POST', {'name': 'Facility de prueba',
            'facility_recipe_id': recipe['id'], 'facility_recipe_version': recipe['version']}, csrf, str(uuid.uuid4()))
        assert installation[0] == 201 and installation[1]['data']['construction_status'] == 'under_construction'
        detail = call('system&id=' + system_id)[1]['data']
        assert len(detail['requirements']) == len(recipe['materials'])
        response = call('admin/users', 'POST', {'username':'operador_paquete','display_name':'Operador de prueba',
            'role':'operator','password':secrets.token_urlsafe(24),'reason':'Validación local'}, csrf, str(uuid.uuid4()))
        assert response[0] == 201
        assert {row['role'] for row in call('admin/users')[1]['data']} == {'admin', 'operator'}
        checks += 10
    finally:
        server.terminate()
        server.wait(timeout=10)

# Fail closed if someone tries to export this source after adding business data.
sql(databases['source'], "INSERT INTO systems (name) VALUES ('No exportar este proyecto');")
refused = subprocess.run([php, 'tools/export-install.php', '--output=' + str(private / 'must-not-exist.sql')],
                         cwd=ROOT, env=source_env, capture_output=True)
assert refused.returncode == 1 and not (private / 'must-not-exist.sql').exists()
checks += 1
(private / 'ACCESO_ADMINISTRADOR.txt').write_text(
    'AFEC Logística - acceso inicial de esta instalación\n\n'
    'URL: https://logistica.afec.space/?page=login\nUsuario: admin\nContraseña: ' + password + '\n\n'
    'Usar después de importar AFEC_Logistica_instalacion_inicial.sql.\n'
    'Usuarios → Restablecer contraseña permite cambiarla y Usuarios → Crear usuario permite dar más accesos.\n'
    'Este archivo y el SQL con el hash de acceso inicial son privados y quedan fuera de Git.\n', encoding='utf-8')
(private / 'ACCESO_ADMINISTRADOR.txt').chmod(0o600)
print(json.dumps({'checks':checks, 'package':str(package), 'bytes':package.stat().st_size,
                  'sha256':hashlib.sha256(package.read_bytes()).hexdigest(), 'counts':metadata['counts']},ensure_ascii=False,indent=2))
