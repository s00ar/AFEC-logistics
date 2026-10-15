<div class="quick-load-bar" data-quick-bar>
    <label>Comandante<select data-quick-commander aria-label="Comandante para <?= e($installation['name']) ?>" disabled><option value="">Cargando comandantes…</option></select></label>
    <button type="button" class="button-quiet" data-quick-more hidden>Más comandantes</button>
    <button type="button" class="button-quiet" data-quick-identity-create hidden>Usar mi comandante</button>
    <label>Palada base (t)<input data-quick-preset type="number" min="1" max="9223372036854775807" step="1" inputmode="numeric" placeholder="Sin configurar" aria-label="Palada base del comandante"></label>
    <button type="button" class="button-quiet" data-quick-save>Guardar palada</button>
    <p class="data-note">Seleccioná un material para sumar o restar stock desde el panel lateral.</p>
    <p data-quick-status class="quick-status" role="status" aria-live="polite"></p>
</div>
