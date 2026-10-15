# Memoria de proyecto — AFEC Logística

> Índice de contexto para ChatGPT, Codex y otras herramientas. **No es la fuente de verdad**: contrastar siempre con los archivos enlazados y el código de la rama actual.
>
> Inicializado: 2026-10-15. Basado en README.md y AGENTS.md de `main`. Este índice **no valida** por sí mismo el estado de producción.

## Identidad y stack
- Proyecto: AFEC Logística, aplicación de colonización de Elite Dangerous.
- Repositorio: `s00ar/AFEC-logistics`.
- Stack verificado en documentación: PHP + PDO MySQL/MariaDB, HTML/CSS/JavaScript vanilla; sin build ni Node en producción.
- Despliegue documentado: Hostinger, https://logistica.afec.space/ (estado en vivo no verificado en esta inicialización).

## Cómo recuperar contexto (lectura progresiva)
1. Leer este índice; identificar el tema de la consulta.
2. Leer `AGENTS.md` para normas obligatorias y `README.md` para el estado general.
3. Consultar **solo** la fuente del tema relevante:
   - Decisiones y políticas: `docs/DECISIONES.md`.
   - Alcance/pruebas por fase: `docs/FASE_1.md` … `docs/FASE_5.md`.
   - Instalación y rollback: `docs/DESPLIEGUE.md`, `docs/FASE_RAIZ_HOSTINGER.md`.
   - Uso: `docs/GUIA_USO.md`.
   - Correcciones de roles: `docs/CORRECCION_ROLES.md`.
   - CRUD de mercancías y migración pendiente: `docs/CRUD_MERCANCIAS.md`.
   - Catálogo y procedencia de recetas: `resources/catalogue/SOURCES.md`.
   - Diagnóstico original: `ANALISIS_AFEC_LOGISTICA.md`; handoff original: `AFEC_Logistica_Handoff_Codex/`.
4. Ante discrepancias, priorizar código + pruebas reproducibles + decisiones aprobadas; indicar fecha y procedencia. No inferir implementación o despliegue desde un resumen.
5. Al finalizar un cambio confirmado, actualizar aquí únicamente el índice/estado pertinente y enlazar commit, prueba o documento. No guardar conversaciones completas, claves, tokens ni datos personales.

## Estado resumido (según README.md consultado el 2026-10-15)
- Documentación reporta fases 1–5 implementadas y verificadas localmente.
- README informa comprobaciones HTTP 200 en panel/API pública el 2026-10-14, **no verificadas nuevamente aquí**.
- Pendientes documentados: importación completa desde Sheets/Apps Script; clasificación de estructuras/tier desconocidos; reservas y cargas en tránsito; administración completa de comandantes/carriers.
- README indica que el CRUD de mercancías requiere la migración SQL 007 en phpMyAdmin; no asumir que fue aplicada.

## Reglas de seguridad y de cambios
- Seguir `AGENTS.md`: validar servidor, autorizaciones y CSRF; movimientos append-only y correcciones compensatorias.
- No agregar credenciales al repositorio, ni tocar Sheets, producción o base real sin autorización explícita.
- Para cada fase detallar alcance, pruebas, riesgos y rollback. No declarar éxito sin evidencia.

## Mantenimiento
- Este archivo es un **índice pequeño**, no un historial exhaustivo.
- Cuando cambie el proyecto, revisar si las afirmaciones anteriores siguen vigentes y corregirlas citando el archivo/commit.
- Para consultas nuevas, recuperar solo las secciones necesarias en lugar de cargar todo el repositorio.
