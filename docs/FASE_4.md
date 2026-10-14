# Fase 4 — identidad AFEC y recetas de facilities

14/10/2026. Implementación local; precarga de recetas reales pendiente de fuente. Sin publicación ni migración en Hostinger.

## Alcance y archivos

1. CSS con los cuatro tonos aportados: `#0B0E14`, `#C7C7C7`, `#FF7100`, `#CC0000` y variantes legibles para superficies/texto. Bordes con degradados estáticos y animación de opacidad/transformación. Cabecera/acceso animados; tarjetas/paneles solo con hover/foco. Sin dependencias, fuentes externas, solicitudes adicionales ni JavaScript de animación; respeta `prefers-reduced-motion`. Las máscaras tienen fallback a bordes estáticos. No se afirma una medición de rendimiento que no se ejecutó.
2. Dashboard/sistema: botones Eliminar visibles en rojo, Ver instalaciones y Agregar facility desde las tarjetas. Eliminar conserva la semántica de archivo reversible y todo el ledger; restauración desde filtros de eliminados/archivados. Las instalaciones pertenecen a un sistema, no al catálogo de recetas.
3. `RecipeRepository`, `RecipeService`, `RecipeController`, vista `recipes.php`, formularios y extensión del editor vanilla. Operadores/admin crean, consultan, editan materiales/fuente, eliminan/restauran y consultan historial. Comandantes/público no cambian planificación.
4. Creación de instalación con receta y versión revisada: bloquea sistema/receta/materiales, copia todos los objetivos, tipo/tier y procedencia, registra revisiones/auditoría y confirma una única operación idempotente. Un fallo deshace toda la operación. Instalaciones existentes conservan sus objetivos al editar/eliminar la receta. Se pueden ajustar independientemente; no se altera ninguna entrega.
5. Guardar objetivos como receta permite reutilizar requeridos activos de una instalación existente. Se revisa el formulario antes de guardar; no deriva cantidades de faltantes o movimientos. El catálogo inicial no contiene cantidades supuestas del juego.

Migración `005_facility_recipes.sql`: dos tablas y procedencia nullable de receta/versión en instalaciones. Las versiones 001–004 permanecen intactas. Listado paginado de 25, hasta 100 materiales por receta, IDs/cantidades decimales sin conversión a Number y límite JSON de 16 KiB. El selector carga las páginas del catálogo al abrir el formulario; no se descarga durante la carga inicial de la página.

## Validación reproducible

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

PHP 8.4.26, MariaDB 11.4.11 y Apache 2.4.58 locales. 12 comprobaciones SQL y 272 HTTP con `public/`; 292 HTTP con raíz completa/.htaccess. Migración simulada y reaplicación aprobadas. Pruebas nuevas: permisos, CSRF, idempotencia, cantidades inválidas/duplicadas, fuente URL segura, stale version, copia completa, metadatos asignados por servidor, edición independiente, archivo/restauración, historial y rollback deliberado de auditoría. Se mantuvieron las pruebas de concurrencia de planificación/cuentas/correcciones y protección de archivos privados.

La demo `afec_test_preview` se respaldó en `.local/preview-before-recipes.sql` antes de aplicar 005 localmente. No se reseteó la demo ni se precargaron recetas ficticias para el usuario. Las recetas de test se crean únicamente en la base aislada de fixtures. La herramienta de navegador no expuso una pestaña abierta disponible; sigue pendiente la comprobación visual móvil/escritorio de esta fase. La prueba HTTP de HTML/controles no sustituye un recorrido del formulario en el navegador.

## Riesgos, límites y rollback

El handoff no incluye recetas numéricas por tipo/tier; se solicitó elegir datos AFEC/Sheets o catálogo comunitario como referencia ajustable. Mientras no se confirme/aporte esa fuente, el catálogo se completa con plantillas verificadas por operadores. No se afirma que estén precargadas todas las facilities del juego. Fuente/fecha/variante se documentan en cada receta; no hay sincronización externa ni importación automática.

Cambiar una receta afecta solo nuevas instalaciones. Cambiar el tipo de una instalación existente tampoco reemplaza sus objetivos; se ajustan explícitamente y con auditoría. No se implementó reaplicar una receta en lote sobre un proyecto existente para evitar sobrescribir ajustes locales sin una política aprobada.

Riesgo principal: cantidades de referencia incorrectas y DDL parcialmente aplicado. Las transacciones no revierten CREATE/ALTER de MySQL. Revisar registro/checksums y respaldo ante instalación incompleta. No modificar migraciones aplicadas ni borrar registros para reintentar.

Rollback de código: punto previo `3096031`, manteniendo tablas, columnas, datos, 005 y su registro/checksum. El código anterior puede consultar la planificación; no ejecutar un migrador antiguo que desconozca versiones aplicadas. Para restauración de datos, ensayar el respaldo completo en una base aislada. No desplegar/migrar en Hostinger sin autorización específica.

Guía: [GUIA_USO.md](GUIA_USO.md). Instalación: [DESPLIEGUE.md](DESPLIEGUE.md).
