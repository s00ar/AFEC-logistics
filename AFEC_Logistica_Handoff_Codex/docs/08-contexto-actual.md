# Contexto de herramienta Sheets (evidencia parcial)

Se localizó en Biblioteca un `Code.gs` fechado 12/10/2026. Su vista preliminar incluye `showAFECManager()`, `showCreateSystemWizard()`, `showAppendInstallationWizard()`, `getAFECVersion()`, metadatos por sheet (`getSheetMeta_`, `setSheetMeta_`, `getOrCreateSystemKey_`) y detección de sistemas. Además usa formularios HTML `Manager`, `Builder`, `Sidebar` y otros. **No se ha ejecutado ni revisado el proyecto íntegro**.

Problemas comunicados por el usuario en el proyecto: errores al agregar instalaciones; nuevas instalaciones creadas donde no funciona `Agregar cantidad`; algunas filas aceptan carga y otras no; rojo por fila al completar inoperante; conflicto por protecciones; duplicación de nombres o filas; errores en fila del historial. Se debe confirmar cada uno contra código/logs, **sin asumir causa raíz**.

La hoja ubicada en Drive es `Planificador_Avanzado_Colonizacion_AFEC`, enlazada en README. La revisión del código de la Terminal AFEC sigue pendiente de obtener el repositorio/fuentes reales; comentarios conversacionales sobre que usa PHP/HTML/JS no equivalen a auditoría del código.
