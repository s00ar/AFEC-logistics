<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Solicitudes de ayuda para ingresar</h1><p class="muted">Los avisos permanecen pendientes hasta restablecer la contraseña o marcarlos como atendidos.</p></div></div>
<p class="data-note">La solicitud no demuestra la identidad del usuario. Confirmala por el canal habitual de AFEC antes de restablecer su contraseña y comunicársela. También podés gestionar las cuentas desde <a href="<?= e($app->url('/?page=users')) ?>">Usuarios y roles</a>.</p>
<?php if (!$passwordRequests['items']): ?><section class="account-panel"><h2>Sin solicitudes pendientes</h2><p class="muted">Los nuevos avisos aparecerán al ingresar como administrador.</p></section><?php endif ?>
<div class="password-request-list">
<?php foreach ($passwordRequests['items'] as $request): $requestedAt=(new DateTimeImmutable($request['requested_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($app->config['app']['display_timezone'])); ?>
<article class="account-panel password-request-card">
    <h2><?= e($request['display_name']) ?></h2><p><strong>Usuario:</strong> <?= e($request['username']) ?></p><p class="muted">Solicitud del <time datetime="<?= e($requestedAt->format(DATE_ATOM)) ?>"><?= e($requestedAt->format('d/m/Y H:i')) ?></time> (<?= e($app->config['app']['display_timezone']) ?>)</p>
    <button class="button-quiet" type="button" data-action="user-password" data-record="<?= record_attribute($request) ?>">Restablecer contraseña</button>
    <form class="close-password-help-form" data-user-id="<?= e($request['id']) ?>" data-request-id="<?= e($request['request_id']) ?>">
        <label>Motivo de cierre<input name="reason" maxlength="1000" required placeholder="Por ejemplo: acceso recuperado por el usuario"></label>
        <p class="form-error" data-form-error role="alert" hidden></p><p class="form-success" data-form-success role="status" hidden></p>
        <button class="button-quiet" type="submit">Marcar como atendida</button>
    </form>
</article>
<?php endforeach ?>
</div>
<?php $paginationQuery=['page'=>'password-requests']; require __DIR__.'/pagination.php'; ?>
