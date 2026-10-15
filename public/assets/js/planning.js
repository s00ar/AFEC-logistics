import { request } from './http.js';
import { accountUpdatePayload } from './accounts.js';

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
  'system-archive': { template: 'archive', title: 'Eliminar sistema (archivar)', route: 'system', method: 'DELETE', query: r => ({ id: r.id }), archive: true },
  'system-restore': { template: 'archive', title: 'Restaurar sistema', route: 'system/restore', method: 'POST', query: r => ({ id: r.id }) },
  'installation-create': { template: 'installation', title: 'Agregar instalación / facility', route: 'installations', method: 'POST', fields: ['name', 'body_name', 'location_label', 'tier', 'type', 'state', 'sort_order', 'reason', 'facility_recipe_id', 'facility_recipe_version'], query: r => ({ system_id: r.system_id }) },
  'installation-edit': { template: 'installation', title: 'Editar instalación', route: 'installation', method: 'PATCH', fields: ['name', 'body_name', 'location_label', 'tier', 'type', 'state', 'sort_order', 'reason'], query: r => ({ id: r.id }), edit: true },
  'installation-archive': { template: 'archive', title: 'Eliminar instalación (archivar)', route: 'installation', method: 'DELETE', query: r => ({ id: r.id }), archive: true },
  'installation-restore': { template: 'archive', title: 'Restaurar instalación', route: 'installation/restore', method: 'POST', query: r => ({ id: r.id }) },
  'requirement-create': { template: 'requirement', title: 'Agregar objetivo', route: 'requirement', method: 'PUT' },
  'requirement-edit': { template: 'requirement', title: 'Editar objetivo', route: 'requirement', method: 'PUT', edit: true },
  'requirement-archive': { template: 'archive', title: 'Retirar objetivo', route: 'requirement', method: 'DELETE', query: r => ({ installation_id: r.installation_id, commodity_id: r.commodity_id }), archive: true },
  'commodity-create': { template: 'commodity', title: 'Crear mercancía', route: 'commodities', method: 'POST', fields: ['code','name_es','name_en','aliases','reason'], keepPage:true },
  'commodity-edit': { template:'commodity', title:'Editar mercancía y equivalencias', route:'admin/commodity', method:'PATCH', fields:['code','name_es','name_en','aliases','preserve_previous','reason'], query:r=>({id:r.id}), edit:true, keepPage:true },
  'commodity-deactivate': { template:'commodity-state', title:'Desactivar mercancía', route:'admin/commodity', method:'DELETE', fields:['reason','confirmed'], query:r=>({id:r.id}), edit:true, keepPage:true },
  'commodity-restore': { template:'commodity-state', title:'Reactivar mercancía', route:'admin/commodity/restore', method:'POST', fields:['reason'], query:r=>({id:r.id}), edit:true, keepPage:true },
  'user-create': { template: 'user', title: 'Crear usuario', route: 'admin/users', method: 'POST', fields: ['username','display_name','role','password','reason'], secret: true, scope: 'admin.user.create', userAction: true },
  'user-edit': { template: 'user', title: 'Editar usuario y rol', route: 'admin/user', method: 'PATCH', fields: ['username','display_name','role','active','reason'], query: r => ({id:r.id}), edit: true, userAction: true },
  'user-role': { template: 'user-role', title: 'Cambiar rol de la cuenta', route: 'admin/user', method: 'PATCH', fields: ['role','reason'], query: r => ({id:r.id}), edit: true, userAction: true },
  'user-password': { template: 'user-password', title: 'Restablecer contraseña', route: 'admin/user/password', method: 'POST', fields: ['password','reason'], query: r => ({id:r.id}), edit: true, secret: true, scope: 'admin.user.password', userAction: true },
  'user-delete': { template: 'archive', title: 'Eliminar usuario', route: 'admin/user', method: 'DELETE', query: r => ({id:r.id}), archive: true, userAction: true },
  'user-restore': { template: 'archive', title: 'Restaurar usuario', route: 'admin/user/restore', method: 'POST', query: r => ({id:r.id}), userAction: true },
  'installation-status': { template: 'status', title: 'Cambiar estado de construcción', route: 'installation/status', method: 'PATCH', fields: ['construction_status','reason','confirmed'], query: r => ({id:r.id}), edit: true },
  'list-add': { template: 'list', title: 'Añadir instalación a lista', route: 'wishlist/entries', method: 'POST', fields: ['scope'], query: r => ({installation_id:r.installation_id}), keepPage: true },
  'list-remove': { template: 'list', title: 'Quitar instalación de esta lista', route: 'wishlist/entry', method: 'DELETE', fields: ['scope','reason'], query: r => ({installation_id:r.installation_id}), edit: true, keepPage: true },
  'source-create': { template: 'source', title: 'Agregar lugar de abastecimiento', route: 'sources', method: 'POST', fields: ['system_name','facility_name','facility_type','notes','reason'], commodityBody: true, keepPage: true },
  'source-edit': { template: 'source', title: 'Editar lugar de abastecimiento', route: 'source', method: 'PATCH', fields: ['system_name','facility_name','facility_type','notes','reason'], query: r => ({id:r.id}), edit: true, keepPage: true },
  'source-delete': { template: 'archive', title: 'Retirar lugar de abastecimiento', route: 'source', method: 'DELETE', query: r => ({id:r.id}), archive: true, keepPage: true },
  'source-restore': { template: 'archive', title: 'Restaurar lugar de abastecimiento', route: 'source/restore', method: 'POST', query: r => ({id:r.id}), keepPage: true },
  'delivery-create': { template: 'delivery', title: 'Registrar entrega recibida', route: 'deliveries', method: 'POST', fields: ['quantity','commander_id','commander_name','carrier_id','carrier_name','carrier_code','occurred_at','reason'], query: r => ({installation_id:r.installation_id,commodity_id:r.commodity_id}), keepPage: true },
  'movement-withdraw': { template: 'withdraw', title: 'Retirar / corregir material registrado', route: 'movement/withdraw', method: 'POST', fields: ['quantity','reason','confirmed'], query: r => ({id:r.id}), keepPage: true },
  'recipe-create': { template:'recipe', title:'Crear receta de facility', route:'facility/recipes', method:'POST', fields:['name','type','tier','source_note','source_url','reason'], keepPage:true },
  'recipe-edit': { template:'recipe', title:'Editar receta y materiales', route:'facility/recipe', method:'PATCH', fields:['name','type','tier','source_note','source_url','reason'], query:r=>({id:r.id}), edit:true, keepPage:true },
  'recipe-archive': { template:'archive', title:'Eliminar receta (archivar)', route:'facility/recipe', method:'DELETE', query:r=>({id:r.id}), archive:true, keepPage:true },
  'recipe-restore': { template:'archive', title:'Restaurar receta', route:'facility/recipe/restore', method:'POST', query:r=>({id:r.id}), keepPage:true },
  'catalogue-import': { template:'catalogue', title:'Cargar catálogo AFEC + comunidad', route:'facility/catalogue/import', method:'POST', fields:['confirmed','snapshot_sha256'], keepPage:true },
};
let active = null;
let pending = null;
let storageBlocked = false;
let busy = false;
let editorGeneration = 0;
let recipeGeneration = 0;
let recipeCommodities = [];
const scopes = {
  'system-create':'planning.system.create','system-edit':'planning.system.update','system-archive':'planning.system.archive','system-restore':'planning.system.restore',
  'installation-create':'planning.installation.create','installation-edit':'planning.installation.update','installation-archive':'planning.installation.archive','installation-restore':'planning.installation.restore',
  'requirement-create':'planning.requirement.save','requirement-edit':'planning.requirement.save','requirement-archive':'planning.requirement.remove','commodity-create':'planning.commodity.create',
  'user-create':'admin.user.create','user-edit':'admin.user.update','user-role':'admin.user.update','user-delete':'admin.user.delete','user-restore':'admin.user.restore','user-password':'admin.user.password',
  'commodity-edit':'planning.commodity.update','commodity-deactivate':'planning.commodity.deactivate','commodity-restore':'planning.commodity.restore',
  'installation-status':'logistics.status','list-add':'logistics.list.add','list-remove':'logistics.list.remove',
  'source-create':'logistics.source.create','source-edit':'logistics.source.update','source-delete':'logistics.source.delete','source-restore':'logistics.source.restore',
  'delivery-create':'logistics.delivery','movement-withdraw':'logistics.withdraw',
  'recipe-create':'planning.recipe.create','recipe-edit':'planning.recipe.update','recipe-archive':'planning.recipe.archive','recipe-restore':'planning.recipe.restore',
  'catalogue-import':'planning.catalogue.import',
};

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
  for (const button of fields.querySelectorAll('button')) button.disabled = on;
  if (!on && active?.record.commodity_id && form.elements.commodity_id) form.elements.commodity_id.disabled = true;
  if (!on && active?.action === 'user-edit') form.elements.username.disabled = !fields.querySelector('[data-rename-username]').checked;
  if (!busy && pending?.secretMissing && form.elements.password) form.elements.password.disabled = false;
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
    ? (definition.userAction ? 'Se revocará el acceso de la cuenta. Sus entregas e historial se conservarán.' : action === 'source-delete' ? 'El lugar dejará de aparecer como abastecimiento activo. Su historial se conservará.' : 'Se archivará y dejará de aparecer en la planificación activa. Podés restaurarlo desde el filtro de eliminados. Sus registros y movimientos se conservan.')
    : definition.userAction && record.username ? `${record.display_name} (${record.username})` : record.name || record.name_es || record.display_name || record.commodity_name || 'Completá los datos de la operación.';
  for (const control of fields.querySelectorAll('[name]')) {
    if (Object.hasOwn(record, control.name)) control.type === 'checkbox' ? control.checked = Boolean(record[control.name]) : control.value = record[control.name] ?? '';
  }
  if (definition.template === 'user') {
    const hide = action === 'user-edit' ? form.elements.password : form.elements.active;
    hide.closest('label').remove();
    const rename = fields.querySelector('[data-rename-username]');
    rename.closest('label').hidden = action !== 'user-edit';
    if (action === 'user-edit') {
      rename.checked = resume && pending.request.body.username !== record.username;
      form.elements.username.disabled = !rename.checked;
      rename.addEventListener('change', () => {
        form.elements.username.value = active.record.username;
        form.elements.username.disabled = !rename.checked;
        if (rename.checked) form.elements.username.focus();
      });
    }
  }
  if (definition.template === 'commodity') {
    form.elements.aliases.value = (record.aliases || []).join('\n');
    form.elements.preserve_previous.closest('label').hidden = action !== 'commodity-edit';
    if (record.aliases_ready === false) { form.elements.aliases.closest('label').hidden = true; }
  }
  if (action === 'commodity-restore') form.elements.confirmed.closest('label').remove();
  if (definition.template === 'status') {
    form.elements.construction_status.value = record.next_status;
    if (record.next_status !== 'completed') { form.elements.confirmed.required = false; form.elements.confirmed.closest('label').hidden = true; }
  }
  if (definition.template === 'list' && action === 'list-add') { form.elements.reason.required = false; form.elements.reason.closest('label').hidden = true; }
  if (definition.template === 'withdraw') form.elements.quantity.value = record.available_quantity;
  if (definition.edit && form.elements.reason && !['system','installation'].includes(definition.template)) form.elements.reason.required = true;
  if (['commodity','requirement','recipe','source','delivery','withdraw'].includes(definition.template)) {
    const reason = form.elements.reason;
    reason.required = false; reason.value = definition.title; reason.closest('label').hidden = true;
  }
  if (definition.template === 'archive' && !definition.archive) {
    const check = form.elements.confirmed;
    check.required = false;
    check.closest('label').hidden = true;
  }
  if (resume) {
    for (const [name, value] of Object.entries(pending.request.body)) {
      const control = form.elements[name];
      if (control) {
        if (control.type === 'checkbox') control.checked = value;
        else if (name === 'aliases' && Array.isArray(value)) control.value = value.join('\n');
        else if (name === 'occurred_at' && value) {
          const date = new Date(value); const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
          control.value = local.toISOString().slice(0,19);
        } else control.value = value ?? '';
      }
    }
  }
  if (!dialog.open) dialog.showModal();
  busyState(false);
  if (definition.template === 'installation') {
    fields.querySelector('[data-recipe-choice]').hidden = action !== 'installation-create';
    if (action === 'installation-create') {
      submit.disabled = true;
      try {
        let page = 1; let more;
        do {
          const result = await request('facility/recipes', {query:{list_page:page}});
          if (generation !== editorGeneration || !dialog.open) return;
          for (const recipe of result.data) {
            const option = document.createElement('option'); option.value = recipe.id;
            option.textContent = `${recipe.name}${recipe.tier ? ` · Tier ${recipe.tier}` : ''}`;
            form.elements.facility_recipe_id.append(option);
          }
          more = result.pagination.has_more; page++;
        } while (more);
        if (resume) {
          const selected = pending.request.body.facility_recipe_id;
          if (selected && !Array.from(form.elements.facility_recipe_id.options).some(option=>option.value===selected)) {
            const option=document.createElement('option'); option.value=selected; option.textContent='Receta de la operación pendiente'; form.elements.facility_recipe_id.append(option);
          }
          form.elements.facility_recipe_id.value=selected || '';
          busyState(false);
        } else if (form.elements.facility_recipe_id.options.length === 1) {
          const preview = fields.querySelector('[data-recipe-preview]'); preview.hidden=false;
          preview.textContent='No hay recetas cargadas. Crealas en Facilities y recetas; también podés cargar objetivos manualmente.';
          busyState(false);
        } else busyState(false);
      } catch (failure) {
        if (generation !== editorGeneration || !dialog.open) return;
        errorBox.textContent=failure.message; errorBox.hidden=false; submit.disabled=!resume;
      }
    }
  }
  if (definition.template === 'recipe') {
    recipeCommodities=[]; submit.disabled=true;
    try {
      const results=await Promise.all([request('commodities'), action==='recipe-edit' && !resume ? request('facility/recipe',{query:{id:record.id}}) : Promise.resolve(null)]);
      if (generation !== editorGeneration || !dialog.open) return;
      recipeCommodities=results[0].data;
      const data=resume ? pending.request.body : results[1]?.data || record;
      if (data && !resume) {
        active.record=data;
        for (const control of fields.querySelectorAll('[name]')) if (Object.hasOwn(data,control.name)) control.value=data[control.name] ?? '';
      }
      for (const material of data?.materials || [{}]) addRecipeMaterial(material);
      busyState(false);
    } catch (failure) {
      if (generation !== editorGeneration || !dialog.open) return;
      if (resume) for (const material of pending.request.body.materials) addRecipeMaterial(material);
      errorBox.textContent=failure.message; errorBox.hidden=false; submit.disabled=!resume;
    }
  }
  if (definition.template === 'delivery') {
    submit.disabled = true;
    try {
      const result = await request('logistics/options');
      if (generation !== editorGeneration || !dialog.open) return;
      for (const [kind, field] of [['commanders','commander_id'],['carriers','carrier_id']]) {
        const select = form.elements[field]; if (!select) continue;
        for (const value of result.data[kind]) {
          const option = document.createElement('option'); option.value = value.id;
          if (kind === 'commanders') option.dataset.standardLoad = value.standard_load_quantity || '';
          option.textContent = value.display_name || `${value.name}${value.code ? ` (${value.code})` : ''}`; select.append(option);
        }
        if (resume) select.value = pending.request.body[field] || '';
      }
      const commander = form.elements.commander_id;
      if (commander && !resume) {
        const proposeLoad = () => { form.elements.quantity.value = commander.selectedOptions[0]?.dataset.standardLoad || ''; };
        commander.addEventListener('change', proposeLoad);
        const card = document.querySelector(`[data-quick-installation="${record.installation_id}"]`);
        if (card) commander.value = card.querySelector('[data-quick-commander]')?.value || '';
        proposeLoad();
      }
      busyState(false);
    } catch (failure) { errorBox.textContent = failure.message; errorBox.hidden = false; submit.disabled = !resume; }
  }
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

function addRecipeMaterial(material = {}) {
  const container=fields.querySelector('[data-recipe-materials]');
  if (!container || container.children.length>=100) { toast('La receta admite hasta 100 materiales.'); return; }
  const row=document.querySelector('#form-recipe-material').content.cloneNode(true);
  const select=row.querySelector('[data-recipe-commodity]');
  for (const commodity of recipeCommodities) {
    const option=document.createElement('option'); option.value=commodity.id; option.textContent=commodity.name_es; select.append(option);
  }
  if (material.commodity_id && !Array.from(select.options).some(option=>option.value===material.commodity_id)) {
    const option=document.createElement('option'); option.value=material.commodity_id; option.textContent=material.commodity_name || `Material ${material.commodity_id} (inactivo)`; select.append(option);
  }
  select.value=material.commodity_id || '';
  row.querySelector('[data-recipe-quantity]').value=material.required_quantity || '';
  container.append(row);
}

async function selectRecipe() {
  if (pending || busy || active?.action !== 'installation-create') return;
  const generation=editorGeneration; const selectedGeneration=++recipeGeneration;
  const id=form.elements.facility_recipe_id.value;
  const preview=fields.querySelector('[data-recipe-preview]');
  form.elements.facility_recipe_version.value='';
  for (const name of ['type','tier']) { form.elements[name].readOnly=false; form.elements[name].value=''; }
  preview.replaceChildren(); preview.hidden=!id;
  errorBox.hidden=true;
  if (!id) { busyState(false); return; }
  preview.textContent='Cargando materiales de la facility…'; submit.disabled=true;
  try {
    const result=await request('facility/recipe',{query:{id}});
    if (generation!==editorGeneration || selectedGeneration!==recipeGeneration || !dialog.open) return;
    if (result.data.archived_at) throw new Error('Esta receta fue eliminada. Elegí otra o cargá objetivos manualmente.');
    form.elements.facility_recipe_version.value=result.data.version;
    for (const name of ['type','tier']) { form.elements[name].value=result.data[name] || ''; form.elements[name].readOnly=true; }
    preview.replaceChildren();
    const title=document.createElement('strong'); title.textContent=`${result.data.name} · versión ${result.data.version}`; preview.append(title);
    const source=document.createElement('p'); source.className='muted recipe-help'; source.textContent=result.data.source_note; preview.append(source);
    const list=document.createElement('ul');
    for (const material of result.data.materials) {
      const row=document.createElement('li'); row.textContent=`${material.commodity_name}: ${quantity(material.required_quantity)}`; list.append(row);
    }
    preview.append(list);
    const note=document.createElement('p'); note.className='recipe-help'; note.textContent='Se cargarán estos objetivos al guardar. Después podés ajustar cada cantidad desde la instalación.'; preview.append(note);
    busyState(false);
  } catch (failure) {
    if (generation!==editorGeneration || selectedGeneration!==recipeGeneration || !dialog.open) return;
    preview.textContent='No se pudieron confirmar los materiales.'; errorBox.textContent=failure.message; errorBox.hidden=false; submit.disabled=true;
  }
}

fields.addEventListener('change',event=>{ if (event.target.name==='facility_recipe_id') selectRecipe(); });
fields.addEventListener('click',event=>{
  if (busy || pending) return;
  if (event.target.closest('[data-add-recipe-material]')) addRecipeMaterial();
  const remove=event.target.closest('[data-remove-recipe-material]');
  if (remove) { const rows=fields.querySelectorAll('.recipe-material'); if (rows.length>1) remove.closest('.recipe-material').remove(); else toast('La receta debe conservar al menos un material.'); }
});

function remember(operation) {
  const saved = JSON.parse(JSON.stringify(operation));
  if (definitions[operation.action].secret) { delete saved.request.body.password; saved.secretMissing = true; }
  sessionStorage.setItem(storageKey, JSON.stringify(saved));
}

function finish(operation, result) {
  try { sessionStorage.removeItem(storageKey); } catch { /* Reconfirming a saved receipt is harmless. */ }
  pending = null;
  try { sessionStorage.setItem('afec.planning.flash', 'La operación se guardó correctamente.'); } catch { /* Already confirmed. */ }
  const definition = definitions[operation.action];
  if (definition.keepPage) { window.location.reload(); return; }
  const destination = new URL(home, window.location.href);
  if (definition.userAction) {
    destination.searchParams.set('page', operation.record.id === actorId ? 'login' : 'users');
  } else if (operation.action !== 'system-archive' && operation.action !== 'commodity-create') {
    destination.searchParams.set('page', 'system'); destination.searchParams.set('id', result.data.system_id || result.data.id);
    if (['installation-archive','requirement-archive'].includes(operation.action)) destination.searchParams.set('include_archived','1');
  }
  window.location.assign(destination);
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (busy || !active || (!pending && !form.reportValidity())) return;
  const definition = definitions[active.action];
  const wasPending = Boolean(pending);
  let priorResultAbsent = !wasPending;
  let operation = pending;
  if (!operation) {
    let body = {};
    for (const name of definition.fields || []) {
      const control = form.elements[name]; if (!control) continue;
      body[name] = control.type === 'checkbox' ? control.checked : control.value;
      if (name === 'occurred_at' && control.value) body[name] = new Date(control.value).toISOString().replace(/\.\d{3}Z$/,'Z');
      if (name === 'aliases') body[name] = control.value.split(/\r?\n/).map(value=>value.trim()).filter(Boolean);
    }
    if (definition.template==='recipe') {
      body.materials=Array.from(fields.querySelectorAll('.recipe-material'),row=>({commodity_id:row.querySelector('[data-recipe-commodity]').value, required_quantity:row.querySelector('[data-recipe-quantity]').value}));
      if (!body.materials.length || body.materials.some(row=>!/[1-9]/.test(row.required_quantity)) || new Set(body.materials.map(row=>row.commodity_id)).size!==body.materials.length) {
        errorBox.textContent='Usá cantidades positivas y un único renglón por material.'; errorBox.hidden=false; return;
      }
    }
    if (definition.commodityBody) body.commodity_id = active.record.commodity_id;
    if (definition.template === 'requirement') {
      body.required_quantity = form.elements.required_quantity.value;
      body.version = active.record.version || '0';
      body.reason = form.elements.reason.value;
    } else if (definition.template === 'archive') {
      body.version = active.record.version;
      body.reason = form.elements.reason.value;
      if (definition.archive) body.confirmed = form.elements.confirmed.checked;
    } else if (definition.edit) body.version = active.record.version;
    if (['user-edit', 'user-role'].includes(active.action)) {
      body = accountUpdatePayload(active.record, body, active.action === 'user-edit' && fields.querySelector('[data-rename-username]').checked);
    }
    const query = definition.template === 'requirement'
      ? { installation_id: active.record.installation_id, commodity_id: active.record.commodity_id || form.elements.commodity_id.value }
      : queryFor(definition, active.record, body);
    const savedRecord = definition.template === 'requirement' ? { ...active.record, commodity_id: query.commodity_id } : active.record;
    operation = { action: active.action, record: savedRecord, actor_id: actorId,
      request: { route: definition.route, method: definition.method, query, body, key: crypto.randomUUID() } };
    try { remember(operation); }
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
    if (wasPending) {
      const receipt = await request('operation', {query:{scope:scopes[operation.action],key:operation.request.key}});
      if (receipt.data) { finish(operation, receipt.data); return; }
      priorResultAbsent = true;
    }
    if (operation.secretMissing) {
      busyState(false);
      if (!form.elements.password.reportValidity()) {
        errorBox.textContent = 'La operación no tiene un resultado confirmado. Reingresá la contraseña para reintentar con la misma clave.';
        errorBox.hidden = false; return;
      }
      operation.request.body.password = form.elements.password.value; operation.secretMissing = false;
      busyState(true);
    }
    const result = await request(operation.request.route, operation.request);
    finish(operation, result);
  } catch (failure) {
    if (!failure.uncertain && priorResultAbsent) {
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
const labels = { name: 'Nombre', description: 'Descripción', body_name: 'Cuerpo', location_label: 'Ubicación', tier: 'Tier', type: 'Tipo', state: 'Estado anterior', construction_status: 'Construcción', sort_order: 'Orden', archived_at: 'Archivo', source_note:'Fuente', source_url:'URL de referencia', code:'Código',name_es:'Nombre en español',name_en:'Nombre en inglés',aliases:'Equivalencias',active:'Activa' };
const actions = { create: 'Creación', update: 'Edición', archive: 'Archivo', restore: 'Restauración', status: 'Cambio de construcción', deactivate:'Desactivación' };
const quantity = value => String(value ?? '—').replace(/\B(?=(\d{3})+(?!\d))/g, '.');

async function loadHistory() {
  const generation = ++historyGeneration;
  previous.disabled = next.disabled = true;
  historyItems.textContent = 'Cargando cambios…';
  try {
    const route = historyRecord.type==='commodity' ? 'admin/commodity/history' : historyRecord.type==='facility_recipe' ? 'facility/recipe/history' : 'planning/history';
    const result = await request(route, { query: { ...historyRecord, list_page: historyPage } });
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
        if (historyRecord.type==='facility_recipe') {
          for (const [stage,label] of [['before','Antes'],['after','Después']]) {
            const materials=item.changes[stage]?.materials;
            if (!materials) continue;
            const line=document.createElement('p'); line.className='muted'; line.textContent=`${label}: ${materials.map(row=>`${row.commodity_name || row.commodity_id}: ${quantity(row.required_quantity)}`).join(' · ')}`; article.append(line);
          }
        }
        for (const [field, label] of Object.entries(labels)) {
          const before = item.changes.before?.[field] ?? null;
          const after = item.changes.after?.[field] ?? null;
          if (JSON.stringify(before) === JSON.stringify(after)) continue;
          const line = document.createElement('p');
          line.className = 'muted';
          const display = value => Array.isArray(value) ? value.join(' · ') || '—' : typeof value === 'boolean' ? value ? 'Sí' : 'No' : value ?? '—';
          line.textContent = `${label}: ${display(before)} → ${display(after)}`;
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
document.addEventListener('afec:edit', event => openEditor(event.detail.action, event.detail.record));
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
