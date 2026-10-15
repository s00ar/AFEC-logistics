<section class="installation-panel carrier-cargo" data-carrier-scope="<?= e($scope) ?>">
    <div class="installation-heading"><div><h2>Tu carga en carrier</h2><p class="muted">Elegí el carrier y usá los botones de cada mercancía para ir sumando lo que cargás. El reparto sigue el orden de destinos de esta lista.</p></div></div>
    <form data-carrier-form class="carrier-form">
        <label>Carrier<select name="carrier_id"><option value="">Registrar un carrier nuevo</option></select></label>
        <div data-carrier-new class="form-columns"><label>Nombre del carrier<input name="carrier_name" maxlength="160" required></label><label>Código (opcional)<input name="carrier_code" maxlength="40"></label></div>
        <div class="form-columns"><label>Material<select name="commodity_id" required><option value="">Seleccionar material</option></select></label><label>Total a bordo (t)<input name="quantity" type="number" min="0" max="9223372036854775807" step="1" inputmode="numeric" required></label></div>
        <p class="muted">Este formulario reemplaza el total y asigna la carga a esta lista, incluso si estaba en la otra. Usá 0 para vaciarlo. Los botones de los materiales suman o restan cantidades. La carga a bordo se mantiene separada de las entregas a instalaciones.</p>
        <div class="dialog-actions"><button type="submit" class="button-primary">Guardar total y repartir</button><button type="button" class="button-quiet" data-carrier-refresh>Actualizar reparto</button><button type="button" class="button-quiet" data-carrier-retry hidden>Comprobar y reintentar</button></div>
    </form>
    <p data-carrier-status class="carrier-message" role="status" aria-live="polite"></p>
    <div data-carrier-loads class="carrier-loads"></div>
</section>
