import { request } from './http.js';

export function positiveQuantity(value) {
  if (typeof value !== 'string' || !/^[1-9][0-9]{0,18}$/.test(value) || BigInt(value) > 9223372036854775807n) {
    throw new Error('Ingresá toneladas enteras positivas, sin separadores (máximo 9223372036854775807).');
  }
  return value;
}

export function loadRequest(action, commander, manual, preset, reason) {
  if (!commander) throw new Error('Seleccioná un comandante.');
  const subtract = action.startsWith('subtract');
  const standard = action.endsWith('-load');
  positiveQuantity(standard ? preset ?? '' : manual);
  if (subtract && !reason.trim()) throw new Error('Indicá el motivo de la resta en la cabecera de esta instalación.');
  return { route: subtract ? 'quick/withdraw' : 'deliveries', scope: subtract ? 'logistics.quick.withdraw' : 'logistics.delivery',
    body: { commander_id: commander, ...(standard ? { use_standard_load: true } : { quantity: manual }),
      reason: subtract ? reason.trim() : 'Carga rápida desde tabla' } };
}

async function initQuickLoad() {
  const panels = [...document.querySelectorAll('[data-quick-installation]')];
  if (!panels.length) return;
  const actor = document.querySelector('meta[name="user-id"]').content;
  const storageKey = `afec.quick.pending.${actor}`;
  const banner = document.querySelector('#quick-pending');
  const retry = document.querySelector('#quick-retry');
  const commanders = new Map();
  const originallyDisabled = new WeakSet();
  let busy = false, pending = null, blocked = false, ready = false, page = 0, more = false, own = null;
  const message = (panel, text) => { (panel?.querySelector('[data-quick-status]') || banner.querySelector('p')).textContent = text; };
  for (const control of document.querySelectorAll('[data-quick], [data-quick-save], [data-quick-preset], [data-quick-quantity]')) {
    if (control.disabled) originallyDisabled.add(control);
  }
  function lock() {
    for (const control of document.querySelectorAll('[data-quick], [data-quick-save], [data-quick-preset], [data-quick-quantity], [data-quick-commander], [data-quick-more]')) {
      const presetAction = control.matches('[data-quick-save], [data-quick-preset], [data-quick$="-load"]');
      control.disabled = busy || blocked || !!pending || originallyDisabled.has(control) || (presetAction && !ready);
    }
    retry.disabled = busy || blocked;
    banner.hidden = (!pending || busy) && !blocked;
  }
  try {
    const check = `afec.quick.check.${actor}`;
    sessionStorage.setItem(check, 'ok'); sessionStorage.removeItem(check);
    pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
  } catch {
    blocked = true; banner.querySelector('p').textContent = 'Habilitá el almacenamiento de la sesión para identificar y recuperar operaciones sin duplicarlas.';
  }
  function remember(panel) {
    try { sessionStorage.setItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`, panel.querySelector('[data-quick-commander]').value); }
    catch { blocked = true; lock(); }
  }
  function selected(panel) { return commanders.get(panel.querySelector('[data-quick-commander]').value); }
  function propose(panel, reset = true) {
    const commander = selected(panel);
    panel.querySelector('[data-quick-preset]').value = commander?.standard_load_quantity ?? '';
    if (reset) for (const input of panel.querySelectorAll('[data-quick-quantity]')) input.value = commander?.standard_load_quantity ?? '';
  }
  async function options(reset = false) {
    const result = await request('quick/options', { query: { list_page: reset ? 1 : page + 1 } });
    if (reset) commanders.clear();
    page = result.data.page; more = result.data.has_more; ready = result.data.presets_ready; own = result.data.own;
    for (const row of result.data.commanders) commanders.set(row.id, row);
    if (own?.active) commanders.set(own.id, own);
    // A saved selection may be beyond the first page; restore it without loading the entire catalogue.
    for (const panel of panels) {
      let id = panel.querySelector('[data-quick-commander]').value;
      try { id ||= sessionStorage.getItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`); } catch { /* checked above */ }
      if (id && !commanders.has(id)) {
        try { const result = await request('commander/load', {query:{id}}); if (result.data.active) commanders.set(id,result.data); }
        catch (failure) { if (failure.uncertain) throw failure; }
      }
    }
    for (const panel of panels) {
      const select = panel.querySelector('[data-quick-commander]');
      let saved = select.value;
      if (!saved) { try { saved = sessionStorage.getItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`) || own?.id || ''; } catch { /* storage is checked above */ } }
      select.replaceChildren(new Option('Seleccionar comandante', ''));
      for (const commander of commanders.values()) select.add(new Option(commander.display_name, commander.id));
      select.value = commanders.has(saved) ? saved : '';
      panel.querySelector('[data-quick-more]').hidden = !more;
      propose(panel, !select.dataset.loaded); select.dataset.loaded = '1';
      if (!ready) message(panel, 'Importá la migración 008 para configurar paladas. La carga manual sigue disponible.');
      else if (!commanders.size) message(panel, 'Creá la identidad con una entrega detallada; después recargá esta página.');
    }
    lock();
  }
  const units = value => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  async function refresh() {
    const url = new URL(window.location.href);
    const query = { id: url.searchParams.get('id'), list_page: url.searchParams.get('list_page') || '1' };
    if (url.searchParams.get('include_archived') === '1') query.include_archived = '1';
    const { data } = await request('system', { query });
    for (const panel of panels) {
      const installation = data.installations.find(item => item.id === panel.dataset.quickInstallation);
      if (!installation) continue;
      const badge = panel.querySelector('[data-quick-progress]');
      if (badge) badge.textContent = installation.archived_at ? 'Archivada' : installation.percent === null ? 'Sin objetivos' : `${installation.percent.toLocaleString('es', { maximumFractionDigits: 1 })}%`;
      for (const row of data.requirements.filter(item => item.installation_id === installation.id)) {
        const tr = panel.querySelector(`[data-material="${row.commodity_id}"]`); if (!tr) continue;
        for (const field of ['required_quantity','delivered_quantity','remaining_quantity','surplus_quantity']) {
          const cell = tr.querySelector(`[data-balance="${field}"]`); if (cell) cell.textContent = units(row[field]);
        }
        const cell = tr.querySelector('[data-balance="remaining_quantity"]'); cell?.classList.toggle('complete', row.remaining_quantity === '0');
        const quick = tr.querySelector('[data-quick-row]'); if (quick) quick.dataset.remaining = row.remaining_quantity;
      }
    }
  }
  async function execute(job) {
    if (busy || blocked) return;
    const panel = panels.find(item => item.dataset.quickInstallation === job.installation);
    busy = true; pending = job;
    message(panel, 'Guardando…');
    try { sessionStorage.setItem(storageKey, JSON.stringify(job)); }
    catch { blocked = true; busy = false; message(panel, 'No se pudo conservar la operación; no se envió.'); lock(); return; }
    lock();
    try {
      const result = await request(job.route, { method: job.method, query: job.query, body: job.body, key: job.key });
      // Persist the receipt state before allowing another operation. Retrying is safe if storage removal fails.
      sessionStorage.removeItem(storageKey); pending = null;
      if (job.route === 'commander/load') {
        commanders.set(result.data.id, result.data);
        for (const card of panels) if (selected(card)?.id === result.data.id) propose(card);
        message(panel, 'Palada estándar guardada.');
      } else {
        const quantity = result.data.delta_quantity?.replace('-', '') || result.data.quantity;
        const name = commanders.get(job.body.commander_id)?.display_name || 'Comandante';
        const note = result.data.exceeds_pending ? ' Supera lo pendiente: se registró completa y el excedente figura como sobrante.' : '';
        message(panel, `${job.route === 'deliveries' ? '+' : '−'}${units(quantity)} t · ${name}. Guardado.${note}`);
        try { await refresh(); } catch { message(panel, 'La operación se guardó. No se pudieron actualizar los totales; recargá la página sin repetir la entrega.'); }
      }
    } catch (failure) {
      if (failure.uncertain || !(failure instanceof Error)) {
        message(panel, 'Resultado pendiente. Usá «Comprobar y reintentar» para recuperar la misma operación.');
      } else {
        try { sessionStorage.removeItem(storageKey); pending = null; } catch { blocked = true; }
        message(panel, failure.message);
        if (failure.code === 'stale_version') { try { await options(true); } catch { /* keep the original failure */ } }
      }
    } finally { busy = false; lock(); }
  }
  async function act(panel, row, action) {
    if (busy || pending || blocked) return;
    try {
      const commander = selected(panel);
      const job = loadRequest(action, commander?.id, row.querySelector('[data-quick-quantity]').value, commander?.standard_load_quantity,
        panel.querySelector('[data-quick-reason]').value);
      await execute({ ...job, method: 'POST', query: { installation_id: panel.dataset.quickInstallation, commodity_id: row.dataset.commodity },
        key: crypto.randomUUID(), installation: panel.dataset.quickInstallation });
    } catch (failure) { message(panel, failure.message); }
  }
  for (const panel of panels) {
    panel.querySelector('[data-quick-commander]').addEventListener('change', () => { propose(panel); remember(panel); });
    panel.querySelector('[data-quick-more]').addEventListener('click', async () => { try { await options(); } catch (failure) { message(panel, failure.message); } });
    panel.querySelector('[data-quick-save]').addEventListener('click', async event => {
      if (busy || pending || blocked || event.detail > 1) return;
      try {
        const commander = selected(panel); if (!commander) throw new Error('Seleccioná un comandante.');
        const value = panel.querySelector('[data-quick-preset]').value.trim(); if (value) positiveQuantity(value);
        await execute({ route: 'commander/load', method: 'PATCH', query: { id: commander.id }, body: { quantity: value || null, version: commander.load_version },
          key: crypto.randomUUID(), installation: panel.dataset.quickInstallation });
      } catch (failure) { message(panel, failure.message); }
    });
    panel.addEventListener('click', event => { const button = event.target.closest('[data-quick]'); if (button && !button.disabled && event.detail <= 1) act(panel, button.closest('[data-quick-row]'), button.dataset.quick); });
    panel.addEventListener('keydown', event => {
      if (event.key === 'Enter' && !event.repeat && event.target.matches('[data-quick-quantity]')) {
        event.preventDefault(); const row = event.target.closest('[data-quick-row]');
        if (!row.querySelector('[data-quick="add"]').disabled) act(panel, row, 'add');
      }
    });
  }
  retry.addEventListener('click', () => { if (pending) execute(pending); });
  lock();
  try { await options(); } catch (failure) { for (const panel of panels) message(panel, `${failure.message} Recargá para volver a cargar comandantes.`); }
}

if (typeof document !== 'undefined') initQuickLoad();
