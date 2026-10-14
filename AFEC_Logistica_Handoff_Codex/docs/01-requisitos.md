# Requisitos funcionales

## Núcleo MVP
1. Listar sistemas de colonización, con creación por admin/operador autorizado.
2. Agregar instalaciones a sistemas existentes, indicando nombre, cuerpo/ubicación, tier, estado y materiales requeridos; permitir editar y archivar instalaciones (permisos específicos por definir).
3. Crear catálogo de mercaderías con código/nombre y alias/idioma opcional; cantidades de requerimiento **por instalación y mercadería**.
4. Registrar **cantidades entregadas** por comandante, instalación, mercadería y fecha. Debe permitir aportes de varios comandantes a la misma instalación.
5. Pantalla de progreso: requerida, entregada, restante (máx. 0 para visualización; registrar sobreentrega separadamente), porcentaje por material/instalación/sistema.
6. Historial trazable de movimientos (autor, cantidad, timestamp, tipo, identificador de operación); correcciones mediante ajustes compensatorios con motivo.
7. Panel de administración: gestionar sistemas, instalaciones, usuarios/roles y catálogo sin tocar SQL manualmente.
8. Lectura accesible a comandantes; solo operadores autorizados realizan altas y cargas. El alcance exacto de lectura pública vs login se debe confirmar.
9. Interfaz en español, responsive para escritorio y móvil, feedback de errores, estados de carga, confirmación en acciones críticas.
10. Export CSV, al menos del historial y del progreso, en un MVP ampliado si conviene separarlo.

## Casos de borde obligatorios
- Una instalación nueva debe permitir cargar mercaderías inmediatamente (no repetir fallo de botón «Agregar cantidad»).
- Varias instalaciones pueden exigir el mismo material sin mezclar sus saldos.
- Cantidad positiva, en unidades enteras para mercaderías salvo prueba contraria.
- Evitar duplicar un aporte por doble clic, retry de red o recarga del navegador.
- Al cambiar requisitos o archivar una instalación con movimientos previos, conservar historial y recalcular de modo consistente.
- Nombres de comandantes sin duplicación accidental por instalación/fila.
- Registro consistente independientemente de posición de fila: abandonar referencias por celdas o filas.

## Fuera de alcance inicial salvo aprobación
- Integración con Frontier/Inara, sincronización en tiempo real, chat, notificaciones push, SSO, pagos, contabilidad de inventario en carriers, cálculos de Construction Points.
- Importación automática de nuevas recetas de instalaciones desde fuentes externas.
