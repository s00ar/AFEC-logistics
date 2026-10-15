import assert from 'node:assert/strict';
import { positiveQuantity, loadRequest } from '../public/assets/js/quick-load.js';

for (const value of ['784','9007199254740993','9223372036854775807']) assert.equal(positiveQuantity(value),value);
for (const value of ['0','-1','1.2','1e3','01','9223372036854775808']) assert.throws(()=>positiveQuantity(value));
const standard=loadRequest('add-load','1','','784','');
assert.equal(standard.body.use_standard_load,true);
assert.equal(standard.body.quantity,undefined);
const manual=loadRequest('subtract','2','25','784','Fix');
assert.equal(manual.route,'quick/withdraw');
assert.equal(manual.body.quantity,'25');
assert.equal(loadRequest('subtract-load','1','','784').body.reason,'Resta de stock desde panel de material');
assert.equal(loadRequest('add','1','25',null).body.reason,undefined);
assert.throws(()=>loadRequest('add','', '25', null, ''));
console.log('16 quick-load validation, payload and precision assertions passed.');
