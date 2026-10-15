# Sesión conservada al cerrar el navegador

15/10/2026. Alcance: permitir conservar el acceso entre aperturas del navegador mediante la opción **Mantener la sesión iniciada**, marcada por defecto. Preparación y pruebas locales; no autoriza publicar ni modificar el sitio real. No requiere SQL ni migraciones.

## Comportamiento y archivos

- `templates/login.php`, `public/assets/js/app.js` y `public/assets/css/app.css`: opción accesible que envía un booleano en `remember_me`. Desmarcarla conserva el comportamiento corto. Clientes API que omiten la opción también conservan ese comportamiento.
- `src/Services/AuthService.php`: valida el booleano y solo recuerda un acceso con credenciales correctas.
- `src/Security/Session.php`: cookie persistente con vencimiento absoluto de 30 días desde el login. Se conserva el estado privado en disco durante ese plazo. Las visitas no extienden el vencimiento. La sesión recordada utiliza ese plazo en lugar de los límites cortos de inactividad y duración; los accesos normales y anteriores conservan sus límites.
- `config/config.example.php` y `src/Config.php`: valor predeterminado `session.remember_seconds=2592000`, ajustable en la configuración privada. No modifica configuraciones privadas existentes.
- `tests/session_http.py` y `tests/session-fixture.php`: regresiones HTTP con cuentas sintéticas y envejecimiento determinista de las sesiones. El helper requiere CLI, entorno test y base `afec_test_*` local. `tests/run.py` integra estas comprobaciones; las pruebas de cuentas en `tests/expansion_http.py` ejercitan revocaciones de sesiones recordadas.

La cookie contiene únicamente el identificador aleatorio de sesión; no se guardan contraseñas en el navegador ni en almacenamiento JavaScript. Se mantienen HttpOnly, SameSite=Lax y Secure obligatorio en producción, regeneración del identificador y del CSRF al ingresar/salir, modo estricto y revalidación de estado, rol y `auth_version` en cada acceso autenticado. Al salir o revocarse la cuenta se invalida el identificador anterior y se sustituye la cookie persistente por una sesión anónima de navegador. La configuración de cookies sigue el [manual oficial de PHP](https://www.php.net/manual/en/function.session-set-cookie-params.php).

## Riesgo y rollback

Recordar el acceso permite a quien use ese perfil del navegador entrar durante el plazo elegido. La opción se puede desmarcar en equipos compartidos y el botón **Salir** cancela el acceso guardado. La eliminación de cookies o de los archivos privados de sesión también requiere un nuevo login; no se promete persistencia frente a limpiezas externas del hosting o navegación privada.

Rollback: revertir únicamente este cambio de código. La versión anterior vuelve a aplicar los límites cortos en la siguiente petición; no hay cambios de base que revertir. Conservar movimientos, cuentas, configuración privada y el resto de cambios anteriores.

## Prueba reproducible

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
node --check public/assets/js/app.js
```

La suite guarda exclusivamente cookies sintéticas en un archivo privado y carga solo las que sobrevivirían al cierre del navegador; usa un cliente nuevo para verificar que conserva la cuenta. También comprueba cookie de sesión corta, vencimiento del lado servidor, ausencia de prórroga, entradas inválidas, credenciales incorrectas, CSRF, logout y rechazo de cookies reutilizadas. No adelanta el reloj del sistema.

Resultado local: **484 comprobaciones HTTP y 12 SQL aprobadas** con PHP 8.4.26, MariaDB local, Apache/FastCGI y reglas `.htaccess` reales. También pasaron simulación/repetición del migrador, sintaxis de PHP y JavaScript, y `git diff --check`. Navegador: formulario visible, opción marcada por defecto y controles para marcar/desmarcar comprobados. La reapertura se simula con cookies persistidas y un cliente HTTP nuevo; no se cerró el navegador del usuario ni se esperaron 30 días reales.

La primera ejecución se interrumpió por una transferencia del logo; después el entorno bloqueó tres archivos CLI existentes. El propietario revisó el antivirus y se restauraron los tres archivos exactamente desde Git, sin cambios de contenido. La ejecución completa posterior pasó; no se modificó el antivirus desde el agente.
