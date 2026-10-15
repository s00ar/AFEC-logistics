"""Persistent-login regression tests using the real HTTP/session code and synthetic accounts."""
import copy
import http.cookiejar
import json
import time


def run_session_checks(Client, check, command, env, private):
    def cookie(client):
        return next(iter(client.cookies))

    def reopen(client):
        # A new browser retains only cookies saved to disk with a future expiry.
        path = private / 'restart-cookies.txt'
        jar = http.cookiejar.MozillaCookieJar(str(path))
        for item in client.cookies:
            jar.set_cookie(copy.copy(item))
        jar.save(ignore_discard=False, ignore_expires=False)
        restored = http.cookiejar.MozillaCookieJar(str(path))
        restored.load(ignore_discard=False, ignore_expires=False)
        fresh = Client()
        for item in restored:
            fresh.cookies.set_cookie(item)
        path.unlink()
        return fresh

    for invalid in ['true', 'false', 1, 0, None, [], {}]:
        client = Client()
        status = client.call('login', 'POST', {'username': 'admin', 'password': env['AFEC_TEST_PASSWORD'], 'remember_me': invalid}, client.session()['csrf_token'])[0]
        check(status == 422, 'Remember option rejects non-boolean input: ' + repr(invalid))
        check(cookie(client).discard, 'Invalid login does not create a persistent cookie')

    failed = Client()
    check(failed.call('login', 'POST', {'username': 'admin', 'password': 'incorrect', 'remember_me': True}, failed.session()['csrf_token'])[0] == 401 and cookie(failed).discard, 'Failed login never remembers an account')
    remembered = Client()
    status, data, headers = remembered.login('admin', remember_me=True)
    saved = cookie(remembered)
    check(status == 200 and not saved.discard and 2591990 <= saved.expires - time.time() <= 2592000, 'Remembered login receives a cookie expiring in 30 days')
    check('HttpOnly' in headers.get('Set-Cookie', '') and 'SameSite=Lax' in headers.get('Set-Cookie', ''), 'Persistent cookie retains HTTP security attributes')
    check('remember-me' in Client().page('/?page=login')[1] and 'name="remember_me" type="checkbox" checked' in Client().page('/?page=login')[1], 'Login form offers persistence enabled by default')
    old_csrf = data['data']['csrf_token']
    restarted = reopen(remembered)
    check(restarted.session()['user']['id'] == data['data']['user']['id'], 'Account survives closing and reopening the browser with only disk cookies')
    check(cookie(restarted).expires == saved.expires, 'Page visits do not extend the remembered deadline')
    state = json.loads(command('tests/session-fixture.php', saved.value, 'inspect'))
    check(state['gc_maxlifetime'] >= 2592000 and state['remember_me'], 'Server retains remembered sessions for their full lifetime')
    command('tests/session-fixture.php', saved.value, 'idle')
    check(restarted.session()['user'] is not None, 'Remembered session survives the normal idle timeout')
    command('tests/session-fixture.php', saved.value, 'absolute')
    check(restarted.session()['user'] is not None, 'Remembered session survives the normal eight-hour limit')
    command('tests/session-fixture.php', saved.value, 'remember')
    check(restarted.session()['user'] is None and cookie(restarted).discard, 'Server expires remembered authentication after its absolute lifetime')

    for option in [None, False]:
        short = Client()
        short.login('admin', remember_me=option)
        check(cookie(short).discard and cookie(short).expires is None, 'Unchecked or omitted option keeps a browser-session cookie')
        check(reopen(short).session()['user'] is None, 'Short session does not survive reopening with disk cookies')
        command('tests/session-fixture.php', cookie(short).value, 'idle')
        check(short.session()['user'] is None, 'Short session retains its idle timeout')
    short = Client(); short.login('admin')
    command('tests/session-fixture.php', cookie(short).value, 'absolute')
    check(short.session()['user'] is None, 'Short session retains its absolute timeout')
    legacy = Client(); legacy.login('admin')
    command('tests/session-fixture.php', cookie(legacy).value, 'legacy')
    check(legacy.session()['user'] is None, 'Sessions created before the change retain their short timeout')

    remembered = Client(); remembered.login('admin', remember_me=True)
    replay = Client(); replay.cookies.set_cookie(copy.copy(cookie(remembered)))
    csrf = remembered.session()['csrf_token']
    check(remembered.call('logout', 'POST', {}, 'wrong')[0] == 403 and remembered.session()['user'] is not None, 'Persistent logout requires CSRF')
    check(remembered.call('logout', 'POST', {}, csrf)[0] == 200 and cookie(remembered).discard, 'Logout replaces the remembered cookie with an anonymous session')
    check(reopen(remembered).session()['user'] is None and replay.session()['user'] is None, 'Logout invalidates saved cookies and prevents replay')
    check(remembered.call('logout', 'POST', {}, old_csrf)[0] == 403, 'CSRF from an expired remembered session cannot be reused')
