<div class="quick-load-bar" data-quick-bar>
    <label>Comandante<select data-quick-commander aria-label="Comandante para <?= e($installation['name']) ?>" disabled><option value="">Cargando comandantes…</option></select></label>
    <button type="button" class="button-quiet" data-quick-more hidden>Más comandantes</button>
    <label>Palada estándar (t)<input data-quick-preset inputmode="numeric" pattern="[0-9]+" maxlength="19" placeholder="Sin configurar" aria-label="Palada estándar del comandante"></label>
    <button type="button" class="button-quiet" data-quick-save>Guardar palada</button>
    <label>Motivo de resta<input data-quick-reason maxlength="1000" placeholder="Corrección de carga"></label>
    <p class="data-note">Enter suma la cantidad de la fila. Se admiten sobrantes: se registra la cantidad completa. Las restas conservan el historial y requieren un motivo.</p>
    <p data-quick-status class="quick-status" role="status" aria-live="polite"></p>
</div>
