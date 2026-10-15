# Mercados del Sistema

## Alcance de la implementación — 15/10/2026

Integrar los CSV aportados, conservando autenticación PHP/PDO, componentes y permisos actuales. Cambios aditivos en `database/migrations/009_system_markets.sql`, repositorio/servicio/controlador de mercados, pantalla y JavaScript propios; enlaces de consulta en materiales. Las instalaciones comerciales son entidades separadas de las obras de construcción. No se modifican entregas, paladas, comandantes, objetivos ni el abastecimiento manual anterior.

Fases: (1) verificar esquema/CSV y definir migración; (2) importación transaccional y trazabilidad; (3) consulta, filtros, administración y enlaces desde logística; (4) pruebas aisladas, paquete phpMyAdmin y verificación de publicación autorizada. Cada fase conserva los archivos originales y los cambios locales previos del usuario.

Pruebas previstas: migración y repetición, CSV reales (197 existencias, 93 mercancías, cuatro estaciones), importación repetida, fechas anteriores/iguales, filas ausentes, cantidades nulas/cero/BIGINT, equivalencias ambiguas, permisos/CSRF, rollback transaccional y hashes de tablas operativas. Recorrido HTTP con Apache y comprobación de interfaz de escritorio/móvil.

Riesgos: los CSV son parciales y no incluyen economía ni pads. Se muestran como desconocidos hasta declararlos expresamente. Stock no equivale a entrega ni reserva: no descuenta faltantes. La fecha original tiene precisión de día; no se inventa hora. Caducidad de siete días confirmada por el propietario; configurable mediante `markets.stale_days` (1–3650).

Rollback: volver al código anterior compatible con la raíz y conservar las tablas nuevas, el registro/checksum 009 y los datos/auditoría. No ejecutar un migrador anterior que desconozca 009. No eliminar tablas ni restaurar una base antigua sobre movimientos posteriores. Los errores de una importación revierten su transacción completa; MySQL DDL no es reversible mediante ROLLBACK.

No aplicar migraciones reales por push ni desde peticiones web. El paquete phpMyAdmin se entrega para revisión y aplicación expresa con respaldo. El código detectará la ausencia de 009 sin impedir el uso del resto de la aplicación.

## Datos aportados y modelo

Se conservan copias exactas de ambos archivos del 15/10/2026 en `resources/markets/`. El inventario contiene **197 existencias, 93 mercancías distintas y cuatro estaciones** de `Col 359 Sector TN-A c1-20`. El resumen contiene cuatro filas coincidentes: Ciudadela Orbital AFEC Prime (48 mercancías), Clarke Station (61), Hardie Sanctuary (38) y Toure Venture (50).

El catálogo actual reconoce 45 mercancías mediante código, nombre o alias; las otras 48 se conservan con su nombre original, sin crear materiales de colonización ni adivinar equivalencias. Un Comandante o Administrador puede vincularlas mediante **Equivalencia**. El nombre original permanece visible y en cada observación.

| Tabla nueva | Contenido / unicidad |
|---|---|
| `market_stations` | Sistema + estación normalizados; cuerpo, economía y pad opcionales, resumen original y fechas/fuentes |
| `market_products` | Nombre original normalizado único; vínculo opcional al ID de `commodities` |
| `market_imports` | SHA-256 único del contenido/contexto, nombres y bytes originales de ambos CSV, actor y resultado |
| `market_observations` | Observaciones inmutables de cantidad, disponibilidad, fecha, fuente y verificación; una por importación/estación/mercancía |
| `market_stock` | Una observación vigente por estación/mercancía, con versión para impedir actualizaciones perdidas |

Las instalaciones comerciales están separadas de las instalaciones en construcción. Las escrituras usan PDO, `MutationService`, autorización operativa revalidada para Comandante y Administrador, CSRF, recibos idempotentes, auditoría existente y una transacción con bloqueo común de mercados. Un fallo revierte observaciones, stock, importación y auditoría juntos.

## Activación en una instalación existente

**El push publica código; no ejecuta SQL ni importa inventario.** La migración y carga inicial se probaron únicamente en bases locales aisladas. Para habilitar producción:

1. Respaldar la base actual y verificar que las migraciones 001–007 estén completas y sin modificaciones.
2. En phpMyAdmin, seleccionar la base correcta e importar **`database/upgrades/AFEC_actualizar_mercados.sql`**. Verificar el resultado **`AFEC: MERCADOS DEL SISTEMA HABILITADOS`**. Este paquete valida checksums, toma un bloqueo y registra la migración 009; admite 008 ausente y no la aplica por su cuenta. No importar a la vez otro paquete/migración.
3. Iniciar sesión como Comandante o Administrador → **Mercados del Sistema** → **Administración: importar CSV**. Seleccionar los dos originales, **Validar archivos**, revisar los recuentos, marcar confirmación y **Importar CSV validados**. El sistema del resumen se deduce del inventario; no hace falta escribirlo para este par de archivos.
4. Comprobar las cuatro estaciones, buscar **CMM** o **Compuestos CMM** (Clarke Station, 279.426 t, fecha 2026-10-15) y repetir los mismos archivos: la aplicación informa que ya están registrados, sin duplicarlos.
5. Completar economía y pad mediante **Datos de estación** únicamente con información verificada, indicando fecha, fuente y motivo. Hasta entonces sus valores son desconocidos y no aparecen en filtros de tamaño concreto.

Si el paquete informa estado parcial/incompleto, detenerse y revisar respaldo, `schema_migrations` y tablas; no borrar el registro ni marcarlo aplicado manualmente. MySQL puede conservar DDL parcial aunque una sentencia falle.

Alternativa CLI, siempre con `AFEC_CONFIG_FILE` privado fuera del webroot:

```text
php tools/migrate.php
php tools/migrate.php --apply
php tools/import-markets.php --user=USUARIO --stock=resources/markets/AFEC_mercados_stock_confirmado_2026-10-15.csv --summary=resources/markets/AFEC_mercados_resumen_2026-10-15.csv
```

El importador solo valida por defecto. Añadir `--apply` para escribir; en producción se exige además `--allow-production`, respaldo y autorización específica. El migrador CLI aplica todas las migraciones pendientes en orden (puede incluir 008); el paquete phpMyAdmin es la alternativa que aplica únicamente 009 sobre 001–007.

## Consulta y mantenimiento

Todos los usuarios autenticados pueden buscar por nombre original o nombre de colonización, filtrar sistema/estación/economía/tamaño de nave/estado, ordenar por disponibilidad, cantidad, fecha o estación y consultar los materiales de una estación pulsando su nombre. Resultados paginados de 25 filas. **Large** se destaca solo cuando el pad L fue registrado expresamente; una nave Medium admite estaciones M/L y Small admite S/M/L.

**Dónde conseguir** en los materiales de una construcción abre proveedores de ese material dentro de su sistema. En listas personal/flota abre proveedores de todos los sistemas, porque un material puede agrupar destinos diferentes. El registro manual de lugares anterior continúa disponible. El stock comercial no descuenta requerimientos, no registra entregas, no reserva y no afirma disponibilidad en tiempo real.

Estados calculados al consultar (UTC, precisión de día): **Confirmado** si cantidad, fecha y disponibilidad son conocidas y la observación está verificada, con antigüedad de hasta siete días; **Desactualizado** si supera siete días; **Desconocido** si falta alguno de esos datos o verificación. Cero confirmado se muestra como cero; ausencia en un CSV conserva la observación anterior. **Materiales requeridos sin proveedores** requiere elegir sistema y enumera materiales de obras activas sin stock positivo registrado en ese sistema; puede haber proveedores desactualizados, por lo que conviene revisar sus fechas. Esa consulta utiliza el sistema y no los filtros de economía/nave/estado.

Comandantes y administradores pueden importar, ver historial de observaciones/importaciones, corregir stock, vincular equivalencias y editar metadatos de estación, según la política vigente de dos roles. El acceso permanece autenticado; cuentas y roles continúan reservados a Administrador. Las correcciones piden motivo, fuente, fecha y versión. Una corrección manual crea otra observación y conserva la anterior. Ambos roles pueden corregir datos del mismo día con versión vigente; no pueden retroceder la fecha.

Una importación solo reemplaza stock con fecha **estrictamente posterior**. Registros antiguos o distintos del mismo día quedan trazados en el historial y en los contadores, sin sustituir lo vigente. El CSV no contiene hora, así que un conflicto del mismo día requiere comprobación y corrección manual. Dos usuarios autenticados importando a la vez el mismo contenido obtienen una sola importación. Los cambios de nombre de archivo no crean una segunda carga si el contenido/contexto coincide. Cada carga nueva conserva archivos originales y su resultado; no elimina mercancías omitidas.

## Formato CSV y API

UTF-8 (BOM opcional), coma, comillas dobles CSV estándar, máximo 1 MiB y 5000 registros por archivo; encabezados exactos y sin columnas adicionales:

```csv
sistema,estacion,cuerpo,categoria,mercaderia,oferta_t,disponible_para_compra,fuente,fecha_fuente,verificacion
```

```csv
estacion,cuerpo,mercaderias_confirmadas,fuente,observacion
```

`oferta_t` es un entero decimal sin separadores o vacío (desconocido), nunca negativo; cantidades e IDs viajan como cadenas para conservar BIGINT. Disponibilidad: `SI`, `NO` o `DESCONOCIDO`/vacío; verificación: `Confirmado`, `Confirmado en captura`, `Desconocido`, `No confirmado` o `Sin verificar`. Fechas `AAAA-MM-DD`, válidas y no futuras (se admite el día siguiente UTC por diferencia de zona horaria). Un resumen necesita una fecha inequívoca en `fuente` y sistema explícito si no se deduce de un inventario con un solo sistema. Al enviar ambos archivos deben coincidir cuerpos, estaciones y recuentos. Duplicados de estación/mercancía, comillas incorrectas, equivalencias ambiguas o cantidades contradictorias rechazan toda la carga.

Rutas bajo `/api/index.php?route=`:

| Método / ruta | Permiso y propósito |
|---|---|
| GET `markets` | Autenticado; `q`, `system`, `station_id`, `economy`, `ship_size=S/M/L`, `status=confirmed/stale/unknown`, `sort=stock/updated/station`, `commodity_id`, `missing=1`, `list_page` |
| GET `markets/options` | Autenticado; estaciones, sistemas, economías y caducidad |
| GET `markets/imports` | Comandante / Administrador; trazabilidad paginada |
| GET `markets/history&station_id=ID&product_id=ID` | Comandante / Administrador; observaciones paginadas |
| POST `markets/import` | Comandante / Administrador; multipart `stock_file`, `summary_file`, `system`, `action=preview/apply`; apply requiere `review_hash`, `confirmed=1` y clave idempotente |
| PATCH `markets/stock&id=PRODUCT_ID` | Comandante / Administrador; JSON con `station_id`, `version`, `quantity`, `available` booleano/null, `verified` booleano, `observed_on`, `source`, `reason` |
| PATCH `markets/station&id=ID` | Comandante / Administrador; `version`, `economy`, `max_pad` S/M/L/null, `observed_on`, `source`, `reason` |
| PATCH `markets/product&id=ID` | Comandante / Administrador; `version`, `commodity_id` o null, `reason` |

Todas las escrituras y la previsualización requieren `X-CSRF-Token`; escrituras requieren `Idempotency-Key`. Sin 009 las consultas del módulo responden `503 market_schema` y la pantalla muestra la actualización pendiente; sistemas y logística siguen funcionando.

## Verificación reproducible y archivos

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
python tests/market_upgrade.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --mysql RUTA_CLIENTE_MYSQL
python tools/build-markets-upgrade.py
```

Resultado final del 16/10/2026: **862 comprobaciones HTTP y 12 SQL aprobadas**, con la política vigente de dos roles integrada, migración CLI simulada/repetida, importación de los 197 valores exactos y repetición, permisos/CSRF, fechas anteriores/iguales, nulos/cero/BIGINT, equivalencias, dos procesos concurrentes, rollback al fallar auditoría y hashes de tablas operativas sin cambios. Paquete phpMyAdmin probado desde 001–007 con y sin 008, repetición, rechazo de prerrequisitos alterados y protección de estado parcial. Interfaz de mercados comprobada en escritorio de 1100 px y móvil de 390 px; búsqueda CMM, corrección local con historial y reimportación duplicada. Sintaxis PHP/JavaScript y diferencias de Git verificadas. No se ejecutó SQL ni se importó inventario en producción durante estas pruebas.

Fase adicional solicitada el 16/10: publicar el buscador previo en listas personal/flota y materiales de settlements, con lupa circular abajo a la izquierda y panel desplegable. Archivos: `commodity-search.php`, `commodity-search.js`, estilos de `app.css`, atributos de las tres vistas, layout y allowlist. Riesgo acotado a presentación/localización de filas; verificación de recursos y HTML de cada vista más suite completa. Rollback del buscador sin SQL; conserva cantidades y acciones de carga.

Archivos nuevos: `database/migrations/009_system_markets.sql`, `database/upgrades/AFEC_actualizar_mercados.sql`, `src/Services/MarketCsv.php`, `src/Services/MarketService.php`, `src/Repositories/MarketRepository.php`, `src/Http/MarketController.php`, `templates/markets.php`, `public/assets/js/markets.js`, `public/assets/css/markets.css`, ambos CSV en `resources/markets/`, `tools/import-markets.php`, `tools/build-markets-upgrade.py`, `tests/market_http.py`, `tests/market-inspect.php`, `tests/market-worker.php`, `tests/market_upgrade.py` y esta guía.

Archivos integrados: `.gitattributes`, `.htaccess`, `config/config.example.php`, `src/Config.php`, `public/index.php`, `public/api/index.php`, `templates/layout.php`, `templates/system.php`, `templates/wishlist.php`, `tests/database.php`, `tests/fixture.php`, `tests/run.py`, `README.md` y `docs/GUIA_USO.md`. La lupa se comparte mediante `templates/commodity-search.php` y `public/assets/js/commodity-search.js`; sus estilos y anotaciones de búsqueda están en `app.css`, las plantillas de sistema/listas/catálogo y `docs/STOCK_Y_CARRIERS.md`.

## Rollback operativo

Retirar el código del módulo o revertir su commit conservando las cinco tablas, datos/auditoría y registro 009. Si se utiliza CLI antiguo, no ejecutar el migrador que desconozca 009. Conservar las nuevas observaciones aunque se oculte la interfaz. El buscador puede retirarse por separado sin SQL. Una importación que falla se revierte íntegra; una importación ya aceptada se corrige mediante observaciones manuales auditadas, sin borrar historial. No hay un `DROP` automático que arriesgue la trazabilidad ni restauración global sobre entregas posteriores.
