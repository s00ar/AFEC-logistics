# Fase 2 — CRUD de planificación

Fecha: 14/10/2026. Implementada y verificada localmente; sin cambios en Hostinger ni Sheets.

## Alcance

- Operadores y administradores crean y editan sistemas e instalaciones, con archivo y restauración. La identidad y el sistema padre de una instalación se conservan.
- Objetivos independientes por instalación/material: alta, cambio de cantidad, retiro y restauración. Se conservan versiones y revisiones con autor, motivo, fecha, cantidad anterior/nueva y estado activo anterior/nuevo.
- Historial privado de cambios para operadores/administradores. La lectura operativa continúa siendo pública; visitantes y viewers no escriben.
- El administrador puede agregar materiales al catálogo. No se inventan recetas, tipos o estados del juego: los metadatos son texto acotado.
- Escrituras PDO transaccionales, CSRF, permisos revalidados bajo bloqueo, clave de idempotencia y control de versión para evitar sobrescribir cambios de otro operador.
- Formularios HTML/JavaScript vanilla, cantidades como cadenas decimales, listados de 25 registros y recuperación de solicitudes pendientes después de recargar.

Archivar un sistema oculta su planificación activa sin modificar los estados propios de sus instalaciones; al restaurarlo, las previamente archivadas siguen archivadas. Retirar un objetivo conserva su cantidad y todos sus movimientos. Ninguna operación de esta fase modifica el ledger.

## Archivos

Servicios `PlanningService` y `MutationService`, repositorio `PlanningRepository`, validación `PlanningInput` y controlador `PlanningController`; ampliación de API, repositorio de progreso, vistas, CSS y módulos JavaScript. Pruebas en `tests/planning_http.py`, `planning-worker.php` y `planning-inspect.php`, integradas en `tests/run.py`.

El esquema `001_initial.sql` permanece sin cambios. Se utilizan las tablas de operaciones, auditoría y revisiones previstas en la fase 1.

## Pruebas reproducibles y resultados

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST
```

Entorno aislado: PHP 8.4.26, MariaDB 11.4.11/InnoDB, base local `afec_test_foundation`. El ejecutor exige entorno test, host loopback y prefijo `afec_test_` antes de cualquier reset.

- 12 comprobaciones SQL y 116 comprobaciones HTTP aprobadas, incluyendo la regresión de la fase 1.
- CRUD de operador, permisos/CSRF, campos falsificados, cantidades inválidas y grandes, versiones obsoletas, archivo/restauración, historial, paginación y reintentos idempotentes.
- Dos procesos PHP y conexiones PDO independientes sincronizados por barrera: creación duplicada, edición concurrente y creación concurrente del mismo objetivo. Una sola operación efectiva por duplicado; los cambios incompatibles reciben conflicto.
- Fallo deliberado al insertar una revisión: rollback completo del objetivo, operación y auditoría. Hash de todos los movimientos antes/después idéntico.
- Migración simulada y reaplicación sin versiones pendientes.
- Navegador móvil y escritorio: creación real de sistema/instalación de demo, alta de objetivo, cambio 1000 → 1250, retiro/restauración e historial. Sin desbordamiento horizontal de página; las tablas se desplazan dentro de su región. Sin errores/warnings de consola en el recorrido normal.
- Respuesta HTTP perdida después del commit, mediante un proxy exclusivamente local: recarga, recuperación del formulario y reintento con el mismo payload/clave. La traza confirma el mismo ID y `replayed=true`; no se crea otro sistema. El proxy se detuvo después de la prueba.

Capturas y datos sintéticos de navegador están en almacenamiento local ignorado; no forman parte del despliegue.

## Riesgos y límites

No hay aún altas de entregas, correcciones/reversiones, importador, edición de usuarios ni CRUD de comandantes/carriers. Las políticas de sobrantes y reversión siguen pendientes. Se admite cantidad objetivo cero; se muestra sin porcentaje, conforme al comportamiento provisional documentado.

No hay borrado físico de planificación ni traslado de instalaciones entre sistemas. Los nombres no identifican entidades ni se fuerza unicidad de nombres sin una regla de negocio confirmada. El catálogo aún no tiene edición/retiro funcional; solo altas administrativas.

La idempotencia distingue usuario, operación y clave. El navegador conserva operaciones pendientes en la sesión de esa pestaña; cerrar definitivamente la pestaña elimina ese almacenamiento. Si el almacenamiento no está disponible, el formulario impide enviar una nueva escritura. Un error de versión exige recargar y revisar antes de volver a guardar.

El listado administrativo de usuarios conserva el límite defensivo de 200 de la fase 1. No se afirma rendimiento con un export real de Sheets ni compatibilidad probada con el servidor remoto.

## Rollback

Volver al código de fase 1 (`d8a34e8`) en una copia o mediante un revert revisado. No requiere revertir DDL: no hay migración nueva. Conservar las filas de planificación, auditoría, revisiones y operaciones; no eliminar datos para bajar de versión. El código anterior solo mostrará la planificación activa y no ofrecerá formularios.

La preparación del despliegue desde la raíz se documenta por separado en [DESPLIEGUE.md](DESPLIEGUE.md). Las instalaciones reales requieren configuración privada y verificación del servidor antes de habilitarlo.
