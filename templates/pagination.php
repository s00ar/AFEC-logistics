<?php if ($pagination['page'] > 1 || $pagination['has_more']): ?>
<nav class="pagination" aria-label="Páginas de resultados">
    <?php if ($pagination['page'] > 1): ?><a class="button-quiet" href="<?= e($app->url('/?' . http_build_query($paginationQuery + ['list_page' => $pagination['page'] - 1]))) ?>">← Anterior</a><?php endif ?>
    <span>Página <?= e($pagination['page']) ?></span>
    <?php if ($pagination['has_more']): ?><a class="button-quiet" href="<?= e($app->url('/?' . http_build_query($paginationQuery + ['list_page' => $pagination['page'] + 1]))) ?>">Siguiente →</a><?php endif ?>
</nav>
<?php endif ?>
