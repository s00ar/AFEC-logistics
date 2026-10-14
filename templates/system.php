<a class="back-link" href="<?= e($app->url('/')) ?>">← Todos los sistemas</a>
<div class="page-heading"><div><p class="eyebrow">SISTEMA DE COLONIZACIÓN</p><h1><?= e($system['name']) ?></h1><?php if ($system['description']): ?><p class="muted"><?= e($system['description']) ?></p><?php endif ?></div><?php if ($system['archived_at']): ?><span class="badge">Archivado</span><?php endif ?></div>
<?php if ($system['installations'] === []): ?><section class="empty-state"><h2>Sin instalaciones activas</h2><p>Las instalaciones y sus materiales aparecerán aquí.</p></section><?php endif ?>
<?php foreach ($system['installations'] as $installation): ?>
<section class="installation-panel">
    <div class="installation-heading"><div><h2><?= e($installation['name']) ?></h2><p class="muted"><?= e($installation['body_name'] ?: $installation['location_label'] ?: 'Ubicación sin especificar') ?></p></div><span class="badge"><?= $installation['percent'] === null ? 'Sin objetivos' : e(number_format($installation['percent'], 1, ',', '.')) . '%' ?></span></div>
    <p class="table-hint">Deslizá la tabla para ver todas las columnas.</p>
    <div class="table-scroll" tabindex="0" role="region" aria-label="Materiales de <?= e($installation['name']) ?>">
        <table><caption class="sr-only">Requerimientos y entregas de <?= e($installation['name']) ?></caption><thead><tr><th scope="col">Material</th><th scope="col" class="numeric">Requerido</th><th scope="col" class="numeric">Entregado</th><th scope="col" class="numeric">Pendiente</th><th scope="col" class="numeric">Sobrante</th></tr></thead><tbody>
        <?php $found = false; foreach ($system['requirements'] as $requirement): if ($requirement['installation_id'] !== $installation['id']) { continue; } $found = true; ?>
        <tr><th scope="row"><?= e($requirement['commodity_name']) ?></th><td class="numeric"><?= units($requirement['required_quantity']) ?></td><td class="numeric"><?= units($requirement['delivered_quantity']) ?></td><td class="numeric <?= $requirement['remaining_quantity'] === '0' ? 'complete' : '' ?>"><?= units($requirement['remaining_quantity']) ?></td><td class="numeric"><?= units($requirement['surplus_quantity']) ?></td></tr>
        <?php endforeach; if (!$found): ?><tr><td colspan="5" class="muted">No hay materiales requeridos.</td></tr><?php endif ?>
        </tbody></table>
    </div>
    <?php if ($installation['negative_balances'] > 0): ?><p class="form-error">Hay saldos negativos que requieren revisión.</p><?php endif ?>
</section>
<?php endforeach ?>
