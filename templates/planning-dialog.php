<dialog id="planning-dialog" class="editor-dialog" aria-labelledby="editor-title">
    <div class="dialog-heading"><h2 id="editor-title">Editar planificación</h2><button type="button" class="button-quiet" data-close-dialog aria-label="Cerrar formulario">×</button></div>
    <p id="editor-description" class="muted"></p>
    <form id="planning-form">
        <div id="editor-fields"></div>
        <p id="planning-error" class="form-error" role="alert" hidden></p>
        <button id="reload-planning" class="button-quiet" type="button" hidden>Actualizar datos</button>
        <div class="dialog-actions"><button type="button" class="button-quiet" data-close-dialog>Cancelar</button><button id="planning-submit" class="button-primary" type="submit">Guardar</button></div>
    </form>
</dialog>
<template id="form-system">
    <label>Nombre del sistema<input name="name" maxlength="255" required></label>
    <label>Descripción<textarea name="description" maxlength="4000" rows="3"></textarea></label>
    <label>Motivo (opcional)<textarea name="reason" maxlength="1000" rows="2"></textarea></label>
</template>
<template id="form-catalogue">
    <input name="snapshot_sha256" type="hidden">
    <p class="muted">La carga usa un snapshot local de las fuentes AFEC y DaftMav. No conecta con Sheets ni modifica instalaciones existentes. Las cantidades comunitarias son referencias ajustables, y los ejemplos AFEC corresponden a proyectos concretos.</p>
    <label class="confirm-row"><input name="confirmed" type="checkbox" required>Confirmo cargar las recetas faltantes y conservar todos los ajustes existentes.</label>
</template>
<template id="form-installation">
    <label data-recipe-choice>Facility / receta<select name="facility_recipe_id"><option value="">Sin receta: cargar objetivos manualmente</option></select></label>
    <input type="hidden" name="facility_recipe_version" value="">
    <div data-recipe-preview class="recipe-preview" aria-live="polite" hidden></div>
    <label>Nombre de la instalación<input name="name" maxlength="255" required></label>
    <div class="form-columns"><label>Cuerpo<input name="body_name" maxlength="255"></label><label>Ubicación<input name="location_label" maxlength="255"></label></div>
    <div class="form-columns"><label>Tier<input name="tier" maxlength="20"></label><label>Tipo<input name="type" maxlength="80"></label></div>
    <input name="state" type="hidden" value="planned"><label>Orden visual<input name="sort_order" type="number" step="1" min="-2147483648" max="2147483647" value="0" required></label><p class="muted">Las instalaciones nuevas comienzan En construcción. Podés terminarlas o reabrirlas desde su panel.</p>
    <label>Motivo (opcional)<textarea name="reason" maxlength="1000" rows="2"></textarea></label>
</template>
<template id="form-recipe">
    <label>Nombre de la receta<input name="name" maxlength="160" required placeholder="Nombre y variante de la facility"></label>
    <div class="form-columns"><label>Tipo de facility<input name="type" maxlength="80" required placeholder="Base orbital, settlement…"></label><label>Tier<input name="tier" maxlength="20"></label></div>
    <label>Fuente / verificación<textarea name="source_note" maxlength="1000" rows="2" required placeholder="Referencia, fecha y variante; indicar si son cantidades aproximadas"></textarea></label>
    <label>URL de referencia (opcional)<input name="source_url" type="url" maxlength="1000"></label>
    <p class="muted recipe-help">Materiales y cantidades por instalación. No se modifican las entregas ni los objetivos de construcciones existentes.</p>
    <div data-recipe-materials></div><button type="button" class="button-quiet" data-add-recipe-material>+ Agregar material</button>
    <label>Motivo<textarea name="reason" maxlength="1000" rows="2" required></textarea></label>
</template>
<template id="form-recipe-material">
    <div class="recipe-material"><label>Material<select data-recipe-commodity required><option value="">Seleccionar material</option></select></label><label>Cantidad<input data-recipe-quantity type="text" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required></label><button type="button" class="button-quiet button-danger" data-remove-recipe-material>Quitar</button></div>
</template>
<template id="form-requirement">
    <label>Material<select name="commodity_id" required><option value="">Seleccioná un material</option></select></label>
    <label>Cantidad requerida<input name="required_quantity" type="text" inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required></label>
    <label>Motivo del cambio<textarea name="reason" maxlength="1000" rows="2" required></textarea></label>
</template>
<template id="form-archive">
    <label>Motivo<textarea name="reason" maxlength="1000" rows="2" required></textarea></label>
    <label class="confirm-row"><input name="confirmed" type="checkbox" required>Confirmo la baja y la conservación del historial.</label>
</template>
<template id="form-commodity">
    <label>Código del material<input name="code" maxlength="120" required></label>
    <label>Nombre en español<input name="name_es" maxlength="255" required></label>
    <label>Nombre en inglés (opcional)<input name="name_en" maxlength="255"></label>
    <label>Alias / equivalencias (uno por línea)<textarea name="aliases" maxlength="8000" rows="3" placeholder="Código alternativo o nombre usado en otra fuente"></textarea></label>
    <label class="confirm-row"><input name="preserve_previous" type="checkbox" checked>Conservar códigos y nombres anteriores como alias</label>
    <p class="muted">Los alias corresponden al mismo material. Desmarcá la conservación anterior cuando estés corrigiendo una identidad equivocada que pertenece a otra mercancía.</p>
    <label>Motivo<textarea name="reason" maxlength="1000" rows="2" required></textarea></label>
</template>
<template id="form-commodity-state"><label>Motivo del cambio<textarea name="reason" maxlength="1000" rows="2" required></textarea></label><label class="confirm-row"><input name="confirmed" type="checkbox" required>Confirmo desactivar esta mercancía para nuevas selecciones. Sus objetivos, entregas e historial se conservan.</label></template>
<dialog id="history-dialog" class="editor-dialog history-dialog" aria-labelledby="history-title">
    <div class="dialog-heading"><h2 id="history-title">Historial de cambios</h2><button type="button" class="button-quiet" id="close-history" aria-label="Cerrar historial">×</button></div>
    <div id="history-items" aria-live="polite"></div>
    <div class="dialog-actions"><button id="history-previous" type="button" class="button-quiet" hidden>Anterior</button><button id="history-next" type="button" class="button-quiet" hidden>Siguiente</button></div>
</dialog>
