# Administrador y Comandante

Política confirmada el 15/10/2026: Comandante gestiona toda la operación, salvo cuentas y roles. Sustituye las restricciones de los tres roles descritas en documentos de fases anteriores.

## Alcance y archivos

Comandante y Administrador pueden gestionar sistemas, instalaciones, objetivos, estados, recetas, mercancías, catálogo inicial, abastecimiento, paladas y entregas/correcciones de terceros. Solo Administrador gestiona cuentas, roles, contraseñas ajenas y avisos de ayuda. Cambiar la contraseña propia continúa disponible para ambos.

Las listas personales, cargas de carrier y recibos siguen perteneciendo a su cuenta. Las identidades de comandante y sus paladas son compartidas para registrar operaciones de terceros; el actor que registra permanece identificado por separado. No cambia la regla de correcciones compensatorias, las versiones concurrentes, las confirmaciones de archivo ni la idempotencia.

`Security/UserRole.php` reconoce dos roles efectivos: `admin` y `viewer` (Comandante). El valor histórico `operator` se interpreta como `viewer`. `UserRepository` normaliza las respuestas de sesión y cuentas; `AuthService` valida el rol al ingresar y consultar la sesión. `MutationService` vuelve a comprobar el rol almacenado bajo bloqueo dentro de cada transacción. Un rol enviado o alterado por el cliente no concede permisos.

`UserService` solo admite asignar `admin` o `viewer`; los dos formularios de cuenta y `admin/roles` ofrecen esas opciones. Si se edita una cuenta antigua, se guarda el rol elegido y la auditoría conserva el valor previo real, incluso `operator`. Las cuentas antiguas funcionan inmediatamente como Comandante, sin altas nuevas ni cambios masivos de datos. Se conservan las protecciones del último administrador y la revocación de sesiones al editar cuentas.

Se actualizan controladores/servicios de planificación, recetas, catálogo y logística, junto con menús y controles HTML. Las rutas históricas `admin/commodities` conservan su URL por compatibilidad; sus permisos son operativos, accesibles para ambos roles. Las rutas `admin/users`, `admin/user*`, `admin/roles` y `admin/password-help*` continúan exclusivas del administrador.

## Pruebas reproducibles

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
node tests/account_payload.mjs
node tests/quick_load.mjs
```

La base debe ser local `afec_test_*`, entorno `test`; el ejecutor lo verifica antes de aplicar fixtures. `commander_planning_http.py` recorre edición, archivo/restauración, objetivos, recetas, estados y las prohibiciones de administración de cuentas. `roles-inspect.php` comprueba normalización, conservación del rol histórico, rechazo de un actor que falsifica ser administrador y revocación transaccional. Los recorridos de mercancías, importación del catálogo, paladas y entregas de terceros usan Comandante. Las regresiones conservan pruebas de CSRF, concurrencia, idempotencia, privacidad y rollback ante fallo de auditoría.

Validación local: **746 comprobaciones HTTP y 12 SQL aprobadas** con Apache/FastCGI y `.htaccess`, simulación y repetición del migrador, cinco aserciones de identidad y 16 de cantidades/payload de carga rápida. Sintaxis PHP/JavaScript y `git diff --check` aprobados. Navegador local: cuenta histórica mostrada como Comandante; ambos formularios ofrecen solo dos roles; edición sin Motivo; Comandante con controles de edición y catálogo, sin enlace de Usuarios. No se guardaron cambios de cuenta desde la comprobación visual. Sesiones de prueba cerradas y sin errores de consola.

## Riesgo y rollback

La ampliación permite que cualquier cuenta activa de Comandante modifique planificación y catálogo compartidos, incluida la atribución de entregas a otros comandantes, según la decisión del propietario. Las escrituras siguen auditadas. No se añaden tablas, migraciones, correos ni servicios externos; no se ejecuta SQL en producción al publicar.

Rollback: revertir el cambio completo de código mediante un commit nuevo, conservando cuentas, identidades, auditoría, listas y movimientos. El código anterior restaura las restricciones previas de `viewer` y reconoce `operator` almacenados. Las cuentas antiguas guardadas posteriormente como Comandante mantienen ese rol; no restaurar hashes, sesiones ni balances antiguos.
