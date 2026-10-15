"""Integration tests. Python is a test runner only, never a production dependency.

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
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', required=True)
parser.add_argument('--config', required=True)
parser.add_argument('--schema-ready', action='store_true', help='Schema already applied in the isolated test database; do not run the PHP migration helper')
parser.add_argument('--webroot', choices=['public', 'repository'], default='public')
parser.add_argument('--apache-root', help='Existing Apache installation; required for repository-root .htaccess verification')
args = parser.parse_args()
if args.webroot == 'repository' and not args.apache_root:
    parser.error('Repository-root tests require --apache-root; PHP built-in server ignores .htaccess.')
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
command('tests/account-inspect.php', 'reset-queue')
db_result = command('tests/database.php').strip()

class Client:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
    def call(self, route, method='GET', payload=None, token=None, raw=None, content_type='application/json', key=None):
        headers = {}
        if token is not None: headers['X-CSRF-Token'] = token
        if key is not None: headers['Idempotency-Key'] = key
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
    def resource(self,path):
        try: response = self.opener.open(BASE + path, timeout=30)
        except urllib.error.HTTPError as failure: response = failure
        with response: return response.status,response.read(),response.headers
    def session(self): return self.call('session')[1]['data']
    def login(self, username, remember_me=None):
        payload = {'username': username, 'password': env['AFEC_TEST_PASSWORD']}
        if remember_me is not None: payload['remember_me'] = remember_me
        return self.call('login', 'POST', payload, self.session()['csrf_token'])

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
    if args.webroot == 'repository':
        from apache_server import start_apache, stop_processes
        servers = start_apache(ROOT, args.apache_root, php, port, env, PRIVATE, output, flags)
    else:
        servers = [subprocess.Popen([php, '-S', f'127.0.0.1:{port}', '-t', 'public'], cwd=ROOT, env=env, stdout=output, stderr=output, creationflags=flags)]
    try:
        anonymous = Client()
        for _ in range(80):
            try: anonymous.session(); break
            except (urllib.error.URLError, OSError, json.JSONDecodeError): time.sleep(.1)
        else: raise AssertionError('HTTP test server did not become ready; inspect the private test server logs.')
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
        check(anonymous.call('systems', 'POST', {})[0] == 401, 'Public cannot write through the systems endpoint')
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
        check(anonymous.page('/config/config.local.php')[0] in [403,404] and anonymous.page('/src/bootstrap.php')[0] in [403,404], 'Private files are unavailable over HTTP')
        asset_prefix = '/public/assets/' if args.webroot == 'repository' else '/assets/'
        check(asset_prefix + 'css/app.css' in html and asset_prefix + 'js/app.js' in html, 'HTML uses assets for the selected deployment layout')
        for asset in ['css/app.css', 'js/app.js', 'js/http.js', 'js/planning.js','js/logistics.js','js/accounts.js','js/quick-load.js','js/account-security.js','js/carrier-load.js','js/commodity-search.js']:
            check(anonymous.page(asset_prefix + asset)[0] == 200, 'Application asset is reachable: ' + asset)
        logo=anonymous.resource(asset_prefix+'images/'+urllib.parse.quote('Emblema AFEC Logística Espacial.png'))
        check(logo[0]==200 and logo[1]==(ROOT/'resources/branding/afec-logistica.png').read_bytes() and logo[2].get('Content-Type','').startswith('image/png'),'Original AFEC logo is served correctly')
        if args.webroot == 'repository':
            check(anonymous.page('/index.php')[0] == 200, 'Root index.php serves the application')
            private_paths = ['/README.md', '/AGENTS.md', '/.git/HEAD', '/.env', '/.local/preview-password.txt',
                '/docs/DECISIONES.md', '/database/migrations/001_initial.sql', '/templates/layout.php',
                '/tools/preflight.php', '/tests/fixture.php', '/storage/.gitkeep', '/config/config.example.php',
                '/AFEC_Logistica_Handoff_Codex/', '/public/index.php', '/public/api/index.php?route=session',
                '/public/assets/js/unknown.js', '/public/assets/js/app.js.bak', '/api/index.php/extra', '/unknown',
                '/resources/catalogue/initial-recipes.json', '/resources/catalogue/sources/afec-research.csv',
                '/tools/seed-recipes.php', '/tools/build-recipe-catalogue.py', '/database/migrations/006_recipe_provenance.sql', '/database/migrations/007_commodity_aliases.sql',
                '/database/upgrades/AFEC_actualizar_mercancias.sql', '/database/migrations/008_commander_standard_load.sql',
                '/database/upgrades/AFEC_actualizar_paladas.sql', '/storage/password-help/1.json',
                '/storage/password-help/1.lock', '/src/Services/AccountService.php', '/tests/account-inspect.php']
            for path in private_paths:
                check(anonymous.page(path)[0] == 403, 'Apache denies non-public repository path: ' + path)
        check(anonymous.page('/?page=login')[0] == 200 and 'login-form' in anonymous.page('/?page=login')[1], 'Public site has an explicit login page')
        admin = Client()
        before = admin.session()['csrf_token']
        old_cookie = list(admin.cookies)[0].value
        status, result, headers = admin.login('admin')
        check(status == 200 and result['data']['user']['role'] == 'admin', 'Administrator can authenticate')
        check(result['data']['csrf_token'] != before and list(admin.cookies)[0].value != old_cookie, 'Login rotates session and CSRF')
        cookie = headers.get('Set-Cookie', '')
        check('HttpOnly' in cookie and 'SameSite=Lax' in cookie, 'Session cookies have required attributes')
        from session_http import run_session_checks
        run_session_checks(Client, check, command, env, PRIVATE)
        status, result, _ = admin.call('admin/users')
        check(status == 200 and len(result['data']) == 3 and 'password_hash' not in json.dumps(result), 'Admin account list excludes hashes')
        viewer = Client(); check(viewer.login('viewer', remember_me=True)[0] == 200, 'Viewer can authenticate with a remembered session')
        check(viewer.call('admin/users')[0] == 403, 'Viewer cannot bypass admin permission through API')
        operator = Client(); check(operator.login('operator')[0] == 200, 'Operator can authenticate for planning')
        check(asset_prefix + 'js/planning.js' in operator.page('/')[1], 'Operator HTML loads planning module for the selected layout')
        from planning_http import run_planning_checks
        run_planning_checks(anonymous, admin, viewer, operator, check, command, env, php, ROOT)
        from expansion_http import run_expansion_checks
        run_expansion_checks(anonymous,admin,viewer,operator,Client,check,command,env,php,ROOT)
        from recipe_http import run_recipe_checks
        run_recipe_checks(anonymous,admin,viewer,operator,check,command)
        from commodity_http import run_commodity_checks
        run_commodity_checks(anonymous,admin,viewer,operator,check,command)
        from catalogue_http import run_catalogue_checks
        run_catalogue_checks(anonymous,admin,viewer,operator,Client,check,command,env,php,ROOT)
        from commodity_http import run_commodity_race
        run_commodity_race(admin,check,env,php,ROOT)
        from quick_http import run_quick_checks
        run_quick_checks(anonymous,admin,viewer,operator,check,command,env,php,ROOT)
        from commander_planning_http import run_commander_planning_checks
        run_commander_planning_checks(anonymous,viewer,operator,check,command)
        from carrier_http import run_carrier_checks
        run_carrier_checks(anonymous,admin,viewer,operator,check,command)
        from wishlist_delivery_http import run_wishlist_delivery_checks
        run_wishlist_delivery_checks(anonymous,admin,viewer,operator,check,command,env,php,ROOT)
        from account_http import run_account_checks
        from market_http import run_market_checks
        run_market_checks(anonymous,admin,viewer,operator,check,command,ROOT,env,php)
        run_account_checks(anonymous,admin,viewer,operator,Client,check,command,env,php,ROOT)
        legacy = admin.call('admin/user&id=3')[1]['data']
        legacy_saved = admin.call('admin/user&id=3', 'PATCH', {'username':legacy['username'],'display_name':legacy['display_name'],'role':'viewer','active':True,'version':legacy['version']}, admin.session()['csrf_token'], key='normalize-legacy-role-test')
        check(legacy_saved[0] == 200 and legacy_saved[1]['data']['role'] == 'viewer' and legacy_saved[1]['data']['commander_id'] == legacy['commander_id'], 'Editing a historical operator preserves identity as Commander')
        legacy_audit = json.loads(command('tests/roles-inspect.php', 'audit'))
        check(legacy_audit['before']['role'] == 'operator' and legacy_audit['after']['role'] == 'viewer', 'Role normalization preserves actual previous value in audit')
        command('tests/fixture.php','deactivate_viewer')
        check(viewer.session()['user'] is None, 'Deactivation invalidates an existing session')
        operator = Client(); check(operator.login('operator', remember_me=True)[0] == 200, 'Operator can authenticate with a remembered session')
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
        print('Webroot checked: ' + args.webroot + (' (Apache with real .htaccess + PHP FastCGI)' if args.webroot == 'repository' else ' (PHP development server)'))
        if args.schema_ready:
            print('SQL schema applied separately; PHP migration helper was not executed.')
        else:
            print('Migration dry-run and repeated application passed.')
    finally:
        if args.webroot == 'repository':
            stop_processes(servers)
        else:
            servers[0].terminate()
            servers[0].wait(timeout=10)
        password_path.unlink(missing_ok=True)
