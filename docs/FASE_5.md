# Fase 5 — precarga AFEC y comunidad

14/10/2026. El propietario autorizó usar ambas fuentes y aportó una investigación CSV. Catálogo preparado y disponible en la demo local; sin despliegue ni migración en Hostinger.

## Alcance, archivos y riesgo

Datos versionados y snapshots acotados en `resources/catalogue/`; preparador reproducible `tools/build-recipe-catalogue.py`; `RecipeCatalogue`/`RecipeCatalogueService`, API y controles administrativos; CLI `tools/seed-recipes.php`; migración `006_recipe_provenance.sql`; pruebas y guía. Mantiene PHP/PDO/vanilla y las rutas/protecciones del despliegue raíz.

71 recetas, 55 mercancías y 1418 materiales: 66 referencias comunitarias y 5 ejemplos AFEC. Procedencia y discrepancias en [SOURCES.md](../resources/catalogue/SOURCES.md). Se conserva intacto el CSV original. Datos sin cantidades/tipo y metadatos erróneos se reportan, sin inventar objetivos ni reglas de construcción.

Riesgos previstos: confundir ejemplos con recetas universales, diferencias de fuentes, identidades duplicadas y DDL parcial. Se mantienen variantes separadas, hash/clave de fuente, referencias visibles y tiers no confirmados vacíos. La carga exige administrador, CSRF, confirmación, snapshot revisado e idempotencia. El permiso se revalida en transacción; un mutex común serializa cargas por distintos actores. Crea solo fuentes faltantes, preserva recetas existentes/archivadas y ajustes locales, reutiliza mercancías por identidad sin renombrarlas. Coincidencias ambiguas o inactivas detienen la transacción.

La carga no crea sistemas/instalaciones ni importa movimientos. Cambiar una referencia tampoco actualiza objetivos de instalaciones existentes. No hay sincronización externa en producción ni un cargador de archivos/URLs arbitrarios.

006 añade origen, clave única y hash en recetas, más un mutex técnico. No modifica 001–005 ni contiene recetas de negocio. La demo fue respaldada en `.local/preview-before-catalogue.sql` antes de aplicarla. El CLI confirmó una reaplicación sin altas (71 fuentes preservadas), seguida de replay idempotente; hashes de instalaciones, objetivos y movimientos de la demo iguales antes/después.

## Pruebas reproducibles

```text
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST
python tests/run.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

12 verificaciones SQL; 308 HTTP con public y 333 con Apache/.htaccess raíz. Migración simulada/reaplicación aprobadas. Pruebas: autorización/CSRF y snapshot obsoleto, rollback de mercancías/recetas/recibos ante fallo de auditoría, aliases ambiguos y mercancías inactivas rechazados sin altas parciales, dos administradores con conexiones independientes, copia íntegra de materiales, fuentes visibles, primary/posterior, AFEC/comunidad con cantidades distintas, overrides preservados, recibos y auditoría con hash. Cinco rutas adicionales de datasets/herramientas/SQL denegadas por HTTP. Las regresiones de cuentas, planificación, listas y ledger siguen pasando.

Reconstrucción determinista del JSON y preservación byte por byte del CSV comprobadas; sintaxis PHP/JS/Python. Lecturas de fuentes mediante Google Drive con metadata previa y rangos acotados; no se alteró ninguna hoja. La revisión visual de nuevos controles sigue pendiente por la limitación de navegador ya documentada; no se afirma un recorrido visual realizado.

## Uso y rollback

Administrador: Facilities y recetas → revisar Catálogo AFEC + comunidad → Cargar catálogo inicial → confirmación → Guardar. La demo ya contiene las 71 fuentes y no ofrece altas repetidas. Operador: Agregar facility → seleccionar la referencia o ejemplo → revisar → Guardar; cantidades independientes después. Se muestran Observación AFEC, Referencia comunitaria o Plantilla manual, y Ajustada localmente cuando corresponde.

CLI simula por defecto: `php tools/seed-recipes.php --user=ADMIN`. Aplicar con `--apply`; producción exige además `--allow-production`, backup y autorización específica. La carga web usa únicamente el snapshot empaquetado, nunca un archivo o URL suministrado por el cliente.

Punto anterior de código: `a1fdab8`. Revertir lógica conservando 006, su registro/checksum, tablas/columnas, configuración y datos. No ejecutar un migrador antiguo que desconozca 006. Las fuentes cargadas siguen siendo recetas normales con sus notas; no borrar datos para repetir. Restauración de backup solo en una copia aislada revisada, conciliando cualquier dato posterior. La importación completa de Sheets/Apps Script y la clasificación de estructuras desconocidas continúan pendientes.
