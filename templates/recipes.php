<div class="page-heading"><div><p class="eyebrow">PLANIFICACIÓN DE CONSTRUCCIÓN</p><h1>Facilities y recetas</h1><p class="muted">Plantillas editables para bases orbitales, instalaciones y settlements.</p></div></div>
<div class="toolbar"><button type="button" class="button-primary" data-action="recipe-create">+ Crear receta</button><a class="button-quiet" href="<?= e($app->url('/?page=recipes&include_archived=' . ($includeArchived ? '0' : '1'))) ?>"><?= $includeArchived ? 'Mostrar activas' : 'Mostrar eliminadas' ?></a></div>
<p class="data-note">Al crear una instalación, elegí una receta para cargar sus materiales automáticamente. Editar una receta actualiza las construcciones futuras; los objetivos existentes se ajustan desde su instalación. Registrá la fuente y revisá las cantidades del proyecto en el juego.</p>
<?php if ($recipes === []): ?><section class="empty-state"><h2>No hay recetas en esta página</h2><p>Creá una plantilla con el tipo, tier, materiales y cantidades verificadas. No se cargaron cantidades del juego sin una fuente confirmada.</p></section><?php else: ?>
<div class="system-grid">
<?php foreach ($recipes as $recipe): ?>
<article class="system-card"><div class="card-top"><span class="eyebrow">RECETA · V<?= e($recipe['version']) ?></span><?php if ($recipe['archived_at']): ?><span class="badge">Eliminada</span><?php endif ?></div><h2><?= e($recipe['name']) ?></h2><p class="muted"><?= e($recipe['type']) ?><?= $recipe['tier'] ? ' · Tier ' . e($recipe['tier']) : '' ?> · <?= e($recipe['material_count']) ?> materiales</p><p class="recipe-help muted"><?= e($recipe['source_note']) ?></p><?php if ($recipe['source_url']): ?><a class="back-link" href="<?= e($recipe['source_url']) ?>" target="_blank" rel="noopener noreferrer">Consultar fuente ↗</a><?php endif ?>
<div class="card-actions">
<?php if (!$recipe['archived_at']): ?><button type="button" class="button-quiet" data-action="recipe-edit" data-record="<?= record_attribute($recipe) ?>">Editar receta y materiales</button><?php endif ?>
<button type="button" class="button-quiet <?= !$recipe['archived_at'] ? 'button-danger' : '' ?>" data-action="<?= $recipe['archived_at'] ? 'recipe-restore' : 'recipe-archive' ?>" data-record="<?= record_attribute($recipe) ?>"><?= $recipe['archived_at'] ? 'Restaurar receta' : 'Eliminar receta' ?></button>
<button type="button" class="button-quiet" data-action="history" data-record="<?= record_attribute(['type' => 'facility_recipe', 'id' => $recipe['id']]) ?>">Ver cambios</button>
</div></article>
<?php endforeach ?>
</div><?php endif ?>
<?php $paginationQuery = ['page' => 'recipes', 'include_archived' => $includeArchived ? '1' : '0']; require __DIR__ . '/pagination.php'; ?>
