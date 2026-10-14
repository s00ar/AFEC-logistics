"""Phase-1 integration tests. Python is a test runner only, never a production dependency.

Requires a local MySQL/MariaDB database named afec_test_* and a private test PHP config.
No production or Hostinger credentials are used by these tests.
"""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
parser.add_argument('--config', required=True)
parser.add_argument('--schema-ready', action='store_true', help='Schema already applied in the isolated test database; do not run the PHP migration helper')
args = parser.parse_args()
env = os.environ.copy()
env['AFEC_CONFIG_FILE'] = str(Path(args.config).resolve())
env['AFEC_ENV'] = 'test'
env['AFEC_TEST_PASSWORD'] = secrets.token_urlsafe(24)
PRIVATE = ROOT / '.local' / 'test-artifacts'
PRIVATE.mkdir(parents=True, exist_ok=True)
password_path = PRIVATE / 'password.txt'
password_path.write_text(env['AFEC_TEST_PASSWORD'], encoding='utf-8')
php = str(Path(args.php).resolve())

def command(*arguments):
    result = subprocess.run([php, *arguments], cwd=ROOT, env=env, capture_output=True, text=True, encoding='utf-8')
    if result.returncode:
        raise AssertionError(f'Command failed: {arguments[0]}\n{result.stderr}')
    return result.stdout

# Guard is executed before migration or reset. This read-only guard must pass first.
guard = r"$a=require 'src/bootstrap.php';$c=$a->config;if($c['app']['environment']!=='test'||!in_array($c['database']['host'],['localhost','127.0.0.1'],true)||!str_starts_with($c['database']['name'],'afec_test_')){exit(2);}echo 'guard-ok';"
assert command('-r', guard) == 'guard-ok'
if not args.schema_ready:
    if not (ROOT / 'tools/migrate.php').is_file():
        raise RuntimeError('Migration helper unavailable. Apply reviewed SQL to the LOCAL TEST database and use --schema-ready.')
    dry_run = command('tools/migrate.php')
    assert 'no se escribió ningún dato' in dry_run
    command('tools/migrate.php', '--apply')
    assert 'No hay migraciones pendientes' in command('tools/migrate.php', '--apply')
command('tests/fixture.php', 'reset')
command('tools/create-admin.php', '--username=admin', '--name=Administrador de pruebas', f'--password-file={password_path}')
command('tests/fixture.php', 'seed')
db_result = command('tests/database.php').strip()

class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
    def call(self, route, method='GET', payload=None, token=None, raw=None, content_type='application/json'):
        headers = {}
        if token is not None: headers['X-CSRF-Token'] = token
        body = raw if raw is not None else (json.dumps(payload).encode() if payload is not None else None)
        if body is not None: headers['Content-Type'] = content_type
        req = urllib.request.Request(BASE + '/api/index.php?route=' + route, data=body, headers=headers, method=method)
        try: response = self.opener.open(req, timeout=10)
        except urllib.error.HTTPError as failure: response = failure
        with response:
            result = json.loads(response.read())
            return response.status, result, response.headers
    def page(self, path):
        try: response = self.opener.open(BASE + path, timeout=10)
        except urllib.error.HTTPError as failure: response = failure
        with response: return response.status, response.read().decode(), response.headers
    def session(self): return self.call('session')[1]['data']
    def login(self, username):
        return self.call('login', 'POST', {'username': username, 'password': env['AFEC_TEST_PASSWORD']}, self.session()['csrf_token'])

with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
BASE = f'http://127.0.0.1:{port}'
flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
checks = 0
def check(value, description):
    global checks
    if not value: raise AssertionError(description)
    checks += 1

with (PRIVATE / 'http-server.log').open('w') as output:
    server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', '-t', 'public'], cwd=ROOT, env=env, stdout=output, stderr=output, creationflags=flags)
    try:
        anonymous = Client()
        for _ in range(80):
            try: anonymous.session(); break
            except (urllib.error.URLError, OSError): time.sleep(.1)
        else: raise AssertionError('PHP server did not start')
        check(anonymous.session()['user'] is None, 'Initial session is anonymous')
        status, result, _ = anonymous.call('systems')
        check(status == 200 and len(result['data']) == 5, 'Public can read systems without login')
        first = result['data'][0] if result['data'][0]['id'] == '1' else next(x for x in result['data'] if x['id'] == '1')
        check(first['required_quantity'] == '300' and first['covered_quantity'] == '125' and first['remaining_quantity'] == '175', 'Independent requirements aggregate correctly, archived installation excluded')
        check(first['percent'] == 41.67 and first['surplus_quantity'] == '100', 'Oversupply does not conceal another material deficit')
        no_targets = next(x for x in result['data'] if x['id'] == '3')
        check(no_targets['percent'] is None, 'No objectives has no invented percentage')
        negative = next(x for x in result['data'] if x['id'] == '4')
        check(negative['negative_balances'] == 1 and negative['delivered_quantity'] == '-3', 'Negative source balance remains visible')
        large = next(x for x in result['data'] if x['id'] == '5')
        check(large['required_quantity'] == '9007199254740993' and isinstance(large['required_quantity'], str), 'Quantities preserve precision beyond JavaScript Number')
        status, result, _ = anonymous.call('system&id=1')
        check(status == 200 and len(result['data']['installations']) == 2, 'Public installation/material detail is available')
        check(result['data']['installations'][0]['percent'] == 50 and result['data']['installations'][1]['percent'] == 25, 'Same commodity in different installations keeps separate balances')
        check(anonymous.call('commodities')[0] == 200 and len(anonymous.call('commodities')[1]['data']) == 3, 'Public catalog excludes inactive entries')
        check(anonymous.call('admin/users')[0] == 401, 'Anonymous cannot read account administration')
        check(anonymous.call('system&id=999999')[0] == 404, 'Unknown system returns JSON 404')
        check(anonymous.call('system&id=1%20OR%201=1')[0] == 422, 'Identifier SQL injection is rejected')
        check(anonymous.call('systems', 'POST', {})[0] == 405, 'Public cannot write through a read endpoint')
        check(anonymous.call('login', 'GET')[0] == 405, 'Login rejects GET')
        check(anonymous.call('login', 'POST', {'username':'admin','password':'wrong'})[0] == 403, 'Login requires CSRF')
        token = anonymous.session()['csrf_token']
        check(anonymous.call('login', 'POST', token=token, raw=b'{invalid')[0] == 400, 'Invalid JSON rejected')
        check(anonymous.call('login', 'POST', token=token, raw=b'[]')[0] == 400, 'Array payload rejected')
        check(anonymous.call('login', 'POST', token=token, raw=b'{}', content_type='text/plain')[0] == 415, 'Unexpected content type rejected')
        check(anonymous.call('login', 'POST', token=token, raw=b' ' * 16385)[0] == 413, 'Oversized payload rejected')
        status, html, headers = anonymous.page('/?page=system&id=1')
        check(status == 200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html, 'Stored name is escaped in HTML')
        check('frame-ancestors' in headers.get('Content-Security-Policy',''), 'CSP present')
        check(anonymous.page('/config/config.local.php')[0] == 404 and anonymous.page('/src/bootstrap.php')[0] == 404, 'Private files are outside document root')
        check(anonymous.page('/?page=login')[0] == 200 and 'login-form' in anonymous.page('/?page=login')[1], 'Public site has an explicit login page')
        admin = Client()
        before = admin.session()['csrf_token']
        old_cookie = list(admin.cookies)[0].value
        status, result, headers = admin.login('admin')
        check(status == 200 and result['data']['user']['role'] == 'admin', 'Administrator can authenticate')
        check(result['data']['csrf_token'] != before and list(admin.cookies)[0].value != old_cookie, 'Login rotates session and CSRF')
        cookie = headers.get('Set-Cookie', '')
        check('HttpOnly' in cookie and 'SameSite=Lax' in cookie, 'Session cookies have required attributes')
        status, result, _ = admin.call('admin/users')
        check(status == 200 and len(result['data']) == 3 and 'password_hash' not in json.dumps(result), 'Admin account list excludes hashes')
        viewer = Client(); check(viewer.login('viewer')[0] == 200, 'Viewer can authenticate')
        check(viewer.call('admin/users')[0] == 403, 'Viewer cannot bypass admin permission through API')
        command('tests/fixture.php','deactivate_viewer')
        check(viewer.session()['user'] is None, 'Deactivation invalidates an existing session')
        operator = Client(); check(operator.login('operator')[0] == 200, 'Operator can authenticate')
        check(operator.call('admin/users')[0] == 403, 'Operator cannot administer users')
        command('tests/fixture.php','revoke_operator')
        check(operator.session()['user'] is None, 'Auth version revokes an existing session')
        current = admin.session()['csrf_token']
        check(admin.call('logout','POST',{},'wrong')[0] == 403, 'Logout requires CSRF')
        check(admin.call('logout','POST',{},current)[0] == 200 and admin.session()['user'] is None, 'Logout clears authentication')
        denied = Client(); token = denied.session()['csrf_token']
        failures = [denied.call('login','POST',{'username':'missing-user','password':'wrong'},token)[0] for _ in range(9)]
        check(failures[:8] == [401]*8 and failures[8] == 429, 'Repeated failures are throttled without revealing account existence')
        print(db_result)
        print(f'HTTP checks passed: {checks}')
        if args.schema_ready:
            print('SQL schema applied separately; PHP migration helper was not executed.')
        else:
            print('Migration dry-run and repeated application passed.')
    finally:
        server.terminate()
        server.wait(timeout=10)
        password_path.unlink(missing_ok=True)
