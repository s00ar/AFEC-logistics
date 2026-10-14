# Fase 3 — cuentas y logística compartida

Fecha: 14/10/2026. Implementada y verificada localmente. No publicada ni aplicada en Hostinger/Sheets.

## Alcance, archivos y fases

Se ejecutaron tres bloques: cuentas/roles, estados/movimientos y listas/abastecimiento. Nuevos servicios `UserService`/`LogisticsService`, repositorio `LogisticsRepository`, validación `LogisticsInput`, controlador `ExpansionController`, migraciones 002/003/004, vistas de usuarios/listas/modales y módulo `logistics.js`. Se ampliaron el editor JS, el repositorio de progreso, navegación, CSS y permisos HTTP de recursos del despliegue raíz.

- Administración de usuarios: crear, consultar, editar usuario/nombre/rol/actividad, eliminar/restaurar y restablecer contraseña. Bajas lógicas, sin romper referencias de auditoría o movimientos. Tres roles incorporados; `viewer` se presenta como Comandante y puede realizar logística propia, listas y abastecimiento.
- Bloqueo transaccional común para cambios de cuentas y protección del último administrador. Cambio de cuenta, rol, contraseña o actividad revoca las sesiones mediante `auth_version`. No se permite desactivar la propia cuenta ni quitarse el rol de administrador: otro administrador debe hacerlo.
- Construcción manual En construcción/Terminada, con motivo y confirmación del cierre incluso si hay faltantes. No modifica cantidades reales. Instalaciones existentes quedan Sin clasificar; se conserva su estado libre anterior sin inferirlo ni sobrescribirlo. Nuevas instalaciones comienzan En construcción.
- Entregas recibidas, con `commander_id`, `created_by_user_id`, carrier opcional y fecha. Comandantes registran/corrigen las propias; operadores/admin pueden registrar/corregir terceros. Se admite alta de comandante (operador/admin) y carrier desde el formulario.
- Retiros parciales repetidos o completos como movimientos compensatorios vinculados al original. No exceden la cantidad neta que conserva esa entrega ni dejan saldo negativo. Correcciones admitidas aun después de terminar/archivar un destino.
- Listas personal y flota. Identidad personal derivada exclusivamente de sesión; ninguna cuenta consulta/modifica otra lista personal. Todos los autenticados gestionan flota y propia. Selecciones únicas por lista/instalación, removibles sin cambiar la planificación. Totales de faltantes por mercancía con destinos paginados; consulta consistente por snapshot. Excluye terminadas/archivadas, objetivos retirados y cantidades cubiertas.
- Lugares de abastecimiento compartidos, múltiples por mercancía, edición/retiro/restauración por cualquier usuario autenticado, versión, autor y fecha. 🌐, modal y copia individual de nombres; fallback a selección manual si el portapapeles falla.
- Logo original sin edición en cabecera/login/favicon; archivo público permitido explícitamente en `.htaccess`.

Las operaciones usan PDO preparado, CSRF, permisos de servidor e idempotencia. El navegador conserva solicitudes pendientes; para altas/restablecimientos de contraseña conserva solo metadatos y cuerpo sin contraseña. Recupera primero un recibo privado de operación y solo pide reingreso si no hay resultado confirmado. Auditoría/recibos no contienen contraseñas ni hashes de acceso. La solicitud se identifica sin incluir la contraseña en un digest rápido; un verificador lento de contraseña protege los reintentos con el mismo contenido.

## Migraciones y riesgos

`001_initial.sql` permanece intacto. `002_user_management.sql` añade versión/baja lógica y un mutex técnico de cuentas. `003_logistics_lists.sql` añade estado formal, selecciones y abastecimiento; mantiene el índice de FK antes de sustituir la unicidad de reversión por un índice que permite varios retiros parciales.

`004_password_operation_verifier.sql` añade un verificador lento para operaciones con contraseña, sin usar su texto en el hash rápido de solicitud. Se confirmó que no había operaciones de contraseña anteriores en la demo antes de aplicarlo.

MySQL/MariaDB puede confirmar DDL implícitamente. No se afirma que rollback transaccional deshaga estas migraciones. El migrador registra checksum/estado y exige revisar una instalación parcial. La demo `afec_test_preview` fue respaldada en almacenamiento local privado antes de aplicar 002/003/004; no se reseteó ni se modificó la base real.

La selección es por instalación y sigue sus objetivos activos actuales; un objetivo agregado posteriormente pasa a la lista. Una selección excluida puede permanecer para reaparecer si el destino se reabre/restaura. Recalcular al consultar no equivale a sincronización instantánea entre navegadores.

Los permisos de corrección se acotan a movimientos propios (autor o comandante vinculado) para Comandante y cualquier movimiento para operador/admin. Corregir destino/material equivocado requiere retirar y registrar una nueva entrega explícitamente; no se implementó una transferencia atómica entre destinos.

## Validación reproducible

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

PHP 8.4.26, MariaDB 11.4.11/InnoDB y Apache 2.4.58 local con PHP FastCGI. Las pruebas exigen host loopback, base `afec_test_*` y entorno test antes de resetear fixtures.

- 12 verificaciones SQL; 230 HTTP con document root `public/` y 250 HTTP con la raíz completa y `.htaccess` reales.
- Regresión de planificación, permisos/CSRF, datos inválidos, cantidades y paginación; nuevo CRUD de cuentas/roles, revocación, eliminación/restauración y contraseña nueva.
- Privacidad frente a IDs de otro usuario, incluidas cuentas administradoras; flota editable por distintos comandantes; selección única y reapertura de déficits tras cambios/compensaciones.
- Abastecimiento compartido, edición obsoleta rechazada, retiro/restauración y disponibilidad del indicador de lugares activos.
- Entrega propia y de terceros, atribución separada, sobreentrega, corrección parcial repetida, prohibición de impersonar o retirar en exceso y original intacto.
- Trabajadores PHP con conexiones PDO independientes y barrera: retiros concurrentes que no sobregiran; selección de flota concurrente sin duplicar; administradores que intentan quitarse permisos mutuamente, conservando uno autorizado.
- Fallo deliberado al insertar auditoría: rollback completo de entrega y de cuenta/comandante/operación. Hash de los seis movimientos originales del fixture idéntico al final.
- Recibos privados sin contraseñas, recursos y logo servidos correctamente, bloqueo HTTP de rutas privadas en Apache, simulación y reaplicación del migrador.
- Sintaxis PHP/JS y HTML de pantallas/controles revisados. La prueba visual de esta ampliación en el navegador integrado permanece pendiente por el bloqueo de navegación local documentado en la adaptación raíz; no se afirma un recorrido visual ejecutado de estos nuevos formularios.

## Rollback

Punto de código anterior: `a93955e`. Conservar 002/003/004, sus checksums y registro de migraciones al volver a código anterior; no borrar tablas, cuentas, selecciones, fuentes o movimientos. El código previo no ofrecerá las funciones nuevas, pero puede seguir consultando saldos compensados y respetando cuentas inactivas. No ejecutar un migrador antiguo que desconozca versiones aplicadas.

Ante migración parcial, revisar el registro y restaurar un respaldo completo revisado en una copia aislada; no reaplicar DDL a ciegas ni eliminar datos reales para repetir la instalación. El paquete de rollback para Hostinger debe mantener la entrada raíz y protección HTTP verificadas.

## Pendientes fuera de esta ampliación

Importación de Sheets, fuentes Apps Script/Terminal AFEC ausentes, recetas verificadas por tipo/tier, reservas y cargas en tránsito, catálogo editable completo y pantallas administrativas de comandantes/carriers. El selector de identidades en entregas está acotado a los primeros 101 registros activos; para mayores volúmenes se incorporará búsqueda/paginación. No hay stock/precio externo, registro público ni roles personalizados.

Guía actualizada en [GUIA_USO.md](GUIA_USO.md). Configuración y puesta en marcha reales en [DESPLIEGUE.md](DESPLIEGUE.md).

## Ajuste de cabecera — 14/10/2026

Se utiliza el archivo aportado en `public/assets/images/Emblema AFEC Logística Espacial.png`, idéntico al original de 1254 × 1254 px. Cabecera a 120 px en escritorio y 96 px hasta 700 px de viewport, altura automática y sin compresión flex. El header permite ajuste de altura y navegación en varias filas; en móvil se oculta el texto redundante junto al emblema. Acceso/favicon usan la misma ruta, codificada para espacios/acentos y permitida explícitamente en `.htaccess`.

Archivos: layout, login, CSS, `.htaccess`, recurso aportado, guía y referencia del logo en la prueba HTTP existente. Riesgo limitado a presentación y disponibilidad de ese recurso; sin cambios de lógica ni SQL. Rollback: revertir este ajuste sobre `57ebe88`, conservando el archivo original del usuario.

Validación: lint de ambos templates PHP y sintaxis del ejecutor Python; solicitudes de solo lectura a Apache local en `http://127.0.0.1:8077/`, `/?page=login`, CSS y URL codificada del PNG: 200, referencias correctas y bytes idénticos. Configuración, templates, imagen desconocida y sufijo `.bak`: 403. También se verificaron dashboard, acceso y PNG con document root aislado en `public/`. Para repetir, recargar esas rutas y comparar el PNG servido con el archivo original. La suite existente comprueba ahora la ruta del logo utilizada por la aplicación; no se repitió la suite de negocio para este cambio de presentación. La herramienta de navegador no expuso una pestaña abierta disponible, por lo que queda pendiente comprobar visualmente el nuevo tamaño.
