# AFEC Logística — handoff para Codex

**Estado:** especificación de migración, NO aplicación implementada.

## Objetivo
Reemplazar el planificador de colonización de Google Sheets/Apps Script por una aplicación web monolítica ligera, desplegable en hosting compartido compatible con PHP y MySQL/MariaDB (Hostinger, sujeto a confirmar plan). Mantener el registro colaborativo de entregas de mercancías, sistemas, instalaciones, objetivos y auditoría.

## Decisiones de la conversación
- Prioridad: **mínima complejidad operativa** y facilidad de despliegue.
- Arquitectura recomendada: **PHP + MySQL/MariaDB + HTML/CSS + JavaScript vanilla**, en un único deploy.
- React + Vite, Supabase y Firebase se evaluaron, pero **no son obligatorios**. No introducirlos sin evaluar el coste real y consultar al usuario.
- El frontend puede ser HTML con JavaScript para lógica de presentación/interactividad; el acceso a DB ocurre exclusivamente en PHP, nunca directamente desde JS del navegador.
- Acceso de lectura para comandantes; **solo usuarios autorizados deben modificar datos**. Definir administradores y operadores; permisos definitivos por validar.
- Seguridad proporcionada al riesgo: sesiones, contraseñas hasheadas, consultas preparadas, autorización backend, CSRF y backup. No omitir controles básicos por tratarse de bajo riesgo.
- Buscar y revisar el código real de la **Terminal AFEC** antes de imitar o reaprovechar su patrón: el código de esa aplicación **no está adjunto en este paquete ni fue verificado**.

## Fuentes localizadas
- Planificador Google Sheets: https://docs.google.com/spreadsheets/d/14RxFS9FSc12ybmMuO95vFh8S9An0D4tdgDdEqT6ydEg/edit
- Archivo `Code.gs` recuperable en la Biblioteca del proyecto (48 KB; versión disponible 12/10/2026). Este ZIP **no contiene el código de origen**.
- Se identificó la existencia de `AFEC_Logistics_Sidebar.html` en Biblioteca, con interfaz para cargas logísticas. Tampoco está adjunto aquí.
- Se observaron en el código del planificador referencias a formularios `Sidebar`, `Help`, `Admin`, `Manager`, `Builder`, funciones para crear sistemas e instalaciones y metadatos por hoja. El análisis del proyecto completo queda pendiente.

## Flujo de trabajo para Codex
1. **Inventario**: pedir los archivos `.gs`, `.html`, exportaciones de Sheets y el código de la Terminal AFEC. Inspeccionar código y datos reales; identificar funciones y errores vigentes antes de escribir reemplazos.
2. Validar alcance y permisos pendientes con el usuario (docs/07-preguntas-abiertas.md).
3. Implementar una versión mínima de la nueva aplicación en un directorio nuevo, sin modificar la planilla en uso.
4. Implementar migración reproducible con simulación (`dry-run`) y reporte de importación; evitar duplicados.
5. Ejecutar pruebas funcionales y de concurrencia, hacer backup, desplegar en staging y obtener aprobación del usuario antes del cambio de producción.
6. Mantener Sheets como fuente anterior de consulta/rollback hasta completar validación.

## Archivos
- `AGENTS.md`: reglas permanentes de ejecución para Codex.
- `docs/01-requisitos.md`: alcance funcional.
- `docs/02-arquitectura.md`: arquitectura y hosting.
- `docs/03-modelo-datos.md`: entidades e invariantes.
- `docs/04-api-ui.md`: interfaz y endpoints propuestos.
- `docs/05-migracion.md`: migración y rollback.
- `docs/06-pruebas.md`: criterios de aceptación.
- `docs/07-preguntas-abiertas.md`: puntos por confirmar.
- `docs/08-contexto-actual.md`: problemas observados y procedencia.
- `db/schema_propuesto.sql`: esquema **inicial para revisar**, no ejecutar en producción sin adaptación.
- `PROMPT_CODEX.md`: mensaje de inicio para Codex.

No hay secretos ni credenciales en este paquete.
