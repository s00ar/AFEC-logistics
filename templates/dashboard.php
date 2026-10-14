<div class="page-heading">
    <div><p class="eyebrow">CENTRO DE OPERACIONES</p><h1>Sistemas de colonización</h1><p class="muted">El avance de nuestras instalaciones, material a material.</p></div>
    <span class="badge"><?= count($systems) ?> sistemas · pág. <?= e($listPage) ?></span>
</div>
<?php if ($canPlan): ?>
<div class="toolbar">
    <button class="button-primary" type="button" data-action="system-create">+ Crear sistema</button>
    <?php if ($user['role'] === 'admin'): ?><button class="button-quiet" type="button" data-action="commodity-create">Agregar material al catálogo</button><?php endif ?>
    <a class="button-quiet" href="<?= e($app->url('/?include_archived=' . ($includeArchived ? '0' : '1'))) ?>"><?= $includeArchived ? 'Mostrar activos' : 'Mostrar eliminados / archivados' ?></a>
</div>
<?php endif ?>
<?php if ($systems === []): ?>
<section class="empty-state"><span class="empty-orbit" aria-hidden="true">◎</span><h2>No hay sistemas en esta página</h2><p>Los sistemas aparecerán aquí con sus instalaciones y progreso.</p></section>
<?php else: ?>
<div class="system-grid">
<?php foreach ($systems as $item): ?>
    <article class="system-card">
        <div class="card-top"><span class="eyebrow">SISTEMA</span><?php if ($item['archived_at']): ?><span class="badge">Archivado</span><?php else: ?><span class="card-coordinate" aria-hidden="true">↗</span><?php endif ?></div>
        <h2><a href="<?= e($app->url('/?page=system&id=' . rawurlencode($item['id']))) ?>"><?= e($item['name']) ?></a></h2>
        <p class="muted"><?= e($item['installation_count']) ?> <?= (int)$item['installation_count'] === 1 ? 'instalación' : 'instalaciones' ?></p>
        <div class="progress-label"><span>Progreso por unidades</span><strong><?= $item['percent'] === null ? 'Sin objetivos' : e(number_format($item['percent'], 1, ',', '.')) . '%' ?></strong></div>
        <progress max="100" value="<?= e($item['percent'] ?? 0) ?>" aria-label="Progreso de <?= e($item['name']) ?>"></progress>
        <dl class="card-stats"><div><dt>Pendiente</dt><dd><?= units($item['remaining_quantity']) ?></dd></div><div><dt>Entregado</dt><dd><?= units($item['delivered_quantity']) ?></dd></div><div><dt>Sobrante</dt><dd><?= units($item['surplus_quantity']) ?></dd></div></dl>
        <?php if ($item['negative_balances'] > 0): ?><p class="form-error">Hay saldos negativos que requieren revisión.</p><?php endif ?>
        <?php if ($canPlan): ?>
        <div class="card-actions">
            <a class="button-quiet" href="<?= e($app->url('/?page=system&id=' . rawurlencode($item['id']))) ?>">Ver instalaciones</a>
            <?php if (!$item['archived_at']): ?><button type="button" class="button-quiet" data-action="installation-create" data-record="<?= record_attribute(['system_id' => $item['id']]) ?>">+ Agregar facility</button><?php endif ?>
            <button type="button" class="button-quiet" data-action="system-edit" data-record="<?= record_attribute($item) ?>">Editar</button>
            <button type="button" class="button-quiet <?= !$item['archived_at'] ? 'button-danger' : '' ?>" data-action="<?= $item['archived_at'] ? 'system-restore' : 'system-archive' ?>" data-record="<?= record_attribute($item) ?>"><?= $item['archived_at'] ? 'Restaurar' : 'Eliminar' ?></button>
        </div>
        <?php endif ?>
    </article>
<?php endforeach ?>
</div>
<p class="data-note">El progreso incluye instalaciones y objetivos activos. Los sobrantes de un material no compensan faltantes de otro.</p>
<?php endif ?>
<?php $paginationQuery = ['include_archived' => $includeArchived ? '1' : '0']; require __DIR__ . '/pagination.php'; ?>
