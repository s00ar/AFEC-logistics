# Casos de prueba / criterios de aceptación

1. Admin crea sistema, agrega instalación y asigna 2 materiales con cantidades objetivo. Se muestran sin recargar manualmente.
2. Operador registra 10 unidades; el historial muestra usuario y hora, entregadas +10, restantes -10.
3. Operador A y B cargan simultáneamente en mismo material: ambas aportaciones se conservan sin pérdida; totales correctos.
4. Doble clic / retry con la misma `idempotency_key` no duplica movimientos.
5. Nueva instalación permite **Agregar cantidad** inmediatamente.
6. Usuario `viewer` no puede mutar llamando directamente API; recibe 403.
7. Usuario no autenticado no puede mutar; recibe 401/403 según contrato.
8. Registrar cero, negativo incorrecto, decimal y cantidad excesiva: validación definida y coherente (reglas de sobreentrega a confirmar).
9. Reversión crea compensación; historial original permanece.
10. Archivar instalación no borra sus movimientos ni distorsiona totales históricos.
11. Dos instalaciones con mismo commodity mantienen saldos independientes.
12. Revisar responsive y feedback ante HTTP 500/timeouts.
13. Prueba de backup + restauración MySQL en ambiente aislado.
14. Migración repetida sobre el mismo dataset no duplica movimientos y concilia sumas.
15. Verificar protección ante SQLi, XSS reflejado/almacenado, CSRF y acceso a ficheros privados.

**No declarar listo para producción** hasta verificar datos reales y pruebas críticas anteriores.
