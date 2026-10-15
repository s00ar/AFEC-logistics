# Contraseña propia y solicitudes de ayuda

15/10/2026. El cambio de contraseña propio no estaba disponible: solo existía el restablecimiento administrativo. Se añade **Mi cuenta → Cambiar mi contraseña** para todos los roles y una solicitud pública de ayuda que genera un aviso a los administradores. El propietario descartó la recuperación por correo; la contraseña nueva se comunica por el canal habitual de AFEC, previa comprobación de identidad.

## Alcance, archivos y comportamiento

- `AccountService`, `AccountRepository`, `AccountController` y `AccountThrottle`: cambio propio con contraseña actual, confirmación de la nueva, CSRF, sesión válida, control de concurrencia, límites de intentos e idempotencia. La identidad procede de sesión; no admite seleccionar otro usuario. Los límites de versión se comprueban antes de incrementar.
- El cambio propio actualiza contraseña y versión de acceso en una sola transacción, con auditoría. Revoca las otras sesiones, incluidas las recordadas. La sesión actual cambia de identificador y CSRF y conserva su elección de recordar acceso. La renovación usa la versión de esa operación, sin adoptar una revocación administrativa posterior. Un fallo de auditoría revierte también la contraseña.
- `PasswordHelpRepository`: un registro privado por cuenta existente en `storage/password-help/`, con permisos restringidos, bloqueo por cuenta y reemplazo atómico. Contiene únicamente ID de cuenta, ID de solicitud y fecha UTC. La cuenta se bloquea en SQL antes de crear/reutilizar el aviso para serializarlo con restablecimientos y cierres. No se atribuye el aviso anónimo al usuario como si hubiera iniciado sesión.
- Los avisos repetidos conservan un único registro pendiente, ID y fecha. La respuesta pública es igual para cuentas inexistentes, inactivas, avisos repetidos o limitados. No se cambian contraseña, versiones ni sesiones al pedir ayuda.
- El estado pendiente se obtiene comparando el aviso con la auditoría de cambio de contraseña o cierre administrativo de esa cuenta. Un restablecimiento existente de administrador y un cambio propio lo resuelven automáticamente; un rollback transaccional no lo resuelve. Los cierres manuales tienen motivo, CSRF, validación del ID de solicitud y recibo idempotente. Un ID antiguo no permite cerrar una solicitud nueva.
- `templates/account.php`, `password-help.php`, `password-requests.php`, `layout.php`, `login.php`, `users.php` y `public/index.php`: formulario personal, acceso a ayuda desde el login, aviso persistente en páginas de administrador y bandeja paginada con restablecimiento/cierre. La fecha se presenta en la zona configurada. Los datos de la cola solo son accesibles para administradores.
- `public/assets/js/account-security.js` y CSS: formularios accesibles, bloqueo de doble envío, confirmación de contraseña y mensajes del servidor. Las contraseñas se mantienen solo en el formulario y la solicitud en memoria, sin guardarlas en almacenamiento del navegador. El módulo se permite expresamente en `.htaccess`.

Se reutilizan las tablas existentes de cuentas, operaciones, auditoría e intentos. Los contadores de esta funcionalidad llevan prefijos propios y no consumen el presupuesto del login: hasta 3 solicitudes por usuario y 10 por dirección en 15 minutos; hasta 8 intentos de cambio propio por cuenta y 30 por dirección en 15 minutos. Se usa `REMOTE_ADDR`, sin confiar en cabeceras reenviadas. Los recibos y la auditoría excluyen contraseñas; el verificador idempotente de contraseña es lento y el digest de la solicitud contiene solo la identidad.

## API

| Método | Ruta | Acceso |
| --- | --- | --- |
| POST | `password/help` | Público con CSRF; recibe `username` y devuelve confirmación genérica |
| POST | `account/password` | Sesión propia y CSRF; `current_password`, `new_password`, `confirmation`, clave idempotente |
| GET | `admin/password-help` | Administrador; lista paginada y cantidad total |
| POST | `admin/password-help/close&id=ID` | Administrador, CSRF y clave idempotente; `request_id` y `reason` |

El restablecimiento manual mantiene la ruta y los controles anteriores `admin/user/password`. `operation&scope=account.password&key=...` permite consultar el recibo propio sin exponer las contraseñas.

## Riesgos, despliegue y rollback

No requiere migración SQL, correo, credenciales SMTP, tareas programadas ni microservicios. El almacenamiento privado ya debe ser escribible para las sesiones. Conservar `storage/password-help/` durante los despliegues e incluirlo en las copias privadas junto con la base; borrar sus archivos elimina avisos pendientes. El código rechaza registros corruptos sin sobrescribirlos. Las rutas privadas siguen bloqueadas por HTTP.

Una solicitud pública no demuestra la identidad del titular: un administrador debe comprobarla por el canal habitual antes de restablecer la contraseña. El formulario no crea cuentas ni modifica el correo de nadie. Se mantienen limitación de intentos y agrupación para reducir avisos repetidos.

Rollback: revertir el código de esta ampliación y conservar `storage/password-help/`, cuentas, auditoría y movimientos. Las contraseñas cambiadas no deben restaurarse desde una copia anterior: siguen funcionando con el backend anterior. La revocación de sesiones también se conserva. No hay DDL que revertir.

## Verificación reproducible

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
node --check public/assets/js/account-security.js
```

`tests/account_http.py` cubre cambio propio en los tres roles, contraseña actual incorrecta, campos ajenos, confirmación, CSRF, revocación, persistencia de la sesión actual, reintentos, confidencialidad de recibos, rollback forzado de auditoría, respuesta pública uniforme, deduplicación, nuevos avisos tras cierre, restablecimiento manual, permisos y límites de intentos. Dos procesos PHP independientes comprueban la creación simultánea de un único aviso. El helper `tests/account-inspect.php` solo opera por CLI, con entorno test, base `afec_test_*` y host loopback.

Navegador local con cuentas sintéticas: ayuda enviada desde el login, confirmación sin cambio de contraseña, alerta visible al ingresar como administrador, formulario personal y bandeja con acciones. Vista de escritorio de 1100 px y móvil de 390 px, sin desbordamiento horizontal en la bandeja y sin errores de consola. No se cambiaron contraseñas reales ni se enviaron solicitudes sintéticas en producción.

Resultado local: **640 comprobaciones HTTP y 12 SQL aprobadas** con Apache/FastCGI y `.htaccess` reales, incluyendo las regresiones anteriores de planificación y permisos de comandantes. También pasaron simulación/repetición del migrador, sintaxis PHP/JavaScript y revisión de whitespace. El aviso sintético de la demo se marcó atendido y se cerró la sesión administrativa al terminar; el aviso desapareció sin cambiar la contraseña de la cuenta de demostración.
