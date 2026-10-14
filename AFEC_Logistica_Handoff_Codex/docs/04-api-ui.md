# Contrato propuesto de UI/API

## Vistas
- Inicio: sistemas, % global y últimas cargas.
- Sistema: instalaciones y progreso.
- Instalación: tabla material/requerido/entregado/restante; botón **Agregar cantidad** por material con formulario que elija comandante/identidad, cantidad y confirmación.
- Historial: filtros por sistema, instalación, comandante, material y período.
- Administración: sistemas, instalaciones, usuarios, materiales y requerimientos.

## Rutas orientativas
- `GET /api/session`, `POST /api/login`, `POST /api/logout`
- `GET /api/systems`, `POST /api/systems`, `PATCH /api/systems/:id`
- `GET /api/systems/:id/installations`, `POST /api/systems/:id/installations`
- `GET /api/installations/:id`, `PATCH /api/installations/:id`
- `GET /api/installations/:id/requirements`, `PUT /api/installations/:id/requirements/:commodityId`
- `GET /api/commodities`
- `GET /api/movements`, `POST /api/movements`, `POST /api/movements/:id/reverse`
- `GET /api/reports/progress`, `GET /api/reports/movements.csv`

Propuesta POST movimiento:
```json
{"installation_id":12,"commodity_id":4,"quantity":75,"idempotency_key":"UUID-generado-cliente","note":"Entrega desde carrier"}
```
La identidad `user_id` proviene de la sesión, no del JSON del cliente. Respuesta incluye identificador de movimiento y saldos recalculados. Usar códigos HTTP 400/401/403/404/409/422/500 según corresponda, errores JSON normalizados, evitar HTML en respuestas de API.

Flujo **Agregar cantidad**: carga inicial de requerimientos -> seleccionar material -> abrir modal -> validar cantidad -> enviar POST -> bloqueo contra doble submit -> persistir en transacción -> recargar material/progreso/historial -> mensaje de éxito o error; si falla, no simular éxito.
