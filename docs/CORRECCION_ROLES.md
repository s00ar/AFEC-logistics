# Cambio de rol conservando la cuenta

14/10/2026. El propietario informó un intento de promoción de Comandante a Operador que devolvía un conflicto de usuario. La captura mostraba `admin` en el campo de acceso mientras se editaba Helirius; posteriormente confirmó que el cambio ya funcionaba al conservar el nombre correcto. El backend ya admitía promociones y degradaciones.

## Alcance y archivos

Protección de identidad en `public/assets/js/accounts.js` y `planning.js`, botón **Cambiar rol** en `templates/users.php`, plantilla dedicada y opción explícita de renombrar en `templates/logistics-dialogs.php`. La edición de permisos usa el mismo PATCH autorizado/versionado de cuentas. El nuevo módulo está permitido expresamente en la regla de recursos de `.htaccess`. `App::assetUrl` añade la fecha de modificación como versión de los recursos, para que los navegadores reciban el JavaScript actualizado tras desplegar. La guía incorpora los pasos y una matriz de permisos.

No requiere SQL ni modifica los servicios de autorización, los roles existentes o las cuentas reales. El formulario de rol conserva usuario, nombre visible, estado e identidad del registro original. El editor general conserva el usuario salvo que se marque **Cambiar el nombre de usuario de acceso**; un valor autocompletado no se envía como cambio de identidad. El campo de contraseña inicial se elimina del formulario de edición y se mantiene en las altas.

El cambio de rol continúa registrado en auditoría y revoca las sesiones de la cuenta. Solo un administrador puede hacerlo. Se conservan los controles de último administrador, cambios de rol de la propia cuenta, CSRF, versiones concurrentes e idempotencia.

## Pruebas y límites

```text
node tests/account_payload.mjs
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

Cinco verificaciones del payload real del módulo: autofill `admin` ignorado al editar otra cuenta, promoción sin controles de usuario/nombre/estado, renombrado explícito, cuenta inactiva preservada y registro original sin mutaciones. Node se usa únicamente en esta prueba de desarrollo; la aplicación en Hostinger sigue siendo PHP y JavaScript vanilla sin build ni runtime Node.

12 SQL y 338 HTTP con Apache y `.htaccess` reales. Alta, promoción/degradación, sesión revocada, permisos aplicados al volver a ingresar, identidad de comandante preservada y conflicto de nombre sin cambios parciales. El recurso nuevo es accesible; las rutas privadas siguen denegadas. PHP/JavaScript pasan comprobación de sintaxis. Las pruebas usan exclusivamente la base local de fixtures protegida; no se cambió el rol de ninguna cuenta real como prueba ni se confirmó el comportamiento visual de una extensión de contraseñas específica.

## Riesgo y rollback

El formulario nuevo depende del módulo `accounts.js`; debe publicarse con su permiso en `.htaccess` y el JavaScript que lo importa. Las operaciones guardadas mantienen su acción, payload y clave al reintentar; se agrega el alias de rol al mismo scope de edición existente.

Para rollback, revertir este cambio completo mediante un commit nuevo, conservando base, configuración y cuentas. No requiere revertir esquema ni restaurar movimientos. Las promociones realizadas seguirán siendo válidas con la versión anterior. No volver a publicar recursos parcialmente ni desactivar las restricciones HTTP para reparar un módulo inaccesible.
