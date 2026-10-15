// Development-only test: browser autofill must not rename the edited account.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../public/assets/js/accounts.js', import.meta.url), 'utf8');
const { accountUpdatePayload } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const record = Object.freeze({ username: 'helirius', display_name: 'Helirius', role: 'viewer', active: true, version: '3' });
assert.deepEqual(accountUpdatePayload(record, { username: 'admin', display_name: 'Helirius', role: 'admin', active: true, reason: 'Promoción' }),
  { username: 'helirius', display_name: 'Helirius', role: 'admin', active: true, reason: 'Promoción', version: '3' });
assert.deepEqual(accountUpdatePayload(record, { role: 'admin', reason: 'Cambio de rol' }),
  { username: 'helirius', display_name: 'Helirius', role: 'admin', active: true, reason: 'Cambio de rol', version: '3' });
assert.equal(accountUpdatePayload(record, { username: 'nuevo_usuario', reason: 'Renombrar' }, true).username, 'nuevo_usuario');
assert.equal(accountUpdatePayload(record, { active: false, reason: 'Suspender' }).active, false);
assert.equal(record.username, 'helirius');
console.log('5 account payload checks passed: autofill ignored, role-only identity preserved, explicit rename and inactive state supported.');
