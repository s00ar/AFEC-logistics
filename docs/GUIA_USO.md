# Uso de la versión actual

Actualizado: 14/10/2026. Corresponde a las fases 1–3 y la demo local; no implica que exista un sitio o una cuenta instalada en Hostinger.

## Ingresar a la demo

1. Abrir `http://127.0.0.1:8077/?page=login` en este PC, con los servicios locales activos.
2. Usar `admin` para administración, `operator` para planificación/entregas de terceros o `viewer` para el rol Comandante: entregas propias, listas y abastecimiento.
3. La contraseña sintética de estas cuentas está en `.local/preview-password.txt`, ignorado por Git. Copiar el contenido sin el salto de línea y pulsar **Iniciar sesión**. No usar la contraseña MySQL para entrar a la aplicación.
4. Si ya se inició sesión, pulsar **Salir** para cambiar de cuenta. La consulta pública no muestra botones de escritura.

Las tres cuentas y su contraseña son exclusivamente de la demo. Una instalación de producción requiere crear su propio primer administrador; no incluye cuentas predeterminadas.

## Usuarios

Ingresar como administrador y abrir **Usuarios**:

1. **Crear:** **+ Crear usuario** → usuario, nombre visible, rol, contraseña inicial y motivo → **Guardar**. Se crea también su identidad de comandante. La contraseña admite entre 12 y 72 bytes; utilizar una contraseña distinta para cada cuenta real y compartirla por el canal acordado.
2. **Consultar:** la tabla muestra usuario, nombre, rol y estado; usar el paginador si hay más de 25 cuentas.
3. **Modificar:** **Editar usuario** → cambiar nombre/usuario/rol y, si corresponde, **Cuenta activa** → motivo → guardar. Los cambios revocan las sesiones existentes de esa cuenta.
4. **Eliminar:** **Eliminar usuario** → motivo → confirmar baja → guardar. Se revoca el acceso y se conservan aportes, historial y lista personal. El nombre de usuario sigue reservado.
5. **Restaurar:** localizar la cuenta eliminada → **Restaurar usuario** → motivo → guardar. Recupera acceso y sus datos.
6. **Contraseña:** **Restablecer contraseña** → contraseña nueva y motivo → guardar. Se revocan las sesiones y la contraseña anterior deja de funcionar. Comunicar la nueva contraseña al titular.

Desactivar con **Cuenta activa** permite una suspensión reversible sin marcarla eliminada. No se permite eliminar, desactivar o quitar el rol al último administrador activo; otro administrador debe cambiar tu propio rol o desactivar tu cuenta. Si editás tu propia cuenta o contraseña, iniciá sesión otra vez.

Los roles incorporados son Administrador, Operador y Comandante (identificador técnico `viewer`, conservado por compatibilidad). Se asignan desde el formulario de usuario; no se crean roles personalizados ni existe registro público automático.

`tools/create-admin.php` sigue reservado al primer administrador de una instalación vacía. Las cuentas posteriores se gestionan desde la web; no editar hashes manualmente en MySQL.

## Sistemas

- **Crear:** en **Sistemas**, pulsar **+ Crear sistema**; completar nombre, descripción opcional y motivo opcional; pulsar **Guardar**. Se abre el sistema creado.
- **Consultar:** pulsar el nombre del sistema. Se muestran sus instalaciones, materiales y progreso.
- **Modificar:** pulsar **Editar** en la tarjeta o **Editar sistema** dentro del sistema; cambiar los datos, completar el motivo y guardar. No se cambian sus IDs ni las instalaciones vinculadas.
- **Eliminar de la planificación activa:** pulsar **Archivar** o **Archivar sistema**, escribir el motivo, marcar la confirmación y guardar. Se conserva todo el historial; no hay borrado físico.
- **Restaurar:** en **Sistemas**, pulsar **Mostrar también archivados**, localizar el sistema y pulsar **Restaurar**. Escribir motivo y guardar. Las instalaciones que ya estaban archivadas conservan su estado propio.
- **Auditar:** dentro del sistema, pulsar **Ver cambios del sistema**.

## Instalaciones, bases orbitales y settlements

Se administran como instalaciones dentro de un sistema. El campo **Tipo** es texto libre en esta versión; todavía no hay catálogo de tipos ni recetas automáticas.

- **Crear:** abrir un sistema activo → **+ Agregar instalación** → completar nombre y, cuando corresponda, cuerpo, ubicación, tier, tipo y orden visual → **Guardar**. El nombre y el orden son obligatorios; la pantalla propone orden 0 y comienza En construcción.
- **Consultar:** abrir el sistema; cada instalación tiene su panel de materiales.
- **Modificar:** **Editar instalación** → cambiar datos → escribir motivo → **Guardar**. No se traslada a otro sistema con este formulario.
- **Eliminar de la planificación activa:** **Archivar instalación** → motivo → confirmar archivo → **Guardar**.
- **Restaurar:** **Mostrar archivadas y retirados** → **Restaurar instalación** → motivo → guardar. El sistema padre debe estar activo; restaurarlo primero si corresponde.
- **Auditar:** pulsar **Ver cambios** en los controles de la instalación.

**Construcción:** operadores/administradores pueden pulsar **Marcar terminada**, indicar motivo y confirmar aunque queden faltantes. El distintivo pasa a **Terminada** y queda fuera de ambas listas de deseos. El registro de entregas y el porcentaje real no se alteran. Para volver a recibir entregas, usar **Reabrir construcción** y un motivo.

Las instalaciones anteriores a esta fase conservan su estado libre original y aparecen **Sin clasificar** hasta que un operador elija **Marcar en construcción** o **Marcar terminada**; no se infiere automáticamente su estado real. Archivar y terminar son acciones distintas.

## Materiales y objetivos

1. En una instalación activa, pulsar **+ Agregar objetivo**.
2. Elegir material, ingresar cantidad requerida como entero sin separadores y completar motivo; guardar.
3. Para cambiar la cantidad, usar **Editar objetivo**, ingresar nueva cantidad y motivo; guardar. No se cambian las entregas históricas.
4. Para retirarlo, usar **Retirar objetivo**, motivo y confirmación; guardar.
5. Para recuperarlo, usar **Mostrar archivadas y retirados** → **Restaurar objetivo** → revisar cantidad y motivo → guardar.
6. **Ver cambios** en la fila del material muestra las revisiones del objetivo.

Si falta un material en el catálogo, un administrador puede crearlo desde **Sistemas → Agregar material al catálogo**. Los operadores pueden usar el catálogo existente; no dan de alta materiales de catálogo en esta versión.

## Entregas y corrección de cantidades

1. Abrir una instalación no archivada ni terminada; pulsar **Registrar entrega** en la fila de la mercancía.
2. Ingresar cantidad recibida. Un Comandante registra la entrega con su propia identidad. Operadores/administradores pueden elegir otro comandante o ingresar uno nuevo; no combinar selección y nombre nuevo.
3. Indicar carrier si corresponde, seleccionando uno existente o ingresando nombre/código nuevos. La fecha es opcional: sin ella se usa la fecha del registro. Una fecha ingresada se interpreta en la zona local del navegador y se guarda en UTC.
4. Guardar. Se conservan por separado quién entregó y quién registró. Se admiten cantidades mayores al objetivo; se muestran como sobrantes y no compensan otro destino/material.
5. Para corregir una carga de más, abrir **Historial de entregas** y pulsar **Retirar / corregir** en el movimiento original. Ingresar cantidad parcial o completa, motivo y confirmación; guardar.

La corrección agrega un movimiento negativo vinculado al original: no lo modifica ni lo borra. Puede haber varios retiros parciales, hasta el saldo que conserva esa entrega y sin dejar negativo el saldo de la instalación/material. Un Comandante corrige sus entregas (incluidas las registradas en su nombre); operadores/administradores corrigen las de terceros. Se pueden corregir registros de instalaciones terminadas/archivadas desde su historial, sin reabrirlas ni dar por entregado un faltante.

Para corregir un destino o material equivocado: retirar la cantidad del registro original y registrar una nueva entrega en el destino/material correcto. Son dos operaciones explícitas y auditadas, no un traslado automático.

## Listas personal y de flota

1. En el panel de una instalación, elegir **Añadir a mi lista** o **Añadir a la flota** y guardar. Puede estar en ambas listas; no se duplica dentro de una misma lista.
2. Abrir **Mi lista** o **Flota** en la navegación. La personal solo corresponde a tu cuenta; la flota la consultan y modifican todos los usuarios autenticados.
3. Cada mercancía muestra el total pendiente y los destinos: sistema, instalación y cantidad. **Más destinos** y los paginadores permiten recorrer listas grandes.
4. Los faltantes se recalculan al consultar/actualizar la página, según objetivos y movimientos actuales. Entregas y correcciones guardadas recargan su página. No hay actualización en segundo plano entre navegadores.
5. En **Instalaciones seleccionadas**, usar **Quitar de esta lista**, motivo y guardar. Esto no archiva la instalación ni retira sus objetivos.

Una selección incluye todos los objetivos activos actuales de esa instalación, incluidos objetivos añadidos posteriormente. Se excluyen de los totales objetivos retirados, instalaciones/sistemas archivados, instalaciones terminadas y materiales sin faltantes. Una instalación terminada/archivada puede conservarse seleccionada; si se reabre/restaura, vuelve a aportar sus faltantes sin duplicarse. Quitarla explícitamente requiere agregarla otra vez si se desea recuperarla.

Todos los usuarios autenticados pueden agregar/quitar selecciones de la flota y de su propia lista; ninguna cuenta puede seleccionar o modificar la lista personal de otro usuario mediante la aplicación.

## Dónde conseguir una mercancía

1. Pulsar **＋** junto al material para registrar un lugar, o **🌐** si ya existen lugares activos. También aparece en las listas de deseos.
2. En el popup, **+ Agregar lugar** → sistema, nombre exacto de estación/settlement, tipo, notas opcionales y motivo → guardar.
3. La ubicación queda compartida para esa mercancía con todos los usuarios autenticados. La próxima consulta de cualquier comandante muestra **🌐** si hay lugares activos.
4. **Copiar sistema** y **Copiar estación/settlement** copian solo el nombre elegido, para pegarlo en el juego. Si el navegador bloquea el portapapeles, el campo queda seleccionado para copiarlo manualmente.
5. **Editar lugar** corrige la información. **Retirar lugar**, motivo y confirmación lo ocultan del abastecimiento activo cuando ya no ofrece esa mercancía.
6. Marcar **Mostrar lugares retirados** para revisar/restaurar un lugar que vuelva a ofrecerla. Cualquier usuario autenticado puede gestionar estos registros; se conserva autoría y auditoría.

El popup muestra fecha de actualización y autor. Son datos aportados por comandantes: no se afirma stock/precio en tiempo real ni se consulta un servicio externo. Se admite más de un lugar por mercancía; un mismo sistema/lugar no se duplica aunque esté retirado.

## Identidad visual

El logo proporcionado se utiliza en cabecera, acceso y favicon, conservando el archivo original.

## Conflictos y reintentos

Si otro operador cambió el registro mientras el formulario estaba abierto, se pide actualizar los datos antes de guardar de nuevo. Si falla la conexión y el resultado es incierto, usar **Comprobar y reintentar**: se conserva la operación para confirmar el mismo guardado sin duplicarlo. No reemplazarla por otra carga idéntica.

Las contraseñas nuevas no se guardan en el almacenamiento de recuperación del navegador. Después de recargar, se consulta primero el resultado de la operación; si no existe un resultado confirmado, se pide reingresar la contraseña para reintentar con la misma clave. Si cambiaste tu propia cuenta y perdiste la respuesta, iniciá sesión con los datos nuevos y comprobá el resultado antes de repetir la operación.

## Funcionalidades todavía pendientes

Importación de Sheets, recetas de materiales verificadas por tipo/tier, reservas/cargas en tránsito y un módulo completo de edición/retiro de comandantes y carriers. Estos últimos pueden seleccionarse o darse de alta al registrar una entrega; no cuentan todavía con pantalla administrativa completa. No hay integración de stock externo, registro público de cuentas ni roles personalizados. El despliegue y la inicialización reales en Hostinger siguen pendientes.
