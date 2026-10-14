import { request } from './http.js';

const dialog = document.querySelector('#logistics-dialog');
const items = document.querySelector('#logistics-items');
const controls = document.querySelector('#logistics-controls');
const previous = document.querySelector('#logistics-previous');
const next = document.querySelector('#logistics-next');
const units = value => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
let current = null;
let page = 1;
let includeInactive = false;
let generation = 0;

function element(tag, text, className) {
  const node = document.createElement(tag);
  if (text !== undefined) node.textContent = text;
  if (className) node.className = className;
  return node;
}
function button(label, action) {
  const node = element('button', label, 'button-quiet'); node.type = 'button'; node.addEventListener('click', action); return node;
}
function edit(action, record) {
  dialog.close(); document.dispatchEvent(new CustomEvent('afec:edit', {detail:{action,record}}));
}
function copyLine(label, value) {
  const line = element('div', undefined, 'copy-line');
  const input = element('input'); input.value = value; input.readOnly = true; input.setAttribute('aria-label',label);
  const copy = button(`Copiar ${label.toLowerCase()}`, async () => {
    try { await navigator.clipboard.writeText(value); copy.textContent = 'Copiado'; }
    catch { input.focus(); input.select(); copy.textContent = 'Seleccionado: copiá con Ctrl+C'; }
  });
  line.append(input,copy); return line;
}
async function load() {
  const ticket = ++generation; previous.disabled = next.disabled = true; items.textContent = 'Cargando…';
  try {
    const route = current.type === 'sources' ? 'sources' : 'movements';
    const query = current.type === 'sources' ? {commodity_id:current.record.commodity_id,include_inactive:includeInactive?'1':'0',list_page:page} : {installation_id:current.record.installation_id,list_page:page};
    const response = await request(route,{query});
    if (ticket !== generation || !dialog.open) return;
    items.replaceChildren();
    if (!response.data.items.length) items.append(element('p','No hay registros en esta página.','muted'));
    for (const row of response.data.items) {
      const article = element('article',undefined,'history-entry');
      if (current.type === 'sources') {
        article.append(element('h3',`${row.system_name} · ${row.facility_name}${Number(row.active)?'':' · Retirado'}`));
        article.append(copyLine('Sistema',row.system_name),copyLine(row.facility_type==='settlement'?'Settlement':'Estación',row.facility_name));
        if (row.notes) article.append(element('p',row.notes,'muted'));
        article.append(element('p',`Actualizado: ${row.updated_at} UTC · ${row.author}`,'muted'));
        const actions = element('div',undefined,'row-actions');
        if (Number(row.active)) actions.append(button('Editar lugar',()=>edit('source-edit',row)),button('Retirar lugar',()=>edit('source-delete',row)));
        else actions.append(button('Restaurar lugar',()=>edit('source-restore',row)));
        article.append(actions);
      } else {
        const labels={delivery:'Entrega',reversal:'Retiro / corrección',opening_balance:'Saldo inicial',adjustment:'Ajuste',import_delivery:'Entrega importada'};
        article.append(element('h3',`${row.commodity_name} · ${units(row.delta_quantity)} · ${labels[row.movement_type]||row.movement_type}`));
        article.append(element('p',`Comandante: ${row.commander_name||'Sin atribución histórica'} · Registró: ${row.author}`));
        article.append(element('p',`${row.occurred_at||row.recorded_at} UTC${row.carrier_name?` · Carrier: ${row.carrier_name}`:''}`,'muted'));
        if (row.reverses_movement_id) article.append(element('p',`Corrección vinculada al movimiento #${row.reverses_movement_id}.`,'muted'));
        if (row.reason) article.append(element('p',row.reason,'muted'));
        if (row.can_reverse) article.append(button(`Retirar / corregir (hasta ${units(row.available_quantity)})`,()=>edit('movement-withdraw',row)));
      }
      items.append(article);
    }
    previous.hidden = page === 1; next.hidden = !response.data.pagination.has_more;
    previous.disabled = next.disabled = false;
  } catch (failure) { if (ticket === generation) items.textContent = failure.message; }
}

document.addEventListener('click', event => {
  const target = event.target.closest('[data-logistics]'); if (!target || target.disabled) return;
  let record; try { record = JSON.parse(target.dataset.record); } catch { return; }
  current = {type:target.dataset.logistics,record}; page = 1; includeInactive = false;
  document.querySelector('#logistics-title').textContent = `${current.type==='sources'?'Abastecimiento':'Entregas'} · ${record.name}`;
  controls.replaceChildren();
  if (current.type === 'sources') {
    controls.append(button('+ Agregar lugar',()=>edit('source-create',record)));
    const label = element('label',undefined,'confirm-row'); const check = element('input'); check.type = 'checkbox';
    check.addEventListener('change',()=>{includeInactive=check.checked;page=1;load();});
    label.append(check,document.createTextNode('Mostrar lugares retirados')); controls.append(label);
    controls.append(element('p','Ubicaciones compartidas aportadas por comandantes. No indican stock en tiempo real.','muted'));
  }
  if (!dialog.open) dialog.showModal(); load();
});
previous.addEventListener('click',()=>{page--;load();}); next.addEventListener('click',()=>{page++;load();});
document.querySelector('#close-logistics').addEventListener('click',()=>dialog.close());
dialog.addEventListener('close',()=>{++generation;});

document.addEventListener('click', async event => {
  const target = event.target.closest('[data-more-destinations]'); if (!target || target.disabled) return;
  const record = JSON.parse(target.dataset.record); target.disabled = true;
  try {
    const response = await request('wishlist/destinations',{query:record});
    const list = target.closest('[data-destinations]').querySelector('ul');
    for (const row of response.data.items) {
      const li = element('li'); const link = element('a',`${row.system_name} · ${row.installation_name}`);
      const url = new URL(document.querySelector('meta[name="home-url"]').content,window.location.href);
      url.searchParams.set('page','system'); url.searchParams.set('id',row.system_id); link.href = url;
      li.append(link,element('strong',units(row.remaining_quantity))); list.append(li);
    }
    if (!response.data.pagination.has_more) target.remove();
    else {record.list_page++;target.dataset.record=JSON.stringify(record);target.disabled=false;}
  } catch (failure) {target.disabled=false;target.textContent=`Reintentar: ${failure.message}`;}
});
