# Migración de la Google Sheet sin pérdida de datos

## Insumos a recopilar
1. Export de la planilla de Google actual (XLSX o CSV por hoja). URL en README.
2. Código `Code.gs` actual y HTML del proyecto Apps Script (`Sidebar`, `Builder`, `Manager`, `Admin`, `Help` y demás archivos disponibles).
3. Código de la Terminal AFEC como referencia arquitectónica.
4. Muestras anonimizadas de hojas de varios sistemas, instalaciones, materiales y movimientos; respaldos de historial.

## Procedimiento
1. Realizar copia de seguridad completa de Sheets y del Apps Script, registrar versión/fecha.
2. Inventariar sheets, encabezados, rangos reales, hojas archivadas, historial, nombres de comandante, fórmulas y metadatos.
3. Mapear identificadores antiguos -> PK nuevos; reportar filas imposibles de mapear; no completar con información inventada.
4. Importación **dry-run**: detectar duplicados/filas inconsistentes/fechas ambiguas y mostrar informe antes de escribir.
5. Importar en staging mediante transacción o lotes auditables con claves de importación únicas e idempotentes.
6. Verificar conteos, suma total entregada y saldos por sistema/instalación/material contra snapshot de origen; diferencias deben quedar documentadas y resueltas.
7. Pruebas manuales y automatizadas; aprobación; ventana de corte con freeze de escritura en Sheets, export final, import delta y habilitación de nueva app.
8. Conservar Sheets original solo lectura hasta período de verificación acordado.

## Rollback
- Si fallan conciliaciones o funcionalidades, detener escrituras en nueva app y volver temporalmente a Sheets tras revisar movimientos hechos durante el corte; **no asumir rollback automático bidireccional**.
- Respaldar MySQL antes de cambios y migraciones de esquema.
- Evitar doble escritura permanente entre ambas plataformas.
