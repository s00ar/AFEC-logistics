<div class="page-heading"><div><p class="eyebrow">RECUPERAR EL ACCESO</p><h1>Ayuda con tu contraseña</h1><p class="muted">Un administrador de AFEC puede restablecerla para que vuelvas a ingresar.</p></div></div>
<section class="account-panel" aria-labelledby="help-title">
    <h2 id="help-title">Avisar a un administrador</h2>
    <p class="muted">Indicá tu usuario de acceso. La solicitud aparecerá como una alerta cuando ingrese un administrador. Pedir ayuda no cambia tu contraseña ni cierra tus sesiones.</p>
    <form id="password-help-form">
        <label for="help-username">Usuario de acceso</label><input id="help-username" name="username" type="text" autocomplete="username" minlength="3" maxlength="120" pattern="[a-zA-Z0-9_.@+\-]+" required>
        <p class="form-error" data-form-error role="alert" hidden></p><p class="form-success" data-form-success role="status" hidden></p>
        <button class="button-primary" type="submit">Solicitar ayuda</button>
    </form>
    <p class="muted">Coordiná con el administrador por el canal habitual de AFEC para confirmar tu identidad y recibir la nueva contraseña. Si no recordás tu usuario, contactalo por ese mismo canal.</p>
    <a href="<?= e($app->url('/?page=login')) ?>">Volver al inicio de sesión</a>
</section>
