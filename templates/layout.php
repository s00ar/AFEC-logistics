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
    <link rel="stylesheet" href="<?= e($app->url('/assets/css/app.css')) ?>">
    <script src="<?= e($app->url('/assets/js/app.js')) ?>" type="module"></script>
    <?php if ($canPlan): ?><script src="<?= e($app->url('/assets/js/planning.js')) ?>" type="module"></script><?php endif ?>
</head>
<body>
<a class="skip-link" href="#main">Saltar al contenido</a>
<header class="site-header">
    <a class="brand" href="<?= e($app->url('/')) ?>" aria-label="AFEC Logística, inicio"><span class="brand-mark" aria-hidden="true">A</span><span>AFEC <strong>LOGÍSTICA</strong></span></a>
    <nav aria-label="Navegación principal">
        <a href="<?= e($app->url('/')) ?>" <?= $page === 'dashboard' ? 'aria-current="page"' : '' ?>>Sistemas</a>
        <?php if (($user['role'] ?? null) === 'admin'): ?><a href="<?= e($app->url('/?page=users')) ?>" <?= $page === 'users' ? 'aria-current="page"' : '' ?>>Usuarios</a><?php endif ?>
    </nav>
    <?php if ($user !== null): ?>
    <div class="account"><span><?= e($user['display_name']) ?></span><button class="button-quiet" id="logout" type="button">Salir</button></div>
    <?php else: ?>
    <div class="account"><a class="button-quiet" href="<?= e($app->url('/?page=login')) ?>">Iniciar sesión</a></div>
    <?php endif ?>
</header>
<main id="main" class="<?= $view === 'login' ? 'login-main' : 'content' ?>">
    <?php if ($canPlan): ?><div id="pending-operation" class="pending-banner" role="status" hidden><p>Hay una operación cuyo resultado falta confirmar.</p><button type="button" class="button-quiet" id="resume-operation">Comprobar y reintentar</button></div><?php endif ?>
    <?php require __DIR__ . '/' . $view . '.php'; ?>
</main>
<?php if ($canPlan): ?><?php require __DIR__ . '/planning-dialog.php'; ?><?php endif ?>
<footer class="site-footer"><span>AFEC · Colonización coordinada</span><span>Elite Dangerous</span></footer>
<div id="global-message" class="toast" role="status" hidden></div>
</body>
</html>
