import { request, ApiError } from './http.js';

const editor = document.querySelector('#market-editor');
const editorForm = document.querySelector('#market-editor-form');
const editorMessage = document.querySelector('#market-editor-message');
const importForm = document.querySelector('#market-import-form');
const importApply = document.querySelector('#market-import-apply');
let active = null;
let manualAttempt = null;
let reviewHash = null;
let importAttempt = null;

function message(node, text) { if (node) node.textContent = text; }
function clearReview() {
  reviewHash = null;
  importAttempt = null;
  if (importApply) importApply.disabled = true;
  if (importForm) importForm.elements.confirmed.checked = false;
  const preview = document.querySelector('#market-import-preview');
  if (preview) { preview.hidden = true; preview.textContent = ''; }
}
async function upload(action) {
  const data = new FormData(importForm);
  for (const name of ['stock_file', 'summary_file']) {
    if (!data.get(name)?.size) data.delete(name);
  }
  data.set('action', action);
  if (action === 'apply') {
    if (!reviewHash || !importForm.elements.confirmed.checked) throw new Error('Validá los archivos y confirmá la importación.');
    data.set('review_hash', reviewHash);
    data.set('confirmed', '1');
    importAttempt ??= crypto.randomUUID();
  } else { data.delete('confirmed'); }
  const url = new URL(document.querySelector('meta[name="api-url"]').content, location.href);
  url.searchParams.set('route', 'markets/import');
  const headers = { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content };
  if (action === 'apply') headers['Idempotency-Key'] = importAttempt;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 60000);
  try {
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers, body: data, signal: controller.signal });
    let result;
    try { result = await response.json(); } catch { throw new Error('No se pudo confirmar la respuesta. Reintentá con los mismos archivos.'); }
    if (!response.ok) throw new ApiError(result.error?.message || 'No se pudo importar.', response.status, result.error?.code);
    return { ...result.data, replayed: result.replayed === true };
  } finally { clearTimeout(timer); }
}
if (importForm) {
  importForm.addEventListener('change', event => {
    if (event.target.name !== 'confirmed') clearReview();
    importApply.disabled = !reviewHash || !importForm.elements.confirmed.checked;
  });
  importForm.addEventListener('input', event => { if (event.target.name === 'system') clearReview(); });
  importForm.addEventListener('submit', async event => {
    event.preventDefault();
    const button = importForm.querySelector('[type="submit"]');
    button.disabled = true; clearReview();
    message(document.querySelector('#market-import-message'), 'Validando archivos…');
    try {
      const data = await upload('preview');
      reviewHash = data.review_hash;
      const preview = document.querySelector('#market-import-preview');
      preview.hidden = false;
      preview.textContent = `${data.stock_rows} filas de stock y ${data.summary_rows} resúmenes. ${data.stations} estaciones, ${data.products} mercancías; ${data.mapped} vinculadas a colonización. ` +
        (data.unmatched.length ? `Sin equivalencia: ${data.unmatched.join(', ')}. ` : '') +
        (data.existing_import ? 'Estos archivos ya están importados; repetirlos no duplicará datos.' : 'Los campos de economía y pad ausentes conservarán sus valores actuales.');
      message(document.querySelector('#market-import-message'), 'Archivos validados. Revisá los datos y confirmá para importar.');
      importApply.disabled = !importForm.elements.confirmed.checked;
    } catch (error) { message(document.querySelector('#market-import-message'), error.message); }
    finally { button.disabled = false; }
  });
  importApply.addEventListener('click', async () => {
    importApply.disabled = true;
    const controls = [...importForm.elements];
    // Build FormData before disabling controls; keep files stable for retries.
    try {
      const pending = upload('apply');
      for (const control of controls) control.disabled = true;
      const data = await pending;
      message(document.querySelector('#market-import-message'), data.duplicate_import || data.replayed
        ? `La importación #${data.import_id} ya estaba registrada. Se recuperó el resultado sin duplicar ni reemplazar existencias.`
        : `Importación #${data.import_id} completada. ${data.applied} actualizadas; ${data.older_ignored} antiguas omitidas; ${data.same_date_conflicts} conflictos de misma fecha conservados en el historial. Recargá para consultar los resultados.`);
      importAttempt = null;
      const link = document.createElement('a'); link.className = 'button-quiet'; link.href = location.href; link.textContent = 'Ver mercados actualizados';
      importForm.append(link);
    } catch (error) {
      for (const control of controls) control.disabled = false;
      importApply.disabled = !reviewHash || !importForm.elements.confirmed.checked;
      message(document.querySelector('#market-import-message'), `${error.message} Si la conexión falló, reintentá sin cambiar los archivos para recuperar la misma operación.`);
    }
  });
}

document.addEventListener('click', async event => {
  if (event.target.closest('[data-market-close]')) { editor?.close(); return; }
  const button = event.target.closest('[data-market-edit]');
  if (!button || !editor) return;
  active = { kind: button.dataset.marketEdit, row: JSON.parse(button.dataset.record) };
  manualAttempt = null; editorForm.reset();
  for (const fieldset of editorForm.querySelectorAll('[data-market-fields]')) {
    fieldset.hidden = fieldset.dataset.marketFields !== active.kind;
    fieldset.disabled = fieldset.hidden;
  }
  const dated = editorForm.querySelector('[data-market-dated]'); dated.hidden = active.kind === 'product';
  for (const control of dated.querySelectorAll('input')) control.disabled = dated.hidden;
  editorForm.elements.source.required = !dated.hidden;
  const row = active.row;
  editorForm.elements.quantity.value = row.quantity ?? '';
  editorForm.elements.available.value = row.available == null ? '' : String(row.available);
  editorForm.elements.verified.checked = Boolean(Number(row.verified));
  editorForm.elements.economy.value = row.economy ?? '';
  editorForm.elements.max_pad.value = row.max_pad ?? '';
  editorForm.elements.commodity_id.value = row.commodity_id ?? '';
  editorForm.elements.observed_on.value = row.observed_on ?? new Date().toLocaleDateString('en-CA', { timeZone: 'Europe/Rome' });
  editorForm.elements.source.value = row.source ?? '';
  message(editorMessage, '');
  message(document.querySelector('#market-editor-title'), active.kind === 'station' ? row.name : row.original_name);
  editor.showModal();
});
editorForm?.addEventListener('submit', async event => {
  event.preventDefault(); if (!active) return;
  const { kind, row } = active;
  const body = { version: kind === 'product' ? row.product_version : row.version, reason: editorForm.elements.reason.value.trim() };
  if (kind === 'product') body.commodity_id = editorForm.elements.commodity_id.value || null;
  else {
    body.observed_on = editorForm.elements.observed_on.value || null;
    body.source = editorForm.elements.source.value.trim();
    if (kind === 'station') {
      body.economy = editorForm.elements.economy.value.trim() || null;
      body.max_pad = editorForm.elements.max_pad.value || null;
    } else {
      body.station_id = row.station_id;
      body.quantity = editorForm.elements.quantity.value.trim() || null;
      body.available = editorForm.elements.available.value === '' ? null : editorForm.elements.available.value === '1';
      body.verified = editorForm.elements.verified.checked;
    }
  }
  const serialized = JSON.stringify(body);
  if (!manualAttempt || manualAttempt.body !== serialized) manualAttempt = { body: serialized, key: crypto.randomUUID() };
  const controls = [...editorForm.elements]; for (const control of controls) control.disabled = true;
  try {
    await request(`markets/${kind}`, { method: 'PATCH', query: { id: kind === 'station' ? row.id : row.product_id }, body, key: manualAttempt.key });
    location.reload();
  } catch (error) { message(editorMessage, error.message + (error.uncertain ? ' Reintentá sin cambiar los campos para recuperar la misma operación.' : '')); }
  finally { for (const control of controls) control.disabled = false; for (const fieldset of editorForm.querySelectorAll('[data-market-fields]')) fieldset.disabled = fieldset.hidden; }
});

const historyDialog = document.querySelector('#market-history');
let historyRow = null, historyPage = 1;
async function loadHistory() {
  const result = await request('markets/history', { query: { station_id: historyRow.station_id, product_id: historyRow.product_id, list_page: historyPage } });
  const container = document.querySelector('#market-history-content');
  for (const row of result.data) {
    const text = document.createElement('p');
    text.textContent = `${row.observed_on ?? 'Sin fecha'} · ${row.quantity ?? 'Stock desconocido'} t · ${row.source} · ${Number(row.applied) ? 'Aplicada al registrarse' : 'Conservada sin reemplazar stock'} · ${row.import_id ? `Importación #${row.import_id}` : 'Actualización manual'}`;
    container.append(text);
  }
  document.querySelector('#market-history-more').hidden = !result.pagination.has_more;
}
document.addEventListener('click', async event => {
  if (event.target.closest('[data-market-history-close]')) { historyDialog?.close(); return; }
  const button = event.target.closest('[data-market-history]'); if (!button) return;
  historyRow = JSON.parse(button.dataset.record); historyPage = 1;
  message(document.querySelector('#market-history-content'), ''); historyDialog.showModal();
  try { await loadHistory(); } catch (error) { message(document.querySelector('#market-history-content'), error.message); }
});
document.querySelector('#market-history-more')?.addEventListener('click', async () => { historyPage++; try { await loadHistory(); } catch (error) { historyPage--; message(document.querySelector('#market-history-content'), error.message); } });
let importsPage = 2;
document.querySelector('#market-import-history')?.addEventListener('click', async event => {
  try {
    const result = await request('markets/imports', { query: { list_page: importsPage } }); importsPage++;
    for (const row of result.data) {
      const text = document.createElement('p'); text.textContent = `#${row.id} · ${row.created_at} UTC · ${row.stock_filename ?? 'Sin stock'} / ${row.summary_filename ?? 'Sin resumen'} · ${row.stock_rows} observaciones.`;
      document.querySelector('#market-import-history-results').append(text);
    }
    event.target.disabled = !result.pagination.has_more;
    if (!result.data.length) message(document.querySelector('#market-import-history-results'), 'No hay más importaciones.');
  } catch (error) { message(document.querySelector('#market-import-history-results'), error.message); }
});
