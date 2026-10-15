<a class="back-link" href="<?= e($app->url('/')) ?>">← Todos los sistemas</a>
<div class="page-heading">
    <div><p class="eyebrow">SISTEMA DE COLONIZACIÓN</p><h1><?= e($system['name']) ?></h1><?php if ($system['description']): ?><p class="muted"><?= e($system['description']) ?></p><?php endif ?></div>
    <?php if ($system['archived_at']): ?><span class="badge">Archivado</span><?php endif ?>
</div>
<?php if ($user !== null): ?><div class="toolbar"><button type="button" class="button-primary" data-action="installation-create" data-record="<?= record_attribute(['system_id' => $system['id']]) ?>" <?= $system['archived_at'] ? 'disabled' : '' ?>>+ Agregar instalación / facility</button></div><?php endif ?>
<?php if ($canPlan): ?>
<?php $systemRecord = array_intersect_key($system, array_flip(['id', 'name', 'description', 'archived_at', 'version'])); ?>
<div class="toolbar">
    <button type="button" class="button-quiet" data-action="system-edit" data-record="<?= record_attribute($systemRecord) ?>">Editar sistema</button>
    <button type="button" class="button-quiet <?= !$system['archived_at'] ? 'button-danger' : '' ?>" data-action="<?= $system['archived_at'] ? 'system-restore' : 'system-archive' ?>" data-record="<?= record_attribute($systemRecord) ?>"><?= $system['archived_at'] ? 'Restaurar sistema' : 'Eliminar sistema' ?></button>
    <button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'system', 'id' => $system['id']]) ?>">Ver cambios del sistema</button>
    <a class="button-quiet" href="<?= e($app->url('/?' . http_build_query(['page' => 'system', 'id' => $system['id'], 'include_archived' => $includeArchived ? '0' : '1']))) ?>"><?= $includeArchived ? 'Mostrar activos' : 'Mostrar eliminadas / archivadas y objetivos retirados' ?></a>
</div>
<?php endif ?>
<?php if ($system['installations'] === []): ?><section class="empty-state"><h2>No hay instalaciones en esta página</h2><p>Las instalaciones y sus materiales aparecerán aquí.</p></section><?php endif ?>
<?php if ($user!==null): ?><div id="quick-pending" class="pending-banner" role="status" hidden><p>Hay una operación de carga rápida cuyo resultado falta confirmar.</p><button type="button" class="button-quiet" id="quick-retry">Comprobar y reintentar la misma operación</button></div><?php endif ?>
<?php foreach ($system['installations'] as $installation): ?>
<?php
    $requirements = array_values(array_filter($system['requirements'], static fn(array $row): bool => $row['installation_id'] === $installation['id']));
    $editable = !$system['archived_at'] && !$installation['archived_at'];
    $open = $editable && $installation['construction_status'] !== 'completed';
    $recipeMaterials = array_values(array_map(static fn(array $row): array => ['commodity_id' => $row['commodity_id'], 'required_quantity' => $row['required_quantity']], array_filter($requirements, static fn(array $row): bool => (bool)$row['active'] && (bool)$row['commodity_active'] && $row['required_quantity'] !== '0')));
?>
<section class="installation-panel" data-quick-installation="<?= e($installation['id']) ?>">
    <div class="installation-heading">
        <div><h2><?= e($installation['name']) ?></h2><p class="muted"><?= e($installation['body_name'] ?: $installation['location_label'] ?: 'Ubicación sin especificar') ?></p><?php if ($installation['type'] || $installation['tier']): ?><p class="muted facility-meta"><?= e($installation['type'] ?: 'Tipo sin especificar') ?><?= $installation['tier'] ? ' · Tier ' . e($installation['tier']) : '' ?></p><?php endif ?></div>
        <div class="status-badges"><span class="badge <?= $installation['construction_status']==='completed'?'complete':'' ?>"><?= $installation['construction_status']==='completed'?'Terminada':($installation['construction_status']==='under_construction'?'En construcción':'Sin clasificar') ?></span><span class="badge" data-quick-progress><?= $installation['archived_at'] ? 'Archivada' : ($installation['percent'] === null ? 'Sin objetivos' : e(number_format($installation['percent'], 1, ',', '.')) . '%') ?></span></div>
    </div>
    <?php if ($canPlan): ?>
    <div class="installation-actions">
        <button type="button" class="button-quiet" data-action="installation-edit" data-record="<?= record_attribute($installation) ?>" <?= $system['archived_at'] ? 'disabled' : '' ?>>Editar instalación</button>
        <button type="button" class="button-quiet <?= !$installation['archived_at'] ? 'button-danger' : '' ?>" data-action="<?= $installation['archived_at'] ? 'installation-restore' : 'installation-archive' ?>" data-record="<?= record_attribute($installation) ?>" <?= $system['archived_at'] && $installation['archived_at'] ? 'disabled' : '' ?>><?= $installation['archived_at'] ? 'Restaurar instalación' : 'Eliminar instalación' ?></button>
        <button type="button" class="button-quiet" data-action="requirement-create" data-record="<?= record_attribute(['installation_id' => $installation['id'], 'system_id' => $system['id'], 'used_commodity_ids' => array_column($requirements, 'commodity_id')]) ?>" <?= !$editable ? 'disabled' : '' ?>>+ Agregar objetivo</button>
        <?php if ($recipeMaterials !== []): ?><button type="button" class="button-quiet" data-action="recipe-create" data-record="<?= record_attribute(['name' => $installation['type'] ?: $installation['name'], 'type' => $installation['type'] ?: '', 'tier' => $installation['tier'], 'source_note' => 'Objetivos registrados en ' . $installation['name'] . '. Revisar variante y cantidades en el juego.', 'materials' => $recipeMaterials, 'reason' => 'Crear plantilla desde objetivos existentes']) ?>">Guardar objetivos como receta</button><?php endif ?>
        <button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'installation', 'id' => $installation['id']]) ?>">Ver cambios</button>
        <button type="button" class="button-quiet" data-action="installation-status" data-record="<?= record_attribute($installation+['next_status'=>$installation['construction_status']==='completed'?'under_construction':'completed']) ?>" <?= !$editable?'disabled':'' ?>><?= $installation['construction_status']==='completed'?'Reabrir construcción':'Marcar terminada' ?></button>
        <?php if($installation['construction_status']===null): ?><button type="button" class="button-quiet" data-action="installation-status" data-record="<?= record_attribute($installation+['next_status'=>'under_construction']) ?>" <?= !$editable?'disabled':'' ?>>Marcar en construcción</button><?php endif ?>
    </div>
    <?php endif ?>
    <?php if($user!==null): ?><div class="installation-actions"><button type="button" class="button-quiet" data-action="list-add" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'scope'=>'personal','name'=>$installation['name']]) ?>" <?= !$open?'disabled':'' ?>>Añadir a mi lista</button><button type="button" class="button-quiet" data-action="list-add" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'scope'=>'fleet','name'=>$installation['name']]) ?>" <?= !$open?'disabled':'' ?>>Añadir a la flota</button><button type="button" class="button-quiet" data-logistics="movements" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'name'=>$installation['name']]) ?>">Historial de entregas</button></div><?php endif ?>
    <?php if($installation['construction_status']==='completed' && $installation['remaining_quantity']!=='0'): ?><p class="data-note">Terminada manualmente con <?= units($installation['remaining_quantity']) ?> unidades pendientes en el registro. Queda excluida de las listas de deseos.</p><?php endif ?>
    <?php if ($user!==null): require __DIR__.'/quick-load-toolbar.php'; endif ?>
    <p class="table-hint">Deslizá la tabla para ver todas las columnas.</p>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Materiales de <?= e($installation['name']) ?>">
        <table><caption class="sr-only">Requerimientos y entregas de <?= e($installation['name']) ?></caption><thead><tr><th scope="col">Material</th><th scope="col" class="numeric">Requerido</th><th scope="col" class="numeric">Entregado</th><th scope="col" class="numeric">Pendiente</th><th scope="col" class="numeric">Sobrante</th></tr></thead><tbody>
        <?php foreach ($requirements as $requirement): ?>
        <tr data-material="<?= e($requirement['commodity_id']) ?>" data-commodity-search="<?= e($requirement['commodity_name']) ?>" class="<?= !(bool)$requirement['active'] ? 'retired-row' : '' ?> <?= $user!==null?'stock-row':'' ?>" <?php if($user!==null): ?>data-quick-row tabindex="0" aria-haspopup="dialog" aria-label="Gestionar stock de <?= e($requirement['commodity_name']) ?>" data-commodity="<?= e($requirement['commodity_id']) ?>" data-name="<?= e($requirement['commodity_name']) ?>" data-remaining="<?= e($requirement['remaining_quantity']) ?>" data-editable="<?= $editable?'1':'0' ?>" data-open="<?= $open && (bool)$requirement['active']?'1':'0' ?>" data-record="<?= record_attribute($requirement+['system_id'=>$system['id']]) ?>"<?php endif ?>>
            <th scope="row"><?= e($requirement['commodity_name']) ?><?php if($user!==null): ?><a class="button-quiet market-provider-link" href="<?= e($app->url('/?'.http_build_query(['page'=>'markets','system'=>$system['name'],'commodity_id'=>$requirement['commodity_id']]))) ?>">Dónde conseguir</a><button type="button" class="button-quiet globe-button" data-logistics="sources" data-record="<?= record_attribute(['commodity_id'=>$requirement['commodity_id'],'name'=>$requirement['commodity_name']]) ?>" aria-label="<?= $requirement['source_count']?'Consultar':'Registrar' ?> abastecimiento de <?= e($requirement['commodity_name']) ?>"><?= $requirement['source_count']?'🌐':'＋' ?></button><?php endif ?><?php if (!(bool)$requirement['active']): ?><span class="retired-label">Retirado</span><?php endif ?></th>
            <td class="numeric" data-balance="required_quantity"><?= units($requirement['required_quantity']) ?></td><td class="numeric" data-balance="delivered_quantity"><?= units($requirement['delivered_quantity']) ?></td><td data-balance="remaining_quantity" class="numeric <?= $requirement['remaining_quantity'] === '0' ? 'complete' : '' ?>"><?= units($requirement['remaining_quantity']) ?></td><td class="numeric" data-balance="surplus_quantity"><?= units($requirement['surplus_quantity']) ?></td>
        </tr>
        <?php endforeach; if ($requirements === []): ?><tr><td colspan="5" class="muted">No hay materiales requeridos.</td></tr><?php endif ?>
        </tbody></table>
    </div>
    <?php if ($installation['negative_balances'] > 0): ?><p class="form-error">Hay saldos negativos que requieren revisión.</p><?php endif ?>
</section>
<?php endforeach ?>
<?php $paginationQuery = ['page' => 'system', 'id' => $system['id'], 'include_archived' => $includeArchived ? '1' : '0']; require __DIR__ . '/pagination.php'; ?>

<?php if($user!==null): require __DIR__.'/quick-load-panel.php'; endif ?>
