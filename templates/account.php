<div class="page-heading"><div><p class="eyebrow">ACCESO PERSONAL</p><h1>Mi cuenta</h1><p class="muted"><?= e($user['display_name']) ?> · <?= e($user['username']) ?></p></div></div>
<section class="account-panel" aria-labelledby="change-password-title">
    <h2 id="change-password-title">Cambiar mi contraseña</h2>
    <p class="muted">Usá una contraseña nueva de entre 12 y 72 bytes. Al cambiarla se cerrarán las otras sesiones de tu cuenta.</p>
    <form id="own-password-form">
        <label for="current-password">Contraseña actual</label><input id="current-password" name="current_password" type="password" autocomplete="current-password" maxlength="1024" required>
        <label for="new-password">Nueva contraseña</label><input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <label for="password-confirmation">Repetir nueva contraseña</label><input id="password-confirmation" name="confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <p class="form-error" data-form-error role="alert" hidden></p><p class="form-success" data-form-success role="status" hidden></p>
        <button class="button-primary" type="submit">Guardar nueva contraseña</button>
    </form>
</section>
