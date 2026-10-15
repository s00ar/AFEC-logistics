import { request } from './http.js';
import { positiveQuantity } from './quick-load.js';

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
  let loads = [], pending = null, busy = true, blocked = false;
  const units = value => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  function element(tag, text, className) {
    const node = document.createElement(tag); node.textContent = text;
    if (className) node.className = className;
    return node;
  }
  function lock() {
    for (const control of form.elements) control.disabled = busy || blocked || !!pending;
    retry.hidden = !pending; retry.disabled = busy || blocked;
    section.querySelector('[data-carrier-refresh]').disabled = busy || blocked || !!pending;
  }
  function propose() {
    const load = loads.find(row => row.carrier_id === form.elements.carrier_id.value && row.commodity_id === form.elements.commodity_id.value);
    form.elements.quantity.value = load?.quantity ?? '';
    const isNew = !form.elements.carrier_id.value;
    section.querySelector('[data-carrier-new]').hidden = !isNew;
    form.elements.carrier_name.required = isNew;
  }
  async function refresh() {
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
  }
  async function execute(job) {
    if (blocked || busy) return;
    busy = true; pending = job;
    try { sessionStorage.setItem(storageKey,JSON.stringify(job)); }
    catch { blocked = true; busy = false; status.textContent = 'No se pudo conservar la operación; no se envió.'; lock(); return; }
    lock(); status.textContent = 'Guardando carga…';
    try {
      const result = await request('carrier/load',{method:'POST',body:job.body,key:job.key});
      sessionStorage.removeItem(storageKey); pending = null;
      if (job.body.carrier_name) {
        const option = new Option(`${job.body.carrier_name}${job.body.carrier_code ? ` (${job.body.carrier_code})` : ''}`,result.data.carrier_id);
        form.elements.carrier_id.add(option); form.elements.carrier_id.value = result.data.carrier_id;
      }
      status.textContent = 'Carga guardada. Reparto calculado según los faltantes actuales de la lista.';
      try { await refresh(); propose(); } catch { status.textContent = 'La carga se guardó. Recargá para ver el reparto actualizado sin volver a enviar el total.'; }
    } catch (failure) {
      if (failure.uncertain || !(failure instanceof Error)) status.textContent = 'Resultado pendiente. Usá «Comprobar y reintentar» para recuperar la misma operación.';
      else {
        try { sessionStorage.removeItem(storageKey); pending = null; } catch { blocked = true; }
        status.textContent = failure.message;
        if (failure.code === 'stale_version') { try { await refresh(); propose(); } catch { /* preserve error */ } }
      }
    } finally { busy = false; lock(); }
  }
  try {
    const check = storageKey + '.check'; sessionStorage.setItem(check,'ok'); sessionStorage.removeItem(check);
    pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (pending) section.closest('details')?.setAttribute('open', '');
  } catch { blocked = true; status.textContent = 'Habilitá el almacenamiento de sesión para recuperar cargas sin duplicarlas.'; }
  lock();
  try {
    const [options, commodities] = await Promise.all([request('logistics/options'),request('commodities')]);
    for (const carrier of options.data.carriers) form.elements.carrier_id.add(new Option(`${carrier.name}${carrier.code ? ` (${carrier.code})` : ''}`,carrier.id));
    for (const commodity of commodities.data) form.elements.commodity_id.add(new Option(commodity.name_es,commodity.id));
    await refresh();
    // Preserve selection of saved carriers even beyond the options page.
    for (const load of loads) if (![...form.elements.carrier_id.options].some(option => option.value === load.carrier_id)) form.elements.carrier_id.add(new Option(load.carrier_name,load.carrier_id));
    if (pending) status.textContent = 'Hay una carga pendiente de confirmar. Comprobá y reintentá la misma operación.';
  } catch (failure) { blocked = true; status.textContent = `${failure.message} Recargá para volver a cargar los datos.`; }
  busy = false; lock();
  form.elements.carrier_id.addEventListener('change',propose);
  form.elements.commodity_id.addEventListener('change',propose);
  form.elements.quantity.addEventListener('keydown',event => { if (['e','E','+','-','.',','].includes(event.key)) event.preventDefault(); });
  form.elements.quantity.addEventListener('paste',event => { if (!/^[0-9]+$/.test(event.clipboardData.getData('text'))) event.preventDefault(); });
  form.addEventListener('submit',event => {
    event.preventDefault(); if (busy || blocked || pending || !form.reportValidity()) return;
    try {
      const quantity = form.elements.quantity.value; if (quantity !== '0') positiveQuantity(quantity);
      const carrier = form.elements.carrier_id.value;
      const commodity = form.elements.commodity_id.value;
      const version = loads.find(row => row.carrier_id === carrier && row.commodity_id === commodity)?.version || '0';
      const body = {scope,commodity_id:commodity,quantity,version,...(carrier ? {carrier_id:carrier} : {carrier_name:form.elements.carrier_name.value,carrier_code:form.elements.carrier_code.value})};
      execute({body,key:crypto.randomUUID()});
    } catch (failure) { status.textContent = failure.message; }
  });
  retry.addEventListener('click',() => { if (pending) execute(pending); });
  section.querySelector('[data-carrier-refresh]').addEventListener('click',async () => {
    if (busy || blocked || pending) return;
    busy = true; lock();
    try { await refresh(); status.textContent = 'Reparto actualizado.'; }
    catch (failure) { status.textContent = failure.message; }
    finally { busy = false; lock(); }
  });
}

initCarrierLoads();
