<a class="back-link" href="<?= e($app->url('/')) ?>">← Todos los sistemas</a>
<div class="page-heading">
    <div><p class="eyebrow">SISTEMA DE COLONIZACIÓN</p><h1><?= e($system['name']) ?></h1><?php if ($system['description']): ?><p class="muted"><?= e($system['description']) ?></p><?php endif ?></div>
    <?php if ($system['archived_at']): ?><span class="badge">Archivado</span><?php endif ?>
</div>
<?php if ($canPlan): ?>
<?php $systemRecord = array_intersect_key($system, array_flip(['id', 'name', 'description', 'archived_at', 'version'])); ?>
<div class="toolbar">
    <button type="button" class="button-primary" data-action="installation-create" data-record="<?= record_attribute(['system_id' => $system['id']]) ?>" <?= $system['archived_at'] ? 'disabled' : '' ?>>+ Agregar instalación</button>
    <button type="button" class="button-quiet" data-action="system-edit" data-record="<?= record_attribute($systemRecord) ?>">Editar sistema</button>
    <button type="button" class="button-quiet" data-action="<?= $system['archived_at'] ? 'system-restore' : 'system-archive' ?>" data-record="<?= record_attribute($systemRecord) ?>"><?= $system['archived_at'] ? 'Restaurar sistema' : 'Archivar sistema' ?></button>
    <button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'system', 'id' => $system['id']]) ?>">Ver cambios del sistema</button>
    <a class="button-quiet" href="<?= e($app->url('/?' . http_build_query(['page' => 'system', 'id' => $system['id'], 'include_archived' => $includeArchived ? '0' : '1']))) ?>"><?= $includeArchived ? 'Mostrar activos' : 'Mostrar archivadas y retirados' ?></a>
</div>
<?php endif ?>
<?php if ($system['installations'] === []): ?><section class="empty-state"><h2>No hay instalaciones en esta página</h2><p>Las instalaciones y sus materiales aparecerán aquí.</p></section><?php endif ?>
<?php foreach ($system['installations'] as $installation): ?>
<?php
    $requirements = array_values(array_filter($system['requirements'], static fn(array $row): bool => $row['installation_id'] === $installation['id']));
    $editable = !$system['archived_at'] && !$installation['archived_at'];
    $open = $editable && $installation['construction_status'] !== 'completed';
?>
<section class="installation-panel">
    <div class="installation-heading">
        <div><h2><?= e($installation['name']) ?></h2><p class="muted"><?= e($installation['body_name'] ?: $installation['location_label'] ?: 'Ubicación sin especificar') ?></p></div>
        <div class="status-badges"><span class="badge <?= $installation['construction_status']==='completed'?'complete':'' ?>"><?= $installation['construction_status']==='completed'?'Terminada':($installation['construction_status']==='under_construction'?'En construcción':'Sin clasificar') ?></span><span class="badge"><?= $installation['archived_at'] ? 'Archivada' : ($installation['percent'] === null ? 'Sin objetivos' : e(number_format($installation['percent'], 1, ',', '.')) . '%') ?></span></div>
    </div>
    <?php if ($canPlan): ?>
    <div class="installation-actions">
        <button type="button" class="button-quiet" data-action="installation-edit" data-record="<?= record_attribute($installation) ?>" <?= $system['archived_at'] ? 'disabled' : '' ?>>Editar instalación</button>
        <button type="button" class="button-quiet" data-action="<?= $installation['archived_at'] ? 'installation-restore' : 'installation-archive' ?>" data-record="<?= record_attribute($installation) ?>" <?= $system['archived_at'] && $installation['archived_at'] ? 'disabled' : '' ?>><?= $installation['archived_at'] ? 'Restaurar instalación' : 'Archivar instalación' ?></button>
        <button type="button" class="button-quiet" data-action="requirement-create" data-record="<?= record_attribute(['installation_id' => $installation['id'], 'system_id' => $system['id'], 'used_commodity_ids' => array_column($requirements, 'commodity_id')]) ?>" <?= !$editable ? 'disabled' : '' ?>>+ Agregar objetivo</button>
        <button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'installation', 'id' => $installation['id']]) ?>">Ver cambios</button>
        <button type="button" class="button-quiet" data-action="installation-status" data-record="<?= record_attribute($installation+['next_status'=>$installation['construction_status']==='completed'?'under_construction':'completed']) ?>" <?= !$editable?'disabled':'' ?>><?= $installation['construction_status']==='completed'?'Reabrir construcción':'Marcar terminada' ?></button>
        <?php if($installation['construction_status']===null): ?><button type="button" class="button-quiet" data-action="installation-status" data-record="<?= record_attribute($installation+['next_status'=>'under_construction']) ?>" <?= !$editable?'disabled':'' ?>>Marcar en construcción</button><?php endif ?>
    </div>
    <?php endif ?>
    <?php if($user!==null): ?><div class="installation-actions"><button type="button" class="button-quiet" data-action="list-add" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'scope'=>'personal','name'=>$installation['name']]) ?>" <?= !$open?'disabled':'' ?>>Añadir a mi lista</button><button type="button" class="button-quiet" data-action="list-add" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'scope'=>'fleet','name'=>$installation['name']]) ?>" <?= !$open?'disabled':'' ?>>Añadir a la flota</button><button type="button" class="button-quiet" data-logistics="movements" data-record="<?= record_attribute(['installation_id'=>$installation['id'],'name'=>$installation['name']]) ?>">Historial de entregas</button></div><?php endif ?>
    <?php if($installation['construction_status']==='completed' && $installation['remaining_quantity']!=='0'): ?><p class="data-note">Terminada manualmente con <?= units($installation['remaining_quantity']) ?> unidades pendientes en el registro. Queda excluida de las listas de deseos.</p><?php endif ?>
    <p class="table-hint">Deslizá la tabla para ver todas las columnas.</p>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Materiales de <?= e($installation['name']) ?>">
        <table><caption class="sr-only">Requerimientos y entregas de <?= e($installation['name']) ?></caption><thead><tr><th scope="col">Material</th><th scope="col" class="numeric">Requerido</th><th scope="col" class="numeric">Entregado</th><th scope="col" class="numeric">Pendiente</th><th scope="col" class="numeric">Sobrante</th><?php if ($canPlan): ?><th scope="col">Objetivo</th><?php endif ?><?php if($user!==null): ?><th scope="col">Entrega</th><?php endif ?></tr></thead><tbody>
        <?php foreach ($requirements as $requirement): ?>
        <tr class="<?= !(bool)$requirement['active'] ? 'retired-row' : '' ?>">
            <th scope="row"><?= e($requirement['commodity_name']) ?><?php if($user!==null): ?><button type="button" class="button-quiet globe-button" data-logistics="sources" data-record="<?= record_attribute(['commodity_id'=>$requirement['commodity_id'],'name'=>$requirement['commodity_name']]) ?>" aria-label="<?= $requirement['source_count']?'Consultar':'Registrar' ?> abastecimiento de <?= e($requirement['commodity_name']) ?>"><?= $requirement['source_count']?'🌐':'＋' ?></button><?php endif ?><?php if (!(bool)$requirement['active']): ?><span class="retired-label">Retirado</span><?php endif ?></th>
            <td class="numeric"><?= units($requirement['required_quantity']) ?></td><td class="numeric"><?= units($requirement['delivered_quantity']) ?></td><td class="numeric <?= $requirement['remaining_quantity'] === '0' ? 'complete' : '' ?>"><?= units($requirement['remaining_quantity']) ?></td><td class="numeric"><?= units($requirement['surplus_quantity']) ?></td>
            <?php if ($canPlan): ?>
            <td><div class="row-actions">
                <button type="button" class="button-quiet" data-action="requirement-edit" data-record="<?= record_attribute($requirement + ['system_id' => $system['id']]) ?>" <?= !$editable || !(bool)$requirement['commodity_active'] ? 'disabled' : '' ?>><?= (bool)$requirement['active'] ? 'Editar objetivo' : 'Restaurar objetivo' ?></button>
                <?php if ((bool)$requirement['active']): ?><button type="button" class="button-quiet" data-action="requirement-archive" data-record="<?= record_attribute($requirement + ['system_id' => $system['id']]) ?>" <?= !$editable ? 'disabled' : '' ?>>Retirar objetivo</button><?php endif ?>
                <button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'requirement', 'id' => $installation['id'], 'commodity_id' => $requirement['commodity_id']]) ?>">Ver cambios</button>
            </div></td>
            <?php endif ?>
            <?php if($user!==null): ?><td><button type="button" class="button-quiet" data-action="delivery-create" data-record="<?= record_attribute($requirement+['system_id'=>$system['id']]) ?>" <?= !$open || !(bool)$requirement['active']?'disabled':'' ?>>Registrar entrega</button></td><?php endif ?>
        </tr>
        <?php endforeach; if ($requirements === []): ?><tr><td colspan="<?= 5+($canPlan?1:0)+($user!==null?1:0) ?>" class="muted">No hay materiales requeridos.</td></tr><?php endif ?>
        </tbody></table>
    </div>
    <?php if ($installation['negative_balances'] > 0): ?><p class="form-error">Hay saldos negativos que requieren revisión.</p><?php endif ?>
</section>
<?php endforeach ?>
<?php $paginationQuery = ['page' => 'system', 'id' => $system['id'], 'include_archived' => $includeArchived ? '1' : '0']; require __DIR__ . '/pagination.php'; ?>
