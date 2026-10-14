<div class="page-heading"><div><p class="eyebrow">CENTRO DE OPERACIONES</p><h1>Sistemas de colonización</h1><p class="muted">El avance de nuestras instalaciones, material a material.</p></div><span class="badge"><?= count($systems) ?> sistemas activos</span></div>
<?php if ($systems === []): ?>
<section class="empty-state"><span class="empty-orbit" aria-hidden="true">◎</span><h2>No hay sistemas registrados</h2><p>Los sistemas activos aparecerán aquí con sus instalaciones y progreso.</p></section>
<?php else: ?>
<div class="system-grid">
<?php foreach ($systems as $item): ?>
    <article class="system-card">
        <div class="card-top"><span class="eyebrow">SISTEMA</span><span class="card-coordinate" aria-hidden="true">↗</span></div>
        <h2><a href="<?= e($app->url('/?page=system&id=' . rawurlencode($item['id']))) ?>"><?= e($item['name']) ?></a></h2>
        <p class="muted"><?= e($item['installation_count']) ?> <?= (int)$item['installation_count'] === 1 ? 'instalación' : 'instalaciones' ?></p>
        <div class="progress-label"><span>Progreso por unidades</span><strong><?= $item['percent'] === null ? 'Sin objetivos' : e(number_format($item['percent'], 1, ',', '.')) . '%' ?></strong></div>
        <progress max="100" value="<?= e($item['percent'] ?? 0) ?>" aria-label="Progreso de <?= e($item['name']) ?>"></progress>
        <dl class="card-stats"><div><dt>Pendiente</dt><dd><?= units($item['remaining_quantity']) ?></dd></div><div><dt>Entregado</dt><dd><?= units($item['delivered_quantity']) ?></dd></div><div><dt>Sobrante</dt><dd><?= units($item['surplus_quantity']) ?></dd></div></dl>
        <?php if ($item['negative_balances'] > 0): ?><p class="form-error">Hay saldos negativos que requieren revisión.</p><?php endif ?>
    </article>
<?php endforeach ?>
</div>
<p class="data-note">El progreso incluye instalaciones y objetivos activos. Los sobrantes de un material no compensan faltantes de otro.</p>
<?php endif ?>
