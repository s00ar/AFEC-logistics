import { request } from './http.js';
import { positiveQuantity } from './quick-load.js';

export function adjustedCargoQuantity(current, amount, action) {
  if (current !== '0') positiveQuantity(current);
  positiveQuantity(amount);
  if (!['add', 'subtract'].includes(action)) throw new Error('Acción de carga inválida.');
  const quantity = BigInt(current) + (action === 'add' ? BigInt(amount) : -BigInt(amount));
  if (quantity < 0n) throw new Error('No podés restar más de lo que tenés cargado en este carrier.');
  if (quantity > 9223372036854775807n) throw new Error('La carga supera el máximo permitido para este carrier.');
  return String(quantity);
}

export function cargoProgress(needed, loads, scope, commodity) {
  const loaded = loads.filter(row => row.scope === scope && row.commodity_id === commodity)
    .reduce((total, row) => total + BigInt(row.quantity), 0n);
  const remaining = BigInt(needed) > loaded ? BigInt(needed) - loaded : 0n;
  return { loaded: String(loaded), remaining: String(remaining) };
}

async function initCarrierLoads() {
  const section = document.querySelector('[data-carrier-scope]');
  if (!section) return;
  const form = section.querySelector('form');
  const status = section.querySelector('[data-carrier-status]');
  const retry = section.querySelector('[data-carrier-retry]');
  const container = section.querySelector('[data-carrier-loads]');
  const scope = section.dataset.carrierScope;
  const actor = document.querySelector('meta[name="user-id"]').content;
  // One key across both lists: an uncertain write must be resolved before switching scopes.
  const storageKey = `afec.carrier.pending.${actor}`;
  const selectionKey = `afec.carrier.selection.${actor}`;
  const materials = [...document.querySelectorAll('[data-cargo-material]')];
  let loads = [], pending = null, busy = true, blocked = false, ready = false, savedCarrier = '';
  const units = value => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  function element(tag, text, className) {
    const node = document.createElement(tag); node.textContent = text;
    if (className) node.className = className;
    return node;
  }
  function lock() {
    const locked = busy || blocked || !!pending || !ready;
    for (const control of form.elements) control.disabled = locked;
    for (const material of materials) {
      const current = selectedLoad(material.dataset.cargoMaterial);
      const otherList = current && current.scope !== scope && current.quantity !== '0';
      material.querySelector('[data-cargo-quantity]').disabled = locked || otherList;
      for (const button of material.querySelectorAll('[data-cargo-adjust]')) {
        button.disabled = locked || otherList || (button.dataset.cargoAdjust === 'subtract' && (!current || current.quantity === '0'));
      }
    }
    retry.hidden = !pending; retry.disabled = busy || blocked;
    section.querySelector('[data-carrier-refresh]').disabled = busy || blocked || !!pending;
  }
  function selectedLoad(commodity) {
    return loads.find(row => row.carrier_id === form.elements.carrier_id.value && row.commodity_id === commodity);
  }
  function message(text, commodity = null) {
    status.textContent = text;
    const material = materials.find(row => row.dataset.cargoMaterial === commodity);
    if (material) material.querySelector('[data-cargo-message]').textContent = text;
  }
  function materialProgress() {
    if (!ready) return;
    const carrier = form.elements.carrier_id;
    const carrierName = carrier.value ? carrier.selectedOptions[0].textContent : 'Carrier nuevo';
    for (const material of materials) {
      const commodity = material.dataset.cargoMaterial;
      const progress = cargoProgress(material.dataset.cargoNeeded, loads, scope, commodity);
      material.querySelector('[data-cargo-summary]').textContent = `Cargado por vos para esta lista: ${units(progress.loaded)} t · Te falta cargar: ${units(progress.remaining)} t`;
      const current = selectedLoad(commodity);
      const otherList = current && current.scope !== scope && current.quantity !== '0';
      material.querySelector('[data-cargo-carrier]').textContent = otherList
        ? `${carrierName}: este material tiene ${units(current.quantity)} t asignadas a la otra lista. Para moverlas, usá «Guardar total y repartir».`
        : `${carrierName}: ${units(current?.quantity ?? '0')} t de este material. Los botones modifican solo tu carga en este carrier.`;
    }
  }
  function propose() {
    const load = loads.find(row => row.carrier_id === form.elements.carrier_id.value && row.commodity_id === form.elements.commodity_id.value);
    form.elements.quantity.value = load?.quantity ?? '';
    const isNew = !form.elements.carrier_id.value;
    section.querySelector('[data-carrier-new]').hidden = !isNew;
    form.elements.carrier_name.required = isNew;
    materialProgress(); lock();
  }
  async function refresh() {
    ready = false;
    // Fetch both scopes for version checks when moving the same cargo to another list.
    const results = await Promise.all(['personal','fleet'].map(value => request('carrier/loads',{query:{scope:value}})));
    loads = results.flatMap(result => result.data);
    container.replaceChildren();
    for (const load of loads.filter(row => row.scope === scope && row.quantity !== '0')) {
      const card = element('article','','carrier-allocation');
      card.append(element('h3',`${load.carrier_name}${load.carrier_code ? ` (${load.carrier_code})` : ''} · ${load.commodity_name}`), element('p',`${units(load.quantity)} t a bordo`));
      const list = document.createElement('ul');
      for (const destination of load.allocations) {
        const item = document.createElement('li');
        item.append(element('span',`${destination.system_name} · ${destination.installation_name}`),element('strong',`${units(destination.allocated_quantity)} t`));
        list.append(item);
      }
      card.append(list);
      if (load.unallocated_quantity !== '0') card.append(element('p',`${units(load.unallocated_quantity)} t sin destino: exceden los faltantes de esta lista.`, 'muted'));
      container.append(card);
    }
    if (!container.children.length) container.append(element('p','Todavía no registraste carga para esta lista.','muted'));
    ready = true; materialProgress();
  }
  async function execute(job) {
    if (blocked || busy) return;
    busy = true; pending = job;
    try { sessionStorage.setItem(storageKey,JSON.stringify(job)); }
    catch { blocked = true; busy = false; message('No se pudo conservar la operación; no se envió.', job.body.commodity_id); lock(); return; }
    lock(); message('Guardando carga…', job.body.commodity_id);
    try {
      const result = await request('carrier/load',{method:'POST',body:job.body,key:job.key});
      sessionStorage.removeItem(storageKey); pending = null;
      if (job.body.carrier_name) {
        if (![...form.elements.carrier_id.options].some(option => option.value === result.data.carrier_id)) {
          form.elements.carrier_id.add(new Option(`${job.body.carrier_name}${job.body.carrier_code ? ` (${job.body.carrier_code})` : ''}`, result.data.carrier_id));
        }
        form.elements.carrier_id.value = result.data.carrier_id;
        try { sessionStorage.setItem(selectionKey, result.data.carrier_id); } catch { /* Selection is optional; pending-write recovery is mandatory. */ }
      }
      message('Carga guardada a bordo. Las entregas a settlements se registran al descargar.', job.body.commodity_id);
      try { await refresh(); propose(); } catch { message('La carga se guardó. Usá «Actualizar reparto» o recargá antes de registrar otra cantidad.', job.body.commodity_id); }
    } catch (failure) {
      if (failure.uncertain || !(failure instanceof Error)) message('Resultado pendiente. Usá «Comprobar y reintentar» para recuperar la misma operación.', job.body.commodity_id);
      else {
        try { sessionStorage.removeItem(storageKey); pending = null; } catch { blocked = true; }
        message(failure.message, job.body.commodity_id);
        if (failure.code === 'stale_version') { try { await refresh(); propose(); } catch { /* preserve error */ } }
      }
    } finally { busy = false; lock(); }
  }
  try {
    const check = storageKey + '.check'; sessionStorage.setItem(check,'ok'); sessionStorage.removeItem(check);
    pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    savedCarrier = sessionStorage.getItem(selectionKey) || '';
  } catch { blocked = true; status.textContent = 'Habilitá el almacenamiento de sesión para recuperar cargas sin duplicarlas.'; }
  for (const material of materials) {
    material.querySelector('.wishlist-cargo').hidden = false;
    material.querySelector('[data-cargo-summary]').textContent = 'Consultando tu carga a bordo…';
  }
  lock();
  try {
    const [options, commodities] = await Promise.all([request('logistics/options'),request('commodities')]);
    for (const carrier of options.data.carriers) form.elements.carrier_id.add(new Option(`${carrier.name}${carrier.code ? ` (${carrier.code})` : ''}`,carrier.id));
    for (const commodity of commodities.data) form.elements.commodity_id.add(new Option(commodity.name_es,commodity.id));
    await refresh();
    // Preserve selection of saved carriers even beyond the options page.
    for (const load of loads) if (![...form.elements.carrier_id.options].some(option => option.value === load.carrier_id)) form.elements.carrier_id.add(new Option(load.carrier_name,load.carrier_id));
    if ([...form.elements.carrier_id.options].some(option => option.value === savedCarrier)) form.elements.carrier_id.value = savedCarrier;
    propose();
    if (pending) status.textContent = 'Hay una carga pendiente de confirmar. Comprobá y reintentá la misma operación.';
  } catch (failure) { blocked = true; status.textContent = `${failure.message} Recargá para volver a cargar los datos.`; }
  busy = false; lock();
  form.elements.carrier_id.addEventListener('change',() => {
    try { sessionStorage.setItem(selectionKey, form.elements.carrier_id.value); } catch { /* Preserve operation recovery even if selection is not remembered. */ }
    propose();
  });
  form.elements.commodity_id.addEventListener('change',propose);
  form.elements.quantity.addEventListener('keydown',event => { if (['e','E','+','-','.',','].includes(event.key)) event.preventDefault(); });
  form.elements.quantity.addEventListener('paste',event => { if (!/^[0-9]+$/.test(event.clipboardData.getData('text'))) event.preventDefault(); });
  form.addEventListener('submit',event => {
    event.preventDefault(); if (busy || blocked || pending || !ready || !form.reportValidity()) return;
    try {
      const quantity = form.elements.quantity.value; if (quantity !== '0') positiveQuantity(quantity);
      const carrier = form.elements.carrier_id.value;
      const commodity = form.elements.commodity_id.value;
      const version = loads.find(row => row.carrier_id === carrier && row.commodity_id === commodity)?.version || '0';
      const body = {scope,commodity_id:commodity,quantity,version,...(carrier ? {carrier_id:carrier} : {carrier_name:form.elements.carrier_name.value,carrier_code:form.elements.carrier_code.value})};
      execute({body,key:crypto.randomUUID()});
    } catch (failure) { status.textContent = failure.message; }
  });
  function adjust(material, action) {
    if (busy || blocked || pending || !ready) return;
    const commodity = material.dataset.cargoMaterial;
    const input = material.querySelector('[data-cargo-quantity]');
    if (!input.reportValidity()) return;
    try {
      const current = selectedLoad(commodity);
      if (current && current.scope !== scope && current.quantity !== '0') throw new Error('La carga de este material pertenece a la otra lista. Movela expresamente desde el formulario de total.');
      const carrier = form.elements.carrier_id.value;
      if (!carrier && !form.elements.carrier_name.reportValidity()) {
        message('Indicá arriba el nombre del carrier nuevo o elegí uno registrado.', commodity); return;
      }
      const quantity = adjustedCargoQuantity(current?.quantity ?? '0', input.value, action);
      const body = {scope, commodity_id: commodity, quantity, version: current?.version ?? '0',
        ...(carrier ? {carrier_id: carrier} : {carrier_name: form.elements.carrier_name.value, carrier_code: form.elements.carrier_code.value})};
      execute({body, key: crypto.randomUUID()});
    } catch (failure) { message(failure.message, commodity); }
  }
  for (const material of materials) {
    for (const button of material.querySelectorAll('[data-cargo-adjust]')) button.addEventListener('click', () => adjust(material, button.dataset.cargoAdjust));
    material.querySelector('[data-cargo-quantity]').addEventListener('keydown', event => {
      if (event.key === 'Enter') { event.preventDefault(); adjust(material, 'add'); }
    });
  }
  retry.addEventListener('click',() => { if (pending) execute(pending); });
  section.querySelector('[data-carrier-refresh]').addEventListener('click',async () => {
    if (busy || blocked || pending) return;
    busy = true; lock();
    try { await refresh(); status.textContent = 'Reparto actualizado.'; }
    catch (failure) { status.textContent = failure.message; }
    finally { busy = false; lock(); }
  });
}

if (typeof document !== 'undefined') initCarrierLoads();
