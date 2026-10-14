# Ampliación solicitada — propuesta pendiente de decisiones

Fecha: 14/10/2026. Este documento registra requisitos y propuesta; no declara implementadas las funciones nuevas ni autoriza despliegues o migraciones reales.

## Requisitos recibidos

- Permitir marcar instalaciones, bases orbitales, settlements y demás instalaciones como **En construcción** o **Terminada**.
- Botón por instalación para agregar sus materiales a una lista de deseos generalizada.
- Totales agrupados por mercancía, con desglose por sistema/instalación y cantidad que se debe entregar en cada destino.
- Registrar dónde se obtiene cada mercancía: sistema y estación/settlement. Guardarlo en MySQL y reutilizarlo para cualquier comandante.
- Icono **🌐** cuando existan lugares registrados; modal con ubicaciones y botones para copiar los nombres exactos al portapapeles.
- Usar el logo entregado para AFEC Logística. Original conservado sin transformación en `resources/branding/afec-logistica.png`; todavía no está integrado a la interfaz.
- Explicar el acceso y CRUD actuales e incorporar la gestión de usuarios que falta.

## Decisiones necesarias

1. **Alcance de la lista:** única compartida por AFEC o personal por comandante. Propuesta: una lista compartida con selección de instalaciones y lectura pública; escrituras autenticadas.
2. **Cantidades y actualización:** objetivos totales o faltantes recalculados al registrar entregas/cambiar objetivos. Propuesta: faltantes actuales por destino, sumados por mercancía; no sumar una instalación dos veces. Retiradas, archivadas y terminadas quedan fuera de la lista activa sin borrar su historia.
3. **Cierre de construcción:** permitir marcar Terminada con materiales pendientes, o exigir objetivos cubiertos. El cambio de estado será manual según el pedido; no equiparar automáticamente 100% logístico con construcción terminada sin una regla acordada. Propuesta: advertencia y motivo si se permite cerrar con faltantes; reapertura auditada.
4. **Permisos:** quién agrega/quita instalaciones de la lista y quién crea/corrige/retira ubicaciones de abastecimiento. Propuesta inicial: operadores/admin gestionan; los datos registrados se comparten. Confirmar si todos los comandantes con cuenta deben poder contribuir a estos dos flujos.

La gestión de cuentas se propone exclusivamente para administradores, sin registro público automático. Si se desean solicitudes de acceso, deben definirse aparte. Se conserva la regla confirmada de que visitantes públicos nunca modifican información.

## Comportamiento propuesto

Una mercancía ocupa una fila en la lista y despliega sus destinos. Ejemplo sintético: Agua 1500, con 500 para Puerto A y 1000 para Settlement B. Cada destino mantiene sus requerimientos y entregas propios; un sobrante en A no cubre automáticamente un faltante en B.

El botón de añadir incluye una instalación una sola vez en la lista; pulsarlo de nuevo indica que ya está incluida. Quitarla de la lista no archiva la instalación ni borra objetivos. Las cantidades se consultan desde planificación y movimientos; no se copia un snapshot que quede desactualizado.

Los puntos de abastecimiento pertenecen a la mercancía, independientemente de la lista o comandante que los registró. Puede haber varios puntos. El modal muestra sistema y estación/settlement; cada nombre tendrá su botón **Copiar** separado para pegarlo en el juego. Solo se copian nombres, sin adornos o texto adicional.

Propuesta adicional para abastecimiento: autor, última fecha de verificación, notas y estado vigente/no confirmado/retirado. Una ubicación registrada es información aportada por usuarios; no implica stock o precio en tiempo real. No se presupone integración externa.

## Fases pequeñas propuestas

1. **Cuentas:** alta/edición de usuario y rol, desactivar/reactivar y restablecimiento de acceso; auditoría, revocación de sesiones y protección del último administrador. Herramienta privada de primer administrador permanece separada.
2. **Estados y marca:** selector/badge de estado manual, transiciones con motivo y uso del logo en cabecera/login. Revisar valores de estado libres existentes; no reemplazarlos automáticamente sin mapeo aprobado. Incorporar recursos gráficos a la lista pública permitida del despliegue raíz.
3. **Entregas:** cerrar políticas pendientes y registrar/revertir/corregir entregas transaccionales, con comandante y usuario registrador separados. Necesario para que los faltantes se actualicen con actividad real.
4. **Lista de deseos:** selección de instalaciones, agrupación, desglose de destinos, actualización de faltantes y quitar/restaurar selección según el alcance acordado.
5. **Abastecimiento:** múltiples lugares por mercancía, altas/ediciones auditadas, indicador 🌐, modal y copia individual de sistema/destino.

El detalle de esquema, archivos, pruebas y rollback de cada hito se presenta antes de implementarlo. Toda migración nueva se añade con versión posterior; no se modifica `001_initial.sql`. Las pruebas usan bases locales aisladas y luego los dos layouts de servidor. No se ejecuta nada sobre Hostinger/Sheets por registrar esta propuesta.

## Funciones adicionales que conviene priorizar

- Asignación/reserva de cargas para reducir compras duplicadas entre comandantes, con cantidades en tránsito separadas de entregas recibidas.
- Caducidad o confirmación de ubicaciones de abastecimiento y corrección compartida sin perder autoría.
- Búsqueda y filtros por sistema, tipo, estado, prioridad y mercancía; exportación de la lista a texto/CSV.
- Carga de objetivos en lote con revisión previa, antes de considerar recetas por tipo/tier basadas en una fuente verificada.
- Importación de Sheets con simulación, conciliación, identificadores de origen y control de duplicados; backups y restauración probada para el despliegue real.

No convertir estas sugerencias en requisitos aprobados; son propuestas para priorizar después de las cuatro decisiones anteriores.
