<div class="page-heading"><div><p class="eyebrow">CATÁLOGO DE LA FLOTA</p><h1>Mercancías</h1><p class="muted">Nombres, códigos y equivalencias del mismo material.</p></div><button type="button" class="button-primary" data-action="commodity-create">+ Crear mercancía</button></div>
<?php if (!$aliasesReady): ?><p class="pending-banner">Importá la migración 007 para guardar alias y conservar los nombres/códigos anteriores al editar. Las altas y los cambios de estado siguen disponibles.</p><?php endif ?>
<p class="data-note">Los cambios conservan el ID, los objetivos, las entregas y los lugares de abastecimiento. Los alias identifican la misma mercancía; no suman cantidades de otras mercancías.</p>
<div class="page-actions"><a class="button-quiet" href="<?= e($app->url('/?page=commodities' . ($inactive ? '' : '&include_inactive=1'))) ?>"><?= $inactive ? 'Mostrar solo activas' : 'Mostrar también desactivadas' ?></a></div>
<section class="installation-panel"><div class="table-scroll" tabindex="0" role="region" aria-label="Catálogo de mercancías"><table><thead><tr><th scope="col">Mercancía</th><th scope="col">Código</th><th scope="col">Nombre en inglés</th><th scope="col">Alias</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead><tbody>
<?php foreach ($commodities as $commodity): ?>
<tr data-commodity-search="<?= e(implode(' ', [$commodity['name_es'], $commodity['code'], $commodity['name_en'] ?? '', ...$commodity['aliases']])) ?>"><th scope="row"><?= e($commodity['name_es']) ?></th><td><?= e($commodity['code']) ?></td><td><?= e($commodity['name_en'] ?? '—') ?></td><td><?= e(implode(' · ', $commodity['aliases']) ?: '—') ?></td><td><span class="badge"><?= $commodity['active'] ? 'Activa' : 'Desactivada' ?></span></td><td><div class="row-actions">
<button type="button" class="button-quiet" data-action="commodity-edit" data-record="<?= record_attribute($commodity + ['aliases_ready'=>$aliasesReady]) ?>">Editar</button>
<button type="button" class="button-quiet" data-action="<?= $commodity['active'] ? 'commodity-deactivate' : 'commodity-restore' ?>" data-record="<?= record_attribute($commodity) ?>"><?= $commodity['active'] ? 'Desactivar' : 'Reactivar' ?></button>
<button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type'=>'commodity','id'=>$commodity['id']]) ?>">Ver cambios</button>
</div></td></tr>
<?php endforeach ?>
</tbody></table></div></section>
<?php if (!$commodities): ?><p class="muted">No hay mercancías en esta página.</p><?php endif ?>
<?php $paginationQuery=['page'=>'commodities','include_inactive'=>$inactive?'1':'0']; require __DIR__.'/pagination.php'; ?>
