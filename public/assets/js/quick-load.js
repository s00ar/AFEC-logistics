import { request } from './http.js';
import { positiveQuantity } from './quantity.js';
export { positiveQuantity } from './quantity.js';

export function loadRequest(action, commander, manual, preset, reason = '') {
  if (!commander) throw new Error('Seleccioná un comandante.');
  const subtract = action.startsWith('subtract');
  const standard = action.endsWith('-load');
  const remaining = action === 'add-remaining';
  if (!remaining) positiveQuantity(standard ? preset ?? '' : manual);
  return { route: subtract ? 'quick/withdraw' : 'deliveries', scope: subtract ? 'logistics.quick.withdraw' : 'logistics.delivery',
    body: { commander_id: commander, ...(remaining ? { use_remaining: true } : standard ? { use_standard_load: true } : { quantity: manual }),
      ...(subtract ? { reason: reason.trim() || 'Resta de stock desde panel de material' } : {}) } };
}

async function initQuickLoad() {
  const panels = [...document.querySelectorAll('[data-quick-installation]')];
  if (!panels.length) return;
  const drawer = document.querySelector('#quick-material-panel');
  // Keep one set of handlers if the module is loaded by multiple page entry points.
  if (drawer.dataset.quickInitialized) return;
  drawer.dataset.quickInitialized = '1';
  const listMode = drawer.hasAttribute('data-quick-list');
  let activePanel = null, activeRow = null;
  const actor = document.querySelector('meta[name="user-id"]').content;
  const storageKey = `afec.quick.pending.${actor}`;
  const banner = document.querySelector('#quick-pending');
  const retry = document.querySelector('#quick-retry');
  const commanders = new Map();
  const originallyDisabled = new WeakSet();
  let busy = false, pending = null, blocked = false, ready = false, page = 0, more = false, own = null;
  const message = (panel, text) => {
    (panel?.querySelector('[data-quick-status]') || banner.querySelector('p')).textContent = text;
    if (panel === activePanel) drawer.querySelector('[data-quick-drawer-status]').textContent = text;
  };
  for (const control of document.querySelectorAll('[data-quick], [data-quick-save], [data-quick-preset], [data-quick-quantity]')) {
    if (control.disabled) originallyDisabled.add(control);
  }
  function lock() {
    for (const control of document.querySelectorAll('[data-quick], [data-quick-save], [data-quick-preset], [data-quick-quantity], [data-quick-commander], [data-quick-more], [data-quick-identity-create]')) {
      const presetAction = control.matches('[data-quick-save], [data-quick-preset], [data-quick$="-load"]');
      control.disabled = busy || blocked || !!pending || originallyDisabled.has(control) || (presetAction && !ready) || (control.matches('[data-quick^="add"]') && activeRow?.dataset.open !== '1') || (control.matches('[data-quick="add-remaining"]') && activeRow?.dataset.remaining === '0') || (control.matches('[data-quick$="-load"]') && !selected(activePanel)?.standard_load_quantity);
    }
    retry.disabled = busy || blocked;
    banner.hidden = (!pending || busy) && !blocked;
  }
  try {
    const check = `afec.quick.check.${actor}`;
    sessionStorage.setItem(check, 'ok'); sessionStorage.removeItem(check);
    pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (listMode && pending) drawer.dataset.quickInstallation = pending.installation;
  } catch {
    blocked = true; banner.querySelector('p').textContent = 'Habilitá el almacenamiento de la sesión para identificar y recuperar operaciones sin duplicarlas.';
  }
  function remember(panel) {
    try { sessionStorage.setItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`, panel?.querySelector('[data-quick-commander]').value); }
    catch { blocked = true; lock(); }
  }
  function selected(panel) { return commanders.get(panel?.querySelector('[data-quick-commander]')?.value); }
  function propose(panel, reset = true) {
    const commander = selected(panel);
    panel.querySelector('[data-quick-preset]').value = commander?.standard_load_quantity ?? '';
    if (panel === activePanel) {
      drawer.querySelector('[data-quick-identity]').textContent = commander?.display_name || 'Seleccioná un comandante en la instalación.';
      drawer.querySelector('[data-quick-load-label]').textContent = commander?.standard_load_quantity ? `${units(commander.standard_load_quantity)} t` : 'Sin configurar';
    }
  }
  async function options(reset = false) {
    const result = await request('quick/options', { query: { list_page: reset ? 1 : page + 1 } });
    if (reset) commanders.clear();
    page = result.data.page; more = result.data.has_more; ready = result.data.presets_ready; own = result.data.own;
    for (const row of result.data.commanders) commanders.set(row.id, row);
    if (own?.active) commanders.set(own.id, own);
    // A saved selection may be beyond the first page; restore it without loading the entire catalogue.
    for (const panel of panels) {
      let id = panel?.querySelector('[data-quick-commander]').value;
      try { id ||= sessionStorage.getItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`); } catch { /* checked above */ }
      if (id && !commanders.has(id)) {
        try { const result = await request('commander/load', {query:{id}}); if (result.data.active) commanders.set(id,result.data); }
        catch (failure) { if (failure.uncertain) throw failure; }
      }
    }
    for (const panel of panels) {
      const select = panel?.querySelector('[data-quick-commander]');
      let saved = select.value;
      if (!saved) { try { saved = sessionStorage.getItem(`afec.quick.commander.${actor}.${panel.dataset.quickInstallation}`) || own?.id || ''; } catch { /* storage is checked above */ } }
      select.replaceChildren(new Option('Seleccionar comandante', ''));
      for (const commander of commanders.values()) select.add(new Option(commander.display_name, commander.id));
      select.value = commanders.has(saved) ? saved : '';
      panel.querySelector('[data-quick-more]').hidden = !more;
      panel.querySelector('[data-quick-identity-create]').hidden = !!own;
      propose(panel, !select.dataset.loaded); select.dataset.loaded = '1';
      if (!ready) message(panel, 'Importá la migración 008 para configurar paladas. La carga manual sigue disponible.');
      else if (!commanders.size) message(panel, 'Todavía no hay comandantes disponibles.');
    }
    lock();
  }
  const units = value => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  async function refresh() {
    if (listMode) { window.location.reload(); return; }
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
        tr.classList.toggle('requirement-complete', Boolean(Number(row.active)) && row.remaining_quantity === '0');
        tr.dataset.remaining = row.remaining_quantity;
        if (tr === activeRow) drawer.querySelector('[data-quick-remaining]').textContent = units(row.remaining_quantity);
      }
    }
  }
  async function execute(job) {
    if (busy || blocked) return;
    const panel = listMode ? drawer : panels.find(item => item.dataset.quickInstallation === job.installation);
    busy = true; pending = job;
    message(panel, 'Guardando…');
    try { sessionStorage.setItem(storageKey, JSON.stringify(job)); }
    catch { blocked = true; busy = false; message(panel, 'No se pudo conservar la operación; no se envió.'); lock(); return; }
    lock();
    try {
      const result = await request(job.route, { method: job.method, query: job.query, body: job.body, key: job.key });
      // Persist the receipt state before allowing another operation. Retrying is safe if storage removal fails.
      sessionStorage.removeItem(storageKey); pending = null;
      if (job.route === 'commander/identity') {
        await options(true);
        message(panel, 'Tu comandante está listo. Ya podés configurar la palada y cargar stock.');
      } else if (job.route === 'commander/load') {
        commanders.set(result.data.id, result.data);
        for (const card of panels) if (selected(card)?.id === result.data.id) propose(card);
        message(panel, 'Palada estándar guardada.');
        if (listMode) drawer.querySelector('[data-quick-config]').open = !result.data.standard_load_quantity;
      } else {
        const quantity = result.data.delta_quantity?.replace('-', '') || result.data.quantity;
        const name = commanders.get(job.body.commander_id)?.display_name || 'Comandante';
        const note = result.data.exceeds_pending ? ' Supera lo pendiente: se registró completa y el excedente figura como sobrante.' : '';
        const text = `${job.route === 'deliveries' ? '+' : '−'}${units(quantity)} t · ${name}. Guardado.${note}`;
        message(panel, text);
        if (listMode) { try { sessionStorage.setItem('afec.planning.flash',text); } catch { /* The delivery is already confirmed. */ } }
        if (panel === activePanel && activeRow?.dataset.commodity === job.query.commodity_id && drawer.open) drawer.close();
        try { await refresh(); } catch { message(panel, 'La operación se guardó. No se pudieron actualizar los totales; recargá la página sin repetir la entrega.'); }
      }
    } catch (failure) {
      if (failure.uncertain || !(failure instanceof Error)) {
        message(panel, 'Resultado pendiente. Usá «Comprobar y reintentar» para recuperar la misma operación.');
      } else {
        try { sessionStorage.removeItem(storageKey); pending = null; } catch { blocked = true; }
        message(panel, failure.message);
        if (failure.code === 'nothing_pending') { try { await refresh(); } catch { /* Keep the server's explanation until reload. */ } }
        if (failure.code === 'stale_version') { try { await options(true); } catch { /* keep the original failure */ } }
      }
    } finally { busy = false; lock(); }
  }
  async function act(panel, row, action) {
    if (busy || pending || blocked) return;
    try {
      const commander = selected(panel);
      const job = loadRequest(action, commander?.id, drawer.querySelector('[data-quick-quantity]').value, commander?.standard_load_quantity);
      await execute({ ...job, method: 'POST', query: { installation_id: panel.dataset.quickInstallation, commodity_id: row.dataset.commodity },
        key: crypto.randomUUID(), installation: panel.dataset.quickInstallation });
    } catch (failure) { message(panel, failure.message); }
  }
  for (const panel of panels) {
    panel.querySelector('[data-quick-identity-create]').addEventListener('click', () => {
      if (!busy && !pending && !blocked) execute({route:'commander/identity',method:'POST',query:{},body:{},key:crypto.randomUUID(),installation:panel.dataset.quickInstallation});
    });
    panel?.querySelector('[data-quick-commander]').addEventListener('change', () => { propose(panel); remember(panel); });
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
    const openRow = async row => {
      if (busy || pending || blocked) return;
      const record = JSON.parse(row.dataset.record);
      activePanel = panel; activeRow = row;
      drawer.querySelector('#quick-material-title').textContent = row.dataset.name;
      drawer.querySelector('[data-quick-installation-name]').textContent = listMode ? `${record.system_name} · ${record.installation_name}` : panel.querySelector('h2').textContent;
      drawer.querySelector('[data-quick-remaining]').textContent = units(row.dataset.remaining);
      drawer.querySelector('[data-quick-quantity]').value = '';
      drawer.querySelector('[data-quick-drawer-status]').textContent = '';
      if (listMode) {
        panel.dataset.quickInstallation = record.installation_id;
        const select = panel.querySelector('[data-quick-commander]');
        let saved = own?.id || '';
        try { saved = sessionStorage.getItem(`afec.quick.commander.${actor}.${record.installation_id}`) || saved; } catch { /* Checked at initialization. */ }
        if (saved && !commanders.has(saved)) {
          busy = true; lock();
          try {
            const result = await request('commander/load',{query:{id:saved}});
            if (result.data.active) { commanders.set(saved,result.data); select.add(new Option(result.data.display_name,saved)); }
          } catch (failure) { message(panel,failure.message); }
          finally { busy = false; }
        }
        select.value = commanders.has(saved) ? saved : '';
        select.setAttribute('aria-label',`Comandante para ${record.installation_name}`);
      }
      for (const button of drawer.querySelectorAll('[data-stock-planning]')) {
        button.dataset.record = button.dataset.action === 'history' ? JSON.stringify({type:'requirement',id:record.installation_id,commodity_id:record.commodity_id}) : row.dataset.record;
        button.disabled = button.dataset.action !== 'history' && (row.dataset.editable !== '1' || (button.dataset.action === 'requirement-edit' && !Number(record.commodity_active)));
        if (button.dataset.action === 'requirement-edit') button.textContent = Number(record.active) ? 'Editar objetivo' : 'Restaurar objetivo';
        if (button.dataset.action === 'requirement-archive') button.hidden = !Number(record.active);
      }
      propose(panel);
      if (listMode) drawer.querySelector('[data-quick-config]').open = !selected(panel)?.standard_load_quantity;
      lock(); drawer.showModal();
    };
    const rowContainer = listMode ? document : panel;
    rowContainer.addEventListener('click', event => {
      if (event.target.closest('button, a, input, select, textarea')) return;
      const row = event.target.closest('[data-quick-row]'); if (row) openRow(row);
    });
    rowContainer.addEventListener('keydown', event => {
      if (['Enter',' '].includes(event.key) && event.target.matches('[data-quick-row]')) { event.preventDefault(); openRow(event.target); }
    });
  }
  drawer.querySelector('[data-quick-close]').addEventListener('click', () => drawer.close());
  drawer.addEventListener('click', event => {
    const button = event.target.closest('[data-quick]');
    if (button && !button.disabled && event.detail <= 1) act(activePanel, activeRow, button.dataset.quick);
    if (event.target.closest('[data-stock-planning]')) drawer.close();
  });
  drawer.addEventListener('keydown', event => {
    if (event.key === 'Enter' && !event.repeat && event.target.matches('[data-quick-quantity]')) {
      event.preventDefault(); if (!drawer.querySelector('[data-quick="add"]').disabled) act(activePanel, activeRow, 'add');
    }
  });
  for (const input of document.querySelectorAll('[data-quick-preset], [data-quick-quantity]')) {
    input.addEventListener('keydown', event => { if (['e','E','+','-','.',','].includes(event.key)) event.preventDefault(); });
    input.addEventListener('paste', event => { if (!/^[0-9]*$/.test(event.clipboardData.getData('text'))) event.preventDefault(); });
  }
  retry.addEventListener('click', () => { if (pending) execute(pending); });
  lock();
  try { await options(); } catch (failure) { for (const panel of panels) message(panel, `${failure.message} Recargá para volver a cargar comandantes.`); }
}

if (typeof document !== 'undefined') initQuickLoad();
