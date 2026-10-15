"""Own passwords and private administrator alerts, with no email or schema changes."""
import json
import os
from pathlib import Path
import subprocess
import time
import uuid


def run_account_checks(anonymous, admin, viewer, operator, Client, check, command, env, php, root):
    def write(client, route, body, key=None):
        return client.call(route, 'POST', body, client.session()['csrf_token'], key=key or str(uuid.uuid4()))
    def expect(response, code, label):
        check(response[0] == code, label + ' (status ' + str(response[0]) + ')')
        return response[1].get('data')
    def pending():
        return expect(admin.call('admin/password-help'), 200, 'Administrator reads request queue')
    def create(username, role='viewer'):
        return expect(write(admin, 'admin/users', {'username':username,'display_name':'Synthetic ' + username,'role':role,'password':env['AFEC_TEST_PASSWORD'],'reason':'Account security test'}), 201, 'Create synthetic security account')
    def login(client, username, password):
        return write(client, 'login', {'username':username,'password':password,'remember_me':True})
    def stats(uid): return json.loads(command('tests/account-inspect.php','stats',uid))
    def change(client, current, new, key=None):
        return write(client, 'account/password', {'current_password':current,'new_password':new,'confirmation':new}, key)

    check(anonymous.page('/?page=password-help')[0] == 200 and 'password-help-form' in anonymous.page('/?page=password-help')[1], 'Public help page is reachable')
    check('page=password-help' in anonymous.page('/?page=login')[1], 'Login links to password help')
    check(viewer.page('/?page=account')[0] == 200 and 'own-password-form' in viewer.page('/?page=account')[1], 'Every account has a self-service password form')
    check(anonymous.call('account/password', 'POST', {})[0] == 401, 'Anonymous cannot change a password')
    for client, code in [(anonymous,401),(viewer,403),(operator,403)]:
        expect(client.call('admin/password-help'), code, 'Request queue is restricted to administrators')
        check(client.page('/?page=password-requests')[0] == code, 'Request HTML is restricted to administrators')
    expect(anonymous.call('password/help', 'GET'), 405, 'Help submission rejects GET')
    expect(anonymous.call('password/help','POST',{'username':'admin'}), 403, 'Help submission requires CSRF')
    expect(write(anonymous,'password/help',{'username':'x OR 1=1'}), 422, 'Help rejects invalid usernames')
    expect(write(anonymous,'password/help',{'username':'admin','user_id':'1'}), 422, 'Help rejects forged fields')
    expect(viewer.call('account/password', 'GET'), 405, 'Own password change rejects GET')
    expect(viewer.call('account/password', 'POST', {}), 403, 'Own password change requires CSRF')

    account = create('account_help_test'); uid = account['id']; original = env['AFEC_TEST_PASSWORD']
    owner = Client(); other = Client(); expect(login(owner,account['username'],original),200,'Owner authenticates'); expect(login(other,account['username'],original),200,'Second owner session authenticates')
    before = stats(uid)
    known = expect(write(anonymous,'password/help',{'username':account['username']}),200,'Owner can request help without signing in')
    unknown = expect(write(anonymous,'password/help',{'username':'unknown_security_account'}),200,'Unknown account receives the same public response')
    check(known == unknown and 'request_id' not in known, 'Public help does not disclose account existence or request identifiers')
    request = next(x for x in pending() if x['id'] == uid)
    expect(write(anonymous,'password/help',{'username':account['username'].upper()}),200,'Repeated and case-varied help is acknowledged')
    check(next(x for x in pending() if x['id'] == uid) == request, 'Repeated help preserves a single pending request and its timestamp')
    check(stats(uid) == before and other.session()['user']['id'] == uid and login(Client(),account['username'],original)[0] == 200, 'Help never changes credentials, versions or existing sessions')
    check('password-help-alert' in admin.page('/')[1] and 'password-help-alert' not in viewer.page('/')[1], 'Only administrators see the pending alert')
    arriving = Client(); expect(arriving.login('admin'),200,'Administrator signs in with pending requests')
    check('password-help-alert' in arriving.page('/')[1], 'Alert appears immediately after administrator login')
    html = admin.page('/?page=password-requests')[1]
    check('Restablecer contraseña' in html and 'data-action="user-password"' in html and account['username'] in html, 'Requests offer the existing administrator reset flow')
    check(original not in json.dumps(pending()) and 'password_hash' not in json.dumps(pending()), 'Request list never exposes credentials')
    close = {'request_id':request['request_id'],'reason':'Verified synthetic request'}
    expect(write(viewer,'admin/password-help/close&id='+uid,close),403,'Commander cannot close a request')
    expect(admin.call('admin/password-help/close&id='+uid,'POST',close),403,'Closing requests requires CSRF')
    expect(write(admin,'admin/password-help/close&id='+uid,close | {'request_id':'0'*32}),409,'Closing a stale request is rejected')
    key = str(uuid.uuid4()); expect(write(admin,'admin/password-help/close&id='+uid,close,key),200,'Administrator can mark a request attended')
    check(write(admin,'admin/password-help/close&id='+uid,close,key)[1]['replayed'], 'Closing a request is idempotent')
    check(not any(x['id'] == uid for x in pending()) and stats(uid) == before, 'Closing an alert leaves the password unchanged')
    expect(write(anonymous,'password/help',{'username':account['username']}),200,'User can request help again after closure')
    fresh = next(x for x in pending() if x['id'] == uid)
    check(fresh['request_id'] != request['request_id'], 'A new request has a new identity')
    expect(write(admin,'admin/password-help/close&id='+uid,close),409,'Old request cannot close a newer one')
    replacement = original + 'reset'
    expect(write(admin,'admin/user/password&id='+uid,{'password':replacement,'version':account['version'],'reason':'Manual reset following help'}),200,'Administrator resets password through the existing endpoint')
    check(not any(x['id'] == uid for x in pending()), 'Administrator password reset automatically resolves the notification')
    check(other.session()['user'] is None and login(Client(),account['username'],original)[0] == 401 and login(owner,account['username'],replacement)[0] == 200, 'Manual reset revokes old sessions and old credentials')

    for role in ['viewer','operator','admin']:
        account = create('own_password_' + role, role); uid = account['id']; owner = Client(); other = Client()
        expect(login(owner,account['username'],original),200,'Own-password account authenticates')
        expect(login(other,account['username'],original),200,'Independent remembered session authenticates')
        new = original + role
        expect(change(owner,'wrong-current',new),403,'Wrong current password is rejected for ' + role)
        expect(change(owner,original,'short'),422,'Weak new password is rejected')
        expect(write(owner,'account/password',{'current_password':original,'new_password':new,'confirmation':'different'}),422,'Confirmation mismatch is rejected')
        expect(write(owner,'account/password',{'current_password':original,'new_password':new,'confirmation':new,'user_id':'1'}),422,'Own password cannot target another account')
        before = stats(uid); old_csrf = owner.session()['csrf_token']; old_cookie = next(iter(owner.cookies)).value
        if role == 'operator':
            command('tests/expansion-inspect.php','fail_audit')
            try:
                expect(change(owner,original,new),503,'Audit failure prevents a partial password change')
            finally:
                command('tests/expansion-inspect.php','clear_failure')
            check(stats(uid) == before and login(Client(),account['username'],original)[0] == 200 and other.session()['user'] is not None, 'Failed password transaction preserves credentials, version and sessions')
        key = str(uuid.uuid4()); result = change(owner,original,new,key)
        expect(result,200,'Every role can change its own password: ' + role)
        check(result[1]['data']['csrf_token'] != old_csrf and next(iter(owner.cookies)).value != old_cookie, 'Own change rotates session ID and CSRF')
        check(not next(iter(owner.cookies)).discard and owner.session()['user']['id'] == uid, 'Current login and its remember preference are preserved')
        check(other.session()['user'] is None, 'Own change revokes other remembered sessions')
        check(login(Client(),account['username'],original)[0] == 401 and login(Client(),account['username'],new)[0] == 200, 'Only the new password authenticates')
        check(change(owner,original,new,key)[1]['replayed'], 'Same password-change request replays without changing credentials again')
        after = stats(uid)
        check(int(after['auth_version']) == int(before['auth_version'])+1 and after['password_changes'] == before['password_changes']+1, 'Own change and replay create only one credential update and audit')
        expect(change(owner,original,new+'other',key),409,'Password-change key cannot be reused for another password')
        receipt = owner.call('operation&scope=account.password&key='+key)[1]['data']
        check(original not in json.dumps(receipt) and new not in json.dumps(receipt) and 'password_hash' not in json.dumps(receipt), 'Own password receipt does not contain secrets')
        check(viewer.call('operation&scope=account.password&key='+key)[1]['data'] is None, 'Another account cannot read the password-change receipt')
        if role == 'viewer':
            attempts = [change(owner,'incorrect-current',new+'blocked')[0] for _ in range(9)]
            check(429 in attempts and attempts[-1] == 429, 'Current-password guessing is rate limited')
            check(login(Client(),account['username'],new)[0] == 200, 'Own-password throttle does not consume the login throttle')

    command('tests/account-inspect.php','clear-help-throttle')
    account = create('help_race_account'); directory = root / '.local/test-artifacts' / ('help-race-' + str(uuid.uuid4())); directory.mkdir()
    barrier = directory / 'go'; processes = []
    try:
        for index in range(2):
            path = directory / (str(index)+'.json'); path.write_text(json.dumps({'username':account['username'],'barrier':str(barrier)}),encoding='utf-8')
            processes.append(subprocess.Popen([php,'tests/account-inspect.php','request-worker',str(path)],cwd=root,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8',creationflags=subprocess.CREATE_NO_WINDOW if os.name=='nt' else 0))
        deadline = time.monotonic()+10
        while not all((directory/(str(i)+'.json.ready')).exists() for i in range(2)):
            if time.monotonic()>deadline: raise AssertionError('Request workers did not reach the barrier')
            time.sleep(.02)
        barrier.write_text('go')
        replies = []
        for process in processes:
            out,err = process.communicate(timeout=15)
            check(process.returncode == 0, 'Independent request worker completed: ' + err)
            replies.append(json.loads(out))
        check(replies[0] == replies[1] and len([x for x in pending() if x['id'] == account['id']]) == 1, 'Concurrent requests create one intact persistent notification')
    finally:
        for process in processes:
            if process.poll() is None: process.terminate(); process.wait(timeout=10)
    for index in range(12):
        expect(write(anonymous,'password/help',{'username':'unregistered_help_'+str(index)}),200,'Rate-limited help still returns the generic acknowledgement')
    account = create('help_limited_account')
    expect(write(anonymous,'password/help',{'username':account['username']}),200,'Help flood does not reveal throttling or account existence')
    check(not any(x['id'] == account['id'] for x in pending()), 'Per-address limit prevents new queued requests after a flood')
