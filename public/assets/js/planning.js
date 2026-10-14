import { request } from './http.js';

const home = document.querySelector('meta[name="home-url"]').content;
const actorId = document.querySelector('meta[name="user-id"]').content;
const storageKey = `afec.pending.planning.${actorId}`;
const dialog = document.querySelector('#planning-dialog');
const form = document.querySelector('#planning-form');
const fields = document.querySelector('#editor-fields');
const errorBox = document.querySelector('#planning-error');
const submit = document.querySelector('#planning-submit');
const banner = document.querySelector('#pending-operation');
const reloadButton = document.querySelector('#reload-planning');
const definitions = {
  'system-create': { template: 'system', title: 'Crear sistema', route: 'systems', method: 'POST', fields: ['name', 'description', 'reason'] },
  'system-edit': { template: 'system', title: 'Editar sistema', route: 'system', method: 'PATCH', fields: ['name', 'description', 'reason'], query: r => ({ id: r.id }), edit: true },
  'system-archive': { template: 'archive', title: 'Archivar sistema', route: 'system', method: 'DELETE', query: r => ({ id: r.id }), archive: true },
  'system-restore': { template: 'archive', title: 'Restaurar sistema', route: 'system/restore', method: 'POST', query: r => ({ id: r.id }) },
  'installation-create': { template: 'installation', title: 'Agregar instalación', route: 'installations', method: 'POST', fields: ['name', 'body_name', 'location_label', 'tier', 'type', 'state', 'sort_order', 'reason'], query: r => ({ system_id: r.system_id }) },
  'installation-edit': { template: 'installation', title: 'Editar instalación', route: 'installation', method: 'PATCH', fields: ['name', 'body_name', 'location_label', 'tier', 'type', 'state', 'sort_order', 'reason'], query: r => ({ id: r.id }), edit: true },
  'installation-archive': { template: 'archive', title: 'Archivar instalación', route: 'installation', method: 'DELETE', query: r => ({ id: r.id }), archive: true },
  'installation-restore': { template: 'archive', title: 'Restaurar instalación', route: 'installation/restore', method: 'POST', query: r => ({ id: r.id }) },
  'requirement-create': { template: 'requirement', title: 'Agregar objetivo', route: 'requirement', method: 'PUT' },
  'requirement-edit': { template: 'requirement', title: 'Editar objetivo', route: 'requirement', method: 'PUT', edit: true },
  'requirement-archive': { template: 'archive', title: 'Retirar objetivo', route: 'requirement', method: 'DELETE', query: r => ({ installation_id: r.installation_id, commodity_id: r.commodity_id }), archive: true },
  'commodity-create': { template: 'commodity', title: 'Agregar material al catálogo', route: 'commodities', method: 'POST', fields: ['code', 'name_es', 'name_en'] },
};
let active = null;
let pending = null;
let storageBlocked = false;
let busy = false;
let editorGeneration = 0;

function toast(message, success = false) {
  const box = document.querySelector('#global-message');
  box.textContent = message;
  box.classList.toggle('is-success', success);
  box.hidden = false;
}

function queryFor(definition, record, body) {
  return definition.template === 'requirement'
    ? { installation_id: record.installation_id, commodity_id: record.commodity_id || body.commodity_id }
    : definition.query?.(record) || {};
}

function readPending() {
  try {
    const saved = sessionStorage.getItem(storageKey);
    if (saved === null) return null;
    const value = JSON.parse(saved);
    if (!value) throw new Error();
    if (!Object.hasOwn(definitions, value.action) || !value.record || !value.request?.body || value.actor_id !== actorId) throw new Error();
    const definition = definitions[value.action];
    if (value.request.route !== definition.route || value.request.method !== definition.method || !/^[A-Za-z0-9_-]{16,100}$/.test(value.request.key)) throw new Error();
    // Requests from storage remain restricted to this action's destination and the authenticated actor.
    if (JSON.stringify(queryFor(definition, value.record, value.request.body)) !== JSON.stringify(value.request.query)) throw new Error();
    return value;
  } catch {
    storageBlocked = true;
    toast('No se pudo leer el almacenamiento de operaciones. Si había una solicitud pendiente, comprobá su resultado antes de repetirla.');
    return null;
  }
}

function freeze(on) {
  for (const control of fields.querySelectorAll('input, select, textarea')) control.disabled = on;
  if (!on && active?.record.commodity_id && form.elements.commodity_id) form.elements.commodity_id.disabled = true;
}

function busyState(on) {
  busy = on;
  submit.disabled = on;
  for (const button of dialog.querySelectorAll('[data-close-dialog]')) button.disabled = on;
  freeze(on || Boolean(pending));
  submit.textContent = on ? 'Guardando…' : pending ? 'Comprobar y reintentar' : 'Guardar';
}

async function openEditor(action, record = {}, resume = false) {
  if (!Object.hasOwn(definitions, action)) return;
  if (storageBlocked) { toast('No se puede conservar o leer la operación. Revisá el almacenamiento del navegador antes de guardar.'); return; }
  if (pending && !resume) { toast('Primero confirmá el resultado de la operación pendiente.'); return; }
  const definition = definitions[action];
  const generation = ++editorGeneration;
  active = { action, record };
  fields.replaceChildren(document.querySelector(`#form-${definition.template}`).content.cloneNode(true));
  errorBox.hidden = true;
  reloadButton.hidden = true;
  document.querySelector('#editor-title').textContent = action === 'requirement-edit' && !Number(record.active) ? 'Restaurar objetivo' : definition.title;
  document.querySelector('#editor-description').textContent = definition.archive
    ? 'Saldrá de la planificación activa. Los registros y movimientos permanecerán disponibles.'
    : record.name || record.commodity_name || 'Completá los datos de la planificación.';
  for (const control of fields.querySelectorAll('[name]')) {
    if (Object.hasOwn(record, control.name) && control.type !== 'checkbox') control.value = record[control.name] ?? '';
  }
  if (definition.edit && form.elements.reason) form.elements.reason.required = true;
  if (definition.template === 'archive' && !definition.archive) {
    const check = form.elements.confirmed;
    check.required = false;
    check.closest('label').hidden = true;
  }
  if (resume) {
    for (const [name, value] of Object.entries(pending.request.body)) {
      const control = form.elements[name];
      if (control) control.type === 'checkbox' ? control.checked = value : control.value = value ?? '';
    }
  }
  if (!dialog.open) dialog.showModal();
  busyState(false);
  if (definition.template === 'requirement') {
    submit.disabled = true;
    try {
      const result = await request('commodities');
      if (generation !== editorGeneration || !dialog.open) return;
      const select = form.elements.commodity_id;
      for (const commodity of result.data) {
        if (!record.commodity_id && (record.used_commodity_ids || []).includes(commodity.id)) continue;
        const option = document.createElement('option');
        option.value = commodity.id;
        option.textContent = commodity.name_es;
        select.append(option);
      }
      const chosen = record.commodity_id || (resume ? pending.request.query.commodity_id : '');
      if (chosen && !Array.from(select.options).some(option => option.value === chosen)) {
        const option = document.createElement('option');
        option.value = chosen;
        option.textContent = record.commodity_name || `Material ${chosen}`;
        select.append(option);
      }
      select.value = chosen;
      if (!record.commodity_id && !resume && select.options.length === 1) {
        throw new Error('No hay materiales disponibles. Un administrador puede agregarlos al catálogo.');
      }
      if (!definition.edit && !resume) form.elements.reason.value = 'Alta de objetivo';
      busyState(false);
    } catch (failure) {
      if (generation !== editorGeneration || !dialog.open) return;
      errorBox.textContent = failure.message;
      errorBox.hidden = false;
      // A stored operation can still be safely replayed even if the current catalog cannot be loaded.
      submit.disabled = !resume;
    }
  }
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (busy || !active || (!pending && !form.reportValidity())) return;
  const definition = definitions[active.action];
  let operation = pending;
  if (!operation) {
    const body = {};
    for (const name of definition.fields || []) body[name] = form.elements[name].value;
    if (definition.template === 'requirement') {
      body.required_quantity = form.elements.required_quantity.value;
      body.version = active.record.version || '0';
      body.reason = form.elements.reason.value;
    } else if (definition.template === 'archive') {
      body.version = active.record.version;
      body.reason = form.elements.reason.value;
      if (definition.archive) body.confirmed = form.elements.confirmed.checked;
    } else if (definition.edit) body.version = active.record.version;
    const query = definition.template === 'requirement'
      ? { installation_id: active.record.installation_id, commodity_id: active.record.commodity_id || form.elements.commodity_id.value }
      : queryFor(definition, active.record, body);
    const savedRecord = definition.template === 'requirement' ? { ...active.record, commodity_id: query.commodity_id } : active.record;
    operation = { action: active.action, record: savedRecord, actor_id: actorId,
      request: { route: definition.route, method: definition.method, query, body, key: crypto.randomUUID() } };
    try { sessionStorage.setItem(storageKey, JSON.stringify(operation)); }
    catch {
      errorBox.textContent = 'El navegador no puede conservar la operación. Habilitá almacenamiento para evitar cargas duplicadas.';
      errorBox.hidden = false;
      return;
    }
    pending = operation;
  }
  errorBox.hidden = true;
  busyState(true);
  try {
    const result = await request(operation.request.route, operation.request);
    try { sessionStorage.removeItem(storageKey); } catch { /* A later replay will return the confirmed result. */ }
    pending = null;
    try { sessionStorage.setItem('afec.planning.flash', 'La planificación se guardó correctamente.'); } catch { /* The server has already confirmed success. */ }
    const data = result.data;
    const destination = new URL(home, window.location.href);
    if (operation.action === 'system-archive' || operation.action === 'commodity-create') {
      // Return to the public list or catalog context.
    } else {
      destination.searchParams.set('page', 'system');
      destination.searchParams.set('id', data.system_id || data.id);
      if (operation.action === 'installation-archive' || operation.action === 'requirement-archive') destination.searchParams.set('include_archived', '1');
    }
    window.location.assign(destination);
  } catch (failure) {
    if (!failure.uncertain) {
      try { sessionStorage.removeItem(storageKey); } catch { storageBlocked = true; }
      pending = null;
    }
    errorBox.textContent = failure.message + (pending ? ' Reintentá esta misma operación para confirmar su resultado.' : '');
    errorBox.hidden = false;
    reloadButton.hidden = failure.status !== 409 || Boolean(pending);
    banner.hidden = !pending;
    busyState(false);
  }
});

dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
dialog.addEventListener('close', () => { ++editorGeneration; banner.hidden = !pending; });
for (const button of dialog.querySelectorAll('[data-close-dialog]')) button.addEventListener('click', () => { if (!busy) dialog.close(); });
reloadButton.addEventListener('click', () => window.location.reload());
document.querySelector('#resume-operation').addEventListener('click', () => {
  if (pending) openEditor(pending.action, pending.record, true);
});

const historyDialog = document.querySelector('#history-dialog');
const historyItems = document.querySelector('#history-items');
const previous = document.querySelector('#history-previous');
const next = document.querySelector('#history-next');
let historyRecord = null;
let historyPage = 1;
let historyGeneration = 0;
const labels = { name: 'Nombre', description: 'Descripción', body_name: 'Cuerpo', location_label: 'Ubicación', tier: 'Tier', type: 'Tipo', state: 'Estado', sort_order: 'Orden', archived_at: 'Archivo' };
const actions = { create: 'Creación', update: 'Edición', archive: 'Archivo', restore: 'Restauración' };
const quantity = value => String(value ?? '—').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

async function loadHistory() {
  const generation = ++historyGeneration;
  previous.disabled = next.disabled = true;
  historyItems.textContent = 'Cargando cambios…';
  try {
    const result = await request('planning/history', { query: { ...historyRecord, list_page: historyPage } });
    if (generation !== historyGeneration || !historyDialog.open) return;
    historyItems.replaceChildren();
    if (!result.data.length) historyItems.textContent = 'No hay cambios registrados en esta página.';
    for (const item of result.data) {
      const article = document.createElement('article');
      article.className = 'history-entry';
      const heading = document.createElement('h3');
      const stamp = new Date(item.recorded_at.replace(' ', 'T') + 'Z').toLocaleString('es');
      heading.textContent = `${stamp} · ${item.author}`;
      article.append(heading);
      const summary = document.createElement('p');
      summary.textContent = historyRecord.type === 'requirement'
        ? `${item.old_quantity === null ? 'Objetivo inicial' : `${Number(item.old_active) ? '' : 'Retirado: '}${quantity(item.old_quantity)}`} → ${Number(item.new_active) ? '' : 'Retirado: '}${quantity(item.new_quantity)}`
        : actions[item.action] || item.action;
      article.append(summary);
      if (item.changes) {
        for (const [field, label] of Object.entries(labels)) {
          const before = item.changes.before?.[field] ?? null;
          const after = item.changes.after?.[field] ?? null;
          if (before === after) continue;
          const line = document.createElement('p');
          line.className = 'muted';
          line.textContent = `${label}: ${before ?? '—'} → ${after ?? '—'}`;
          article.append(line);
        }
      }
      const reason = document.createElement('p');
      reason.className = 'muted';
      reason.textContent = item.reason;
      article.append(reason);
      historyItems.append(article);
    }
    previous.hidden = historyPage === 1;
    next.hidden = !result.pagination.has_more;
    previous.disabled = next.disabled = false;
  } catch (failure) {
    if (generation === historyGeneration) historyItems.textContent = failure.message;
  }
}

document.addEventListener('click', event => {
  const button = event.target.closest('button[data-action]');
  if (!button || button.disabled) return;
  let record;
  try { record = JSON.parse(button.dataset.record || '{}'); }
  catch { toast('No se pudieron leer los datos del formulario. Recargá la página.'); return; }
  if (button.dataset.action === 'history') {
    historyRecord = record;
    historyPage = 1;
    if (!historyDialog.open) historyDialog.showModal();
    loadHistory();
  } else openEditor(button.dataset.action, record);
});
previous.addEventListener('click', () => { historyPage--; loadHistory(); });
next.addEventListener('click', () => { historyPage++; loadHistory(); });
document.querySelector('#close-history').addEventListener('click', () => historyDialog.close());
historyDialog.addEventListener('close', () => { ++historyGeneration; });

pending = readPending();
banner.hidden = !pending;
try {
  const flash = sessionStorage.getItem('afec.planning.flash');
  if (flash) { sessionStorage.removeItem('afec.planning.flash'); toast(flash, true); }
} catch { /* A new mutation will refuse submission if it cannot save its key. */ }
