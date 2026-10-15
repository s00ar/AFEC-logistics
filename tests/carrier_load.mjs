import assert from 'node:assert/strict';
import { adjustedCargoQuantity, cargoProgress } from '../public/assets/js/carrier-load.js';

let count = 0;
function equal(actual, expected) { assert.deepEqual(actual, expected); count++; }
function rejects(...args) { assert.throws(() => adjustedCargoQuantity(...args)); count++; }

const first = adjustedCargoQuantity('0', '784', 'add');
const second = adjustedCargoQuantity(first, '784', 'add');
equal(first, '784'); equal(second, '1568');
equal(adjustedCargoQuantity(second, '25', 'subtract'), '1543');
equal(adjustedCargoQuantity('25', '25', 'subtract'), '0');
equal(adjustedCargoQuantity('9007199254740993', '784', 'add'), '9007199254741777');
equal(adjustedCargoQuantity('9223372036854775806', '1', 'add'), '9223372036854775807');
rejects('25', '26', 'subtract');
rejects('9223372036854775807', '1', 'add');
rejects('0', '1', 'delete');
for (const bad of ['', '0', '-1', '1.5', '1e3', '01', '9223372036854775808']) rejects('10', bad, 'add');
const loads = [
  {scope:'personal', commodity_id:'1', quantity:'1000'},
  {scope:'personal', commodity_id:'1', quantity:'500'},
  {scope:'fleet', commodity_id:'1', quantity:'700'},
  {scope:'personal', commodity_id:'2', quantity:'9000'},
];
equal(cargoProgress('3000', loads, 'personal', '1'), {loaded:'1500', remaining:'1500'});
equal(cargoProgress('100', loads, 'fleet', '1'), {loaded:'700', remaining:'0'});
equal(cargoProgress('30', loads, 'personal', '3'), {loaded:'0', remaining:'30'});
equal(cargoProgress('18446744073709551615', [
  {scope:'personal', commodity_id:'1', quantity:'9223372036854775807'},
  {scope:'personal', commodity_id:'1', quantity:'9223372036854775807'},
], 'personal', '1'), {loaded:'18446744073709551614', remaining:'1'});
console.log(`${count} cargo increment, bounds, scope and progress checks passed.`);
