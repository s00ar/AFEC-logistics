<section class="login-intro">
    <p class="eyebrow"><span class="status-dot" aria-hidden="true"></span> OPERACIONES DE COLONIZACIÓN</p>
    <h1>Cada entrega <br>nos acerca <br><span>a un nuevo hogar.</span></h1>
    <p class="intro-copy">Sistemas, instalaciones y aportes de nuestros comandantes en un mismo lugar.</p>
    <img class="login-logo" src="<?= e($logoUrl) ?>" alt="Emblema de AFEC Logística" width="280" height="280">
</section>
<section class="login-panel" aria-labelledby="login-title">
    <p class="eyebrow">ACCESO DE COMANDANTES</p>
    <h2 id="login-title">Bienvenido a bordo</h2>
    <p class="muted">Ingresá con tu cuenta para gestionar las operaciones.</p>
    <form id="login-form">
        <label for="username">Usuario</label>
        <input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="120" pattern="[a-zA-Z0-9_.@+\-]+" required autofocus>
        <label for="password">Contraseña</label>
        <div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required><button id="toggle-password" type="button" aria-controls="password" aria-pressed="false">Mostrar</button></div>
        <label class="remember-session" for="remember-me"><input id="remember-me" name="remember_me" type="checkbox" checked aria-describedby="remember-help"> Mantener la sesión iniciada</label>
        <p id="remember-help" class="remember-help">Conserva el acceso al cerrar el navegador. Desmarcá esta opción en equipos compartidos.</p>
        <p id="login-error" class="form-error" role="alert" hidden></p>
        <button class="button-primary" id="login-submit" type="submit">Iniciar sesión <span aria-hidden="true">→</span></button>
    </form>
    <p class="login-help">Si necesitás una cuenta, contactá con un administrador de AFEC.</p>
</section>
