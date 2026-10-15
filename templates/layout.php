<?php $logoUrl = $app->assetUrl('images/' . rawurlencode('Emblema AFEC Logística Espacial.png')); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <meta name="api-url" content="<?= e($app->url('/api/index.php')) ?>">
    <meta name="home-url" content="<?= e($app->url('/')) ?>">
    <meta name="user-id" content="<?= e($user['id'] ?? '') ?>">
    <title><?= e($title) ?> · AFEC Logística</title>
    <link rel="stylesheet" href="<?= e($app->assetUrl('css/app.css')) ?>">
    <link rel="icon" type="image/png" href="<?= e($logoUrl) ?>">
    <script src="<?= e($app->assetUrl('js/app.js')) ?>" type="module"></script>
    <?php if ($user !== null): ?><script src="<?= e($app->assetUrl('js/planning.js')) ?>" type="module"></script><script src="<?= e($app->assetUrl('js/logistics.js')) ?>" type="module"></script><?php endif ?>
    <?php if ($user !== null && $page==='system'): ?><script src="<?= e($app->assetUrl('js/quick-load.js')) ?>" type="module"></script><?php endif ?>
</head>
<body>
<a class="skip-link" href="#main">Saltar al contenido</a>
<header class="site-header">
    <a class="brand" href="<?= e($app->url('/')) ?>" aria-label="AFEC Logística, inicio"><img class="brand-logo" src="<?= e($logoUrl) ?>" alt="" width="120" height="120"><span>AFEC <strong>LOGÍSTICA</strong></span></a>
    <nav aria-label="Navegación principal">
        <a href="<?= e($app->url('/')) ?>" <?= $page === 'dashboard' ? 'aria-current="page"' : '' ?>>Sistemas</a>
        <?php if ($canPlan): ?><a href="<?= e($app->url('/?page=recipes')) ?>" <?= $page === 'recipes' ? 'aria-current="page"' : '' ?>>Facilities y recetas</a><?php endif ?>
        <?php if ($user !== null): ?><a href="<?= e($app->url('/?page=wishlist&scope=personal')) ?>" <?= $page==='wishlist' && $scope==='personal'?'aria-current="page"':'' ?>>Mi lista</a><a href="<?= e($app->url('/?page=wishlist&scope=fleet')) ?>" <?= $page==='wishlist' && $scope==='fleet'?'aria-current="page"':'' ?>>Flota</a><?php endif ?>
        <?php if (($user['role'] ?? null) === 'admin'): ?><a href="<?= e($app->url('/?page=commodities')) ?>" <?= $page === 'commodities' ? 'aria-current="page"' : '' ?>>Mercancías</a><a href="<?= e($app->url('/?page=users')) ?>" <?= $page === 'users' ? 'aria-current="page"' : '' ?>>Usuarios</a><?php endif ?>
    </nav>
    <?php if ($user !== null): ?>
    <div class="account"><span><?= e($user['display_name']) ?></span><button class="button-quiet" id="logout" type="button">Salir</button></div>
    <?php else: ?>
    <div class="account"><a class="button-quiet" href="<?= e($app->url('/?page=login')) ?>">Iniciar sesión</a></div>
    <?php endif ?>
</header>
<main id="main" class="<?= $view === 'login' ? 'login-main' : 'content' ?>">
    <?php if ($user !== null): ?><div id="pending-operation" class="pending-banner" role="status" hidden><p>Hay una operación cuyo resultado falta confirmar.</p><button type="button" class="button-quiet" id="resume-operation">Comprobar y reintentar</button></div><?php endif ?>
    <?php require __DIR__ . '/' . $view . '.php'; ?>
</main>
<?php if ($user !== null): ?><?php require __DIR__ . '/planning-dialog.php'; require __DIR__ . '/logistics-dialogs.php'; ?><?php endif ?>
<footer class="site-footer"><span>AFEC · Colonización coordinada</span><span>Elite Dangerous</span></footer>
<div id="global-message" class="toast" role="status" hidden></div>
</body>
</html>
