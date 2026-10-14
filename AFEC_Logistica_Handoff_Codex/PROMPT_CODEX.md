# Prompt para entregar a Codex

Trabajá sobre este handoff para construir la nueva **AFEC Logística**, reemplazo de Google Sheets. Primero leé TODO el README, AGENTS.md y docs. **No comiences creando código sin inventariar los archivos existentes**. Identificá los archivos externos que faltan (`Code.gs`, HTML Apps Script, export actual de Sheets y fuentes de Terminal AFEC) y solicitá su incorporación antes de migrar datos o afirmar compatibilidad.

La decisión preferida es **monolito PHP + MySQL/MariaDB + HTML/CSS + JS vanilla**, apto para Hostinger compartido, sin React, Firebase ni Supabase salvo justificación explícita y aprobación. Presentá un plan por fases con tiempos relativos, riesgos y rollback. Proponé modelo definitivo a partir de datos reales, luego implementá MVP en carpeta nueva, con seguridad básica, roles, transacciones, historial auditable, migración dry-run e idempotente y pruebas reproducibles.

Prioridad principal: que añadir sistemas e instalaciones y que el botón **Agregar cantidad** funcione para cualquier instalación nueva, manteniendo saldos y atribución por comandante. No toques producción ni planilla vigente sin consentimiento. Reportá claramente lo verificado, lo inferido y lo pendiente de confirmar.
