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
    <label>Motivo<textarea name="reason" maxlength="1000" rows="2"></textarea></label>
</template>
<template id="form-installation">
    <label>Nombre de la instalación<input name="name" maxlength="255" required></label>
    <div class="form-columns"><label>Cuerpo<input name="body_name" maxlength="255"></label><label>Ubicación<input name="location_label" maxlength="255"></label></div>
    <div class="form-columns"><label>Tier<input name="tier" maxlength="20"></label><label>Tipo<input name="type" maxlength="80"></label></div>
    <input name="state" type="hidden" value="planned"><label>Orden visual<input name="sort_order" type="number" step="1" min="-2147483648" max="2147483647" value="0" required></label><p class="muted">Las instalaciones nuevas comienzan En construcción. Podés terminarlas o reabrirlas desde su panel.</p>
    <label>Motivo<textarea name="reason" maxlength="1000" rows="2"></textarea></label>
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
</template>
<dialog id="history-dialog" class="editor-dialog history-dialog" aria-labelledby="history-title">
    <div class="dialog-heading"><h2 id="history-title">Historial de cambios</h2><button type="button" class="button-quiet" id="close-history" aria-label="Cerrar historial">×</button></div>
    <div id="history-items" aria-live="polite"></div>
    <div class="dialog-actions"><button id="history-previous" type="button" class="button-quiet" hidden>Anterior</button><button id="history-next" type="button" class="button-quiet" hidden>Siguiente</button></div>
</dialog>
