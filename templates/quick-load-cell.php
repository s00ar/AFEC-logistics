<div class="quick-load-cell" data-quick-row data-commodity="<?= e($requirement['commodity_id']) ?>" data-remaining="<?= e($requirement['remaining_quantity']) ?>">
    <label class="sr-only" for="quick-<?= e($installation['id']) ?>-<?= e($requirement['commodity_id']) ?>">Cantidad de <?= e($requirement['commodity_name']) ?></label>
    <input id="quick-<?= e($installation['id']) ?>-<?= e($requirement['commodity_id']) ?>" data-quick-quantity inputmode="numeric" pattern="[0-9]+" maxlength="19" placeholder="Toneladas" <?= !$open || !(bool)$requirement['active'] ? 'disabled' : '' ?>>
    <div class="quick-load-actions">
        <button type="button" class="button-primary" data-quick="add-load" <?= !$open || !(bool)$requirement['active']?'disabled':'' ?>>+ Palada</button>
        <button type="button" class="button-quiet" data-quick="add" <?= !$open || !(bool)$requirement['active']?'disabled':'' ?>>+ Cantidad</button>
        <button type="button" class="button-quiet" data-quick="subtract">− Cantidad</button>
        <button type="button" class="button-quiet" data-quick="subtract-load">− Palada</button>
    </div>
</div>
<button type="button" class="button-quiet quick-details" data-action="delivery-create" data-record="<?= record_attribute($requirement+['system_id'=>$system['id']]) ?>" <?= !$open || !(bool)$requirement['active']?'disabled':'' ?>>Entrega detallada / carrier</button>
