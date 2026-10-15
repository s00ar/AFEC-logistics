# Carga rápida y paladas individuales — 15/10/2026

## Alcance y compatibilidad

Referencia: instrucciones aportadas por el propietario, con la tabla conceptual de Excel/Sheets. No se recibió una nueva planilla ni se afirma haber auditado fuentes externas. Se conserva PHP/PDO, HTML y JavaScript vanilla, sin build ni dependencias de producción.

Archivos afectados: servicio y repositorio de logística, controlador de expansión y rutas API; nuevos repositorio/servicio de paladas; tabla de `system.php` y dos parciales; módulo `quick-load.js`, formularios detallados, CSS, allowlist Apache y pruebas. Migración aditiva `008_commander_standard_load.sql` y paquete para phpMyAdmin `AFEC_actualizar_paladas.sql`.

Antes de aplicar: respaldo de la base y esquema 001–007 válido. Riesgos principales: atribuir entregas a otro comandante, reintentar una operación ya guardada y compensaciones concurrentes. La selección se muestra por instalación, las solicitudes se identifican antes de enviarse y todas las escrituras usan CSRF, idempotencia y transacciones. Los bloqueos siguen el orden existente de usuario, instalación y objetivo; las paladas se bloquean al usarlas. No se cambian IDs, recetas, objetivos ni movimientos originales. Se conserva el hash de las solicitudes manuales anteriores para que sus reintentos sigan funcionando.

Rollback: retirar el código de esta fase y conservar las columnas nullable/aditivas y registro/checksum 008; los movimientos nuevos son entregas y compensaciones compatibles con el historial previo. No borrar filas para revertir una corrección ni ejecutar el instalador inicial. La reversión de datos reales requiere restauración de respaldo revisada; no se ofrece un `DROP` automático. No usar un migrador antiguo que desconozca 008.

## Reglas reutilizadas

- Comandante configura su propia palada y registra/corrige sus entregas; operador/admin puede hacerlo en nombre de terceros. La resta rápida se limita siempre al comandante seleccionado. La corrección detallada de un movimiento mantiene sus permisos anteriores.
- Palada sin configurar: NULL, sin asumir una cantidad global. Guardar vacío elimina la preferencia. Se admiten enteros positivos hasta BIGINT firmado; cantidades e IDs viajan como cadenas y el frontend usa BigInt para validar.
- Se permite sumar más que lo pendiente, como antes: se registra toda la cantidad y se informa del sobrante. No se recorta silenciosamente.
- La resta rápida compensa entregas positivas del comandante en orden de ID ascendente, hasta completar la cantidad exacta. Cada parte conserva el vínculo original, carrier, autor y comandante. Todas las partes y sus auditorías se confirman juntas o se revierten juntas. Nunca deja negativo el saldo global ni consume entregas de otro comandante.
- Se conserva la corrección de instalaciones terminadas/archivadas; las nuevas sumas requieren instalación y objetivo abiertos/activos. El carrier y las fechas especiales siguen disponibles en **Entrega detallada / carrier**.

## Flujo y recuperación

El selector de comandante se conserva en sessionStorage por cuenta e instalación. La palada se guarda en MySQL por comandante y se propone en todas las filas; seleccionar otro actualiza las cantidades propuestas. El listado rápido pagina de 50 en 50 con **Más comandantes**; la identidad propia está disponible aunque no esté en la primera página. Las cuentas sin identidad pueden crearla mediante su primera entrega detallada y recargar.

Los botones actúan directamente; Enter suma el campo de la fila. No hay ventanas de confirmación en este flujo. Las restas requieren motivo en la cabecera. Se bloquean los controles durante el envío, se ignora el segundo clic de un doble clic y la repetición de Enter mantenido. La confirmación aparece dentro de la instalación; se consultan los totales y porcentajes del servidor sin recargar la página ni perder el comandante.

Antes del envío se conserva la solicitud y su Idempotency-Key en sessionStorage. Si falla la conexión o llega una respuesta incierta, se bloquean nuevas cargas y **Comprobar y reintentar** usa la misma solicitud/clave. Recargar la página no pierde esta operación. Si la escritura fue confirmada pero falló la lectura de totales, se informa que ya se guardó y que solo hay que recargar. No reenviar la entrega con otra clave. El modo rápido requiere almacenamiento de sesión disponible; el formulario detallado conserva su mecanismo anterior.

## API

| Método | Ruta | Datos y permiso |
| --- | --- | --- |
| GET | `quick/options&list_page=N` | Autenticado; comandantes accesibles, palada/version, identidad propia y disponibilidad del esquema |
| GET / PATCH | `commander/load&id=ID` | Consultar una identidad concreta o guardar `quantity` cadena positiva o NULL y `version`; propia o autorizada |
| POST | `deliveries&installation_id=ID&commodity_id=ID` | Se reutiliza el endpoint: `use_standard_load=true` sustituye a `quantity`; nunca ambos |
| POST | `quick/withdraw&installation_id=ID&commodity_id=ID` | `commander_id`, `quantity` o `use_standard_load=true`, `reason`; compensaciones transaccionales |

Scopes de recibos: `logistics.load.save`, `logistics.delivery`, `logistics.quick.withdraw`. La cantidad de una palada se resuelve en el servidor dentro de la operación; reintentar después de cambiar la preferencia devuelve el resultado original.

## Pruebas reproducibles

```text
python tests/run.py --php RUTA_PHP --config CONFIG_TEST --webroot repository --apache-root RUTA_APACHE
node tests/quick_load.mjs
python tests/load_upgrade.py --php RUTA_PHP --mysql CLIENTE_MYSQL --config CONFIG_TEST --initial-sql SQL_INICIAL_001_006
```

Se usan bases locales aisladas `afec_test_*`, nunca la base real. La suite cubre las funciones anteriores y escenarios de paladas, cantidades manuales, ambas restas, atribución, permisos/CSRF, versiones, doble solicitud, exceso sobre pendientes, rechazo de saldos negativos, concurrencia, rollback de auditoría y precisión BIGINT. La prueba de migración importa 007 y el paquete exacto 008 sobre una instalación previa, compara todos los datos, repite el paquete y rechaza prerrequisitos inválidos. Revisión adicional de la UI mediante navegador sobre una base aislada, sin alterar la demo anterior ni producción.

El código es compatible con 007: las cargas/restas manuales siguen disponibles y las paladas indican que falta importar 008. Esta fase no ejecuta migraciones remotas ni publica automáticamente en Hostinger.

## Resultado verificado

15/10/2026: 12 comprobaciones SQL y 448 HTTP aprobadas con Apache, FastCGI y `.htaccess` real, incluyendo regresión de CRUD, roles, recetas, listas e historial. Las 15 aserciones JavaScript de cantidades/payloads y el lint PHP/JS pasaron. Los paquetes phpMyAdmin 007 y 008 pasaron sus pruebas aisladas de preservación, repetición y validación de checksum/prerrequisitos; se comprobó también sumar y restar manualmente antes de aplicar 008.

Prueba de navegador en `afec_test_quick_ui_*`: paladas independientes de 784/100, suma completa con sobrante, resta estándar repartida, carga con Enter, resta manual, doble clic que agrega solo 5 t, selección persistida tras recargar y propuesta automática al cambiar de comandante en el formulario detallado. Totales e historial comprobados en pantalla. A 390 px, la página no desborda horizontalmente y la tabla tiene su propio desplazamiento. Se restableció el viewport al terminar. Solo se modificaron datos sintéticos locales; producción permanece intacta.
