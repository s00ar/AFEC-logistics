# Modelo relacional (propuesta inicial)

Entidades:
- `users`: cuenta, nombre de comandante, rol, estado.
- `systems`: sistema con nombre y metadatos; único por clave de negocio si existe.
- `installations`: instalación perteneciente a sistema, cuerpo, localización, tier, estado y orden visual.
- `commodities`: catálogo de mercancías con código único y nombre(s).
- `requirements`: requerimiento material (instalación + commodity) y cantidad objetivo.
- `movements`: ledger de cargas / reversiones / ajustes. Registra instalación, mercancía, usuario, unidades delta firmadas, tipo, motivo y clave idempotente.

Reglas:
- No almacenar como autoridad una columna `restante` editable. Calcular objetivo - SUM(movements.delta) para cada pareja instalación/material.
- `delta` entero positivo al entregar, negativo al revertir; validaciones para ajustes y no permitir saldos negativos sin autorización expresa.
- Movimientos no se borran; las correcciones son filas nuevas enlazadas a movimiento original (`reverses_movement_id`).
- El requerimiento se puede cambiar con historial de auditoría; recomendar tabla de revisiones si el negocio requiere trazabilidad fina.
- Instalaciones pueden archivarse, pero no borrar físicamente si hay movimientos.
- Usar claves internas (IDs) y constraints; nunca posición de celda ni nombre como identificador persistente.
- Los totales son derivados por SQL. Concurrencia con transacción y `SELECT ... FOR UPDATE` al validar reglas de saldo.

**Advertencia**: comparar el esquema con el archivo Google Apps Script y hojas reales antes de darlo por definitivo. Las columnas y relaciones del esquema son un diseño propuesto, no el modelo validado del sistema actual.
