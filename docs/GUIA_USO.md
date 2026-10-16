# Uso de la versión actual

Actualizado: 15/10/2026. Corresponde a las fases 1–5. El propietario indicó que actualizar `main` en GitHub activa el despliegue automático en Hostinger; el estado de publicación y la configuración del servidor se verifican por separado.

## Ingresar al sitio de Hostinger

Abrir [AFEC Logística](https://logistica.afec.space/) para consultar el progreso público. Para escribir, entrar desde **Ingresar** o abrir [Iniciar sesión](https://logistica.afec.space/?page=login), con una cuenta creada en esa instalación.

El formulario ofrece **Mantener la sesión iniciada**, marcado por defecto. Con esta opción, el acceso se conserva al cerrar y volver a abrir el navegador durante un máximo de 30 días desde el ingreso. En un equipo compartido, desmarcarla: se mantiene la sesión corta (30 minutos sin actividad y hasta 8 horas). **Salir**, un cambio de contraseña o una revocación de la cuenta invalidan el acceso guardado. Si se borran las cookies o se usa navegación privada, será necesario volver a ingresar. El cambio requiere publicar la versión que incorpora esta opción y volver a iniciar sesión una vez; las sesiones anteriores no se convierten automáticamente.

Las cuentas de la demo local no se publican mediante Git. El primer administrador, la configuración privada de MySQL y las migraciones se preparan según [DESPLIEGUE.md](DESPLIEGUE.md). Un push publica código; no crea cuentas ni ejecuta SQL. Si el sitio devuelve 403/500 o un error de conexión, todavía no se puede dar la instalación por operativa.

Para la base vacía se entregó un SQL privado de instalación inicial con el catálogo y una cuenta `admin`. La contraseña está en `ACCESO_ADMINISTRADOR.txt` junto al SQL, fuera de Git. Importación y acceso en [IMPORTAR_PHPMYADMIN.md](IMPORTAR_PHPMYADMIN.md). Después de importar, crear las demás cuentas desde **Usuarios**. Las referencias ya vienen cargadas; no hay que precargar otra vez el catálogo.

Estado comprobado el 14/10/2026: después de cargar la configuración y el esquema, panel y API de sistemas responden 200. El propietario confirmó que puede administrar cuentas y cambiar roles. El fallo inicial de configuración y la instalación por phpMyAdmin se documentan en [DESPLIEGUE.md](DESPLIEGUE.md); los controles nuevos de identidad están en [CORRECCION_ROLES.md](CORRECCION_ROLES.md).

## Cambiar tu contraseña o pedir ayuda

- Con la sesión iniciada, pulsar **Mi cuenta** en la cabecera. Completar contraseña actual, nueva contraseña y confirmación; pulsar **Guardar nueva contraseña**. La sesión actual continúa y las otras sesiones de esa cuenta se revocan.
- Si no se puede ingresar, abrir **¿No podés ingresar? Pedí ayuda con tu contraseña** desde el login e indicar el usuario de acceso. Pedir ayuda conserva la contraseña actual y no cierra sesiones. No se necesita cargar un correo.
- Al ingresar como administrador, un aviso indica las solicitudes pendientes. Abrir **Ver solicitudes**, confirmar la identidad por el canal habitual de AFEC y pulsar **Restablecer contraseña**. Comunicar la nueva contraseña por ese canal. El restablecimiento elimina automáticamente el aviso pendiente; también se puede **Marcar como atendida**, indicando un motivo, si el usuario ya recuperó el acceso.

## Ingresar a la demo

1. Abrir `http://127.0.0.1:8077/?page=login` en este PC, con los servicios locales activos.
2. Usar `admin` para administración o `viewer` para Comandante, con toda la gestión operativa. La cuenta histórica `operator` también se reconoce como Comandante.
3. La contraseña sintética de estas cuentas está en `.local/preview-password.txt`, ignorado por Git. Copiar el contenido sin el salto de línea y pulsar **Iniciar sesión**. No usar la contraseña MySQL para entrar a la aplicación.
4. Si ya se inició sesión, pulsar **Salir** para cambiar de cuenta. La consulta pública no muestra botones de escritura.

Las tres cuentas y su contraseña son exclusivamente de la demo. Una instalación de producción requiere crear su propio primer administrador; no incluye cuentas predeterminadas.

## Usuarios

Ingresar como administrador y abrir **Usuarios**:

1. **Crear:** **+ Crear usuario** → usuario, nombre visible, rol, contraseña inicial y motivo → **Guardar**. Se crea también su identidad de comandante. La contraseña admite entre 12 y 72 bytes; utilizar una contraseña distinta para cada cuenta real y compartirla por el canal acordado.
2. **Consultar:** la tabla muestra usuario, nombre, rol y estado; usar el paginador si hay más de 25 cuentas.
3. **Cambiar privilegios:** **Cambiar rol** en la cuenta existente → seleccionar Comandante o Administrador → **Guardar**. No se pide motivo; la auditoría registra el administrador, los cambios y una descripción automática. Se conserva el usuario de acceso y su identidad de comandante. La cuenta debe volver a iniciar sesión para recibir los nuevos permisos.
4. **Modificar otros datos:** **Editar usuario** → cambiar nombre visible, rol o **Cuenta activa** → guardar. No se pide motivo. Para renombrar el acceso, marcar explícitamente **Cambiar el nombre de usuario de acceso**. El formulario conserva el usuario original cuando esa opción no está marcada, aunque el navegador intente autocompletarlo con otro nombre. Los cambios revocan las sesiones existentes de esa cuenta y mantienen su auditoría.
5. **Eliminar:** **Eliminar usuario** → motivo → confirmar baja → guardar. Se revoca el acceso y se conservan aportes, historial y lista personal. El nombre de usuario sigue reservado.
6. **Restaurar:** localizar la cuenta eliminada → **Restaurar usuario** → motivo → guardar. Recupera acceso y sus datos.
7. **Contraseña:** **Restablecer contraseña** → contraseña nueva y motivo → guardar. Se revocan las sesiones y la contraseña anterior deja de funcionar. Comunicar la nueva contraseña al titular.

Desactivar con **Cuenta activa** permite una suspensión reversible sin marcarla eliminada. No se permite eliminar, desactivar o quitar el rol al último administrador activo; otro administrador debe cambiar tu propio rol o desactivar tu cuenta. Si editás tu propia cuenta o contraseña, iniciá sesión otra vez.

Los dos roles son Administrador y Comandante (identificador técnico `viewer`, conservado por compatibilidad). Las cuentas antiguas de Operador se muestran y funcionan como Comandante sin cambiar su identidad, aportes o historial. No se puede asignar Operador a cuentas nuevas ni al editar. No requiere migración. Ver [ROLES.md](ROLES.md).

| Función | Comandante | Administrador |
| --- | --- | --- |
| Consultar sistemas, instalaciones, materiales y progreso | Sí; también público | Sí |
| Registrar y corregir entregas propias y de terceros | Sí | Sí |
| Gestionar lista propia y lista compartida de la flota | Sí | Sí |
| Gestionar lugares de abastecimiento y paladas por comandante | Sí | Sí |
| Crear, editar, archivar y restaurar sistemas, instalaciones y objetivos | Sí | Sí |
| Marcar instalaciones terminadas o reabrirlas | Sí | Sí |
| Gestionar recetas, mercancías e importar el catálogo inicial | Sí | Sí |
| Gestionar cuentas, roles, contraseñas ajenas y solicitudes de ayuda | No | Sí |
| Cambiar su propia contraseña | Sí | Sí |

Las listas personales pertenecen exclusivamente a su cuenta, incluso frente a otros comandantes y administradores. La promoción no mueve ni elimina listas, entregas o historial. El error «Ese usuario ya existe» indica un conflicto de nombre de acceso, no una prohibición de cambiar roles; no utilizar `admin` como nombre de otra cuenta.

`tools/create-admin.php` sigue reservado al primer administrador de una instalación vacía. Las cuentas posteriores se gestionan desde la web; no editar hashes manualmente en MySQL.

## Sistemas

Comandantes y administradores pueden crear, editar, archivar y restaurar sistemas, construcciones y objetivos.

- **Crear:** en **Sistemas**, pulsar **+ Crear sistema**; completar nombre, descripción opcional y motivo opcional; pulsar **Guardar**. Se abre el sistema creado.
- **Consultar:** pulsar el nombre del sistema o **Ver instalaciones**. Se muestran sus instalaciones, materiales y progreso.
- **Modificar:** pulsar **Editar** en la tarjeta o **Editar sistema** dentro del sistema; cambiar los datos y guardar (motivo opcional). No se cambian sus IDs ni las instalaciones vinculadas.
- **Eliminar de la planificación activa:** pulsar **Eliminar** o **Eliminar sistema**, escribir el motivo, marcar la confirmación y guardar. Se archiva y conserva todo el historial; no hay borrado físico.
- **Restaurar:** en **Sistemas**, pulsar **Mostrar eliminados / archivados**, localizar el sistema y pulsar **Restaurar**. Escribir motivo y guardar. Las instalaciones que ya estaban archivadas conservan su estado propio.
- **Auditar:** dentro del sistema, pulsar **Ver cambios del sistema**.

## Instalaciones, bases orbitales y settlements

Se administran como instalaciones dentro de un sistema. Un Comandante puede abrir el sistema y pulsar **+ Agregar instalación / facility**, elegir una receta activa, revisar sus materiales y guardar. También dispone de **+ Agregar facility** desde la tarjeta del sistema. Sin receta se crea sin objetivos; cualquier comandante o administrador puede completarlos. Ambos roles pueden usar **Facilities y recetas** para definir plantillas por tipo, tier y variante; no se presupone una cantidad exacta del juego sin una fuente confirmada.

- **Crear:** desde la tarjeta del sistema, pulsar **+ Agregar facility**, o abrir un sistema activo → **+ Agregar instalación / facility**. Elegir la receta en **Facility / receta** para ver los materiales y cantidades que se cargarán automáticamente. Completar nombre, cuerpo, ubicación y orden visual → **Guardar**. El servidor copia tipo/tier y todos los objetivos de esa versión en una única transacción. Comienza En construcción.
- **Sin receta:** seleccionar **Sin receta: cargar objetivos manualmente** y completar tipo/tier. Después usar **+ Agregar objetivo**. Si no hay plantillas cargadas, crearlas primero desde **Facilities y recetas** o reutilizar objetivos existentes como se explica abajo.
- **Consultar:** abrir el sistema; cada instalación tiene su panel de materiales.
- **Modificar:** **Editar instalación** → cambiar datos → **Guardar** (motivo opcional). No se traslada a otro sistema con este formulario.
- **Eliminar de la planificación activa:** **Eliminar instalación** → motivo → confirmar baja → **Guardar**. Conserva historial y permite restauración.
- **Restaurar:** **Mostrar eliminadas / archivadas y objetivos retirados** → **Restaurar instalación** → motivo → guardar. El sistema padre debe estar activo; restaurarlo primero si corresponde.
- **Auditar:** pulsar **Ver cambios** en los controles de la instalación.

**Construcción:** al cubrir todos los materiales, el distintivo cambia automáticamente a **Terminada** y la tabla se contrae. Una corrección que deje faltantes recupera el estado de construcción registrado. Comandantes y administradores también pueden pulsar **Marcar terminada**, indicar motivo y confirmar aunque queden faltantes; ese cierre manual se conserva y excluye la instalación de las listas activas. Para reabrir un cierre manual, usar **Reabrir construcción** y un motivo.

Las instalaciones anteriores a esta fase conservan su estado libre original. Si aún tienen faltantes y no se clasificaron manualmente, aparecen **Sin clasificar**; si todos sus materiales están cubiertos, muestran **Terminada**. Archivar y terminar son acciones distintas.

## Materiales y objetivos

1. En una instalación activa, pulsar **+ Agregar objetivo**.
2. Elegir material, ingresar cantidad requerida como entero sin separadores; guardar.
3. Para cambiar la cantidad, usar **Editar objetivo**, ingresar nueva cantidad; guardar. No se cambian las entregas históricas.
4. Para retirarlo, usar **Retirar objetivo**, motivo y confirmación; guardar.
5. Para recuperarlo, usar **Mostrar eliminadas / archivadas y objetivos retirados** → **Restaurar objetivo** → revisar cantidad y motivo → guardar.
6. **Ver cambios** en el panel del material muestra las revisiones del objetivo.

Si falta un material en el catálogo, un comandante o administrador puede crearlo desde **Sistemas → Agregar material al catálogo**. Ambos roles gestionan el catálogo existente y pueden dar de alta materiales.

## Catálogo de mercancías

Ingresar como Comandante o Administrador → **Mercancías**.

1. **Crear:** **+ Crear mercancía** → código, nombre en español, nombre en inglés opcional y alias opcionales, uno por línea → guardar.
2. **Editar:** **Editar** → corregir nombres/código o alias → guardar. Se conserva el ID y todos sus objetivos, entregas y lugares de abastecimiento.
3. **Equivalencias:** un alias es otro nombre/código de la misma mercancía. Por defecto se conservan los nombres/códigos anteriores al renombrar. Para corregir identidades equivocadas, desmarcar **Conservar códigos y nombres anteriores como alias** y revisar la lista de alias. No se fusionan mercancías distintas ni sus cantidades. Máximo 30 alias; un código, nombre o alias no puede corresponder a otro ID, incluso desactivado.
4. **Desactivar:** **Desactivar** → motivo → confirmar → guardar. Deja de aparecer al elegir un material nuevo. Los proyectos, recetas, balances e historial existentes se conservan; las correcciones de entregas siguen disponibles según sus permisos.
5. **Reactivar:** **Mostrar también desactivadas** → **Reactivar** → motivo → guardar. Recupera el mismo ID.
6. **Auditar:** **Ver cambios** muestra nombres, códigos, alias y estado anteriores/posteriores con autor y motivo. Si otra persona editó el registro, actualizar los datos antes de guardar.

Los alias y la conservación automática de nombres anteriores requieren importar 007. Si aparece el aviso de migración, seguir [DESPLIEGUE.md](DESPLIEGUE.md). La carga inicial de recetas reconoce los alias; una equivalencia ambigua debe corregirse antes de importar.

## Facilities y recetas automáticas

Comandantes y administradores gestionan este catálogo; no es una lista de instalaciones ya construidas.

1. **Crear:** **Facilities y recetas → + Crear receta**. Completar nombre/variante, tipo, tier opcional, fuente/verificación y URL opcional. Añadir cada mercancía y cantidad positiva, sin repetir materiales. Completar motivo → guardar.
2. **Reutilizar objetivos de AFEC:** abrir una instalación con materiales → **Guardar objetivos como receta**. Revisar el tipo, tier, origen y cantidades precargadas antes de guardar. Se usan los requeridos activos, no los faltantes ni las entregas.
3. **Usar:** **Agregar facility** dentro del sistema → elegir receta → revisar el detalle → guardar. Los objetivos quedan disponibles para entregas y listas de deseos.
4. **Editar el catálogo:** **Editar receta y materiales** → cambiar cantidades, añadir/quitar materiales, actualizar fuente y motivo → guardar. Solo cambia lo que se copiará a instalaciones futuras; no altera construcciones existentes.
5. **Ajustar un proyecto existente:** en esa instalación usar **Editar objetivo**, **Agregar objetivo** o **Retirar objetivo**, conservando entregas e historial. Editar el tipo de la instalación no sustituye automáticamente sus objetivos.
6. **Eliminar/restaurar receta:** **Eliminar receta** → motivo y confirmación → guardar. Deja de estar disponible al crear instalaciones, pero sus instalaciones anteriores mantienen objetivos y procedencia. **Mostrar eliminadas → Restaurar receta** la recupera.
7. **Ver cambios:** historial de versiones, fuente y cantidades anteriores/posteriores. Si la receta cambia después de revisarla para crear una instalación, el servidor exige actualizar antes de guardar.

Límite por receta: 100 materiales, catálogo paginado de 25. Las cantidades se guardan como enteros decimales, sin redondeo de JavaScript. No hay actualización externa automática. Las recetas sintéticas de pruebas quedan en una base separada; el catálogo de referencia de la demo usa las fuentes descritas abajo.

## Catálogo inicial AFEC + comunidad

La demo dispone de **71 recetas**: 66 referencias DaftMav y 5 ejemplos AFEC (cuatro del CSV investigado y un Dodec de Sheets), con 55 mercancías. Los puertos **Primary** aparecen separados de construcciones posteriores. Cada receta muestra **Observación AFEC**, **Referencia comunitaria** o **Plantilla manual**; **Ajustada localmente** identifica versiones posteriores de una receta importada.

Para una instalación nueva: **Agregar facility → Facility / receta → elegir variante → revisar materiales → Guardar**. Las cantidades AFEC son observaciones de proyectos concretos y las comunitarias son referencias; ajustar según los requeridos del proyecto en el juego. Un tier sin confirmar queda vacío, no se supone uno.

En otra instalación del programa, después de aplicar 006 y crear el administrador:

1. Ingresar como Comandante o Administrador → **Facilities y recetas**.
2. Revisar **Catálogo AFEC + comunidad**, fuentes y **Datos pendientes y criterio de carga**.
3. Pulsar **Cargar catálogo inicial**, confirmar y guardar. Carga únicamente las recetas que faltan y sus mercancías; conserva las recetas existentes, incluso editadas o archivadas. No modifica sistemas, instalaciones, objetivos ni entregas.
4. Si falta respuesta, usar **Comprobar y reintentar**, conservando la misma operación. Repetir la carga tampoco duplica sus identidades de fuente.

Comandantes y administradores gestionan las recetas y pueden cargar el paquete inicial, incluidas sus mercancías. Sus nombres/IDs existentes se conservan. Una coincidencia ambigua o mercancía inactiva detiene la carga completa y requiere revisar ese catálogo antes de reintentar.

Los 33 materiales de estructuras sin identificar del CSV no se asignan a un tipo supuesto; las 51 filas de tipos sin cantidades no producen recetas vacías. Bio Large suma 8524 en AFEC y 8523 en la referencia comunitaria: ambas variantes se conservan. La celda TOTALES 6375 de AFEC no se usa. Detalle, fuentes y reconstrucción en [SOURCES.md](../resources/catalogue/SOURCES.md).

## Entregas y correcciones

Las entregas se registran desde el panel lateral del material, como se describe abajo. Comandantes y administradores pueden registrar aportes propios o seleccionar otra identidad. El historial conserva quién entrega, quién registra y las correcciones vinculadas. Para corregir un movimiento concreto, abrir **Historial de entregas** → **Retirar / corregir**, ingresar cantidad y confirmar. No se editan ni se borran los originales.

## Carga de stock y paladas

Al completar todos los materiales de una instalación, su bloque se contrae automáticamente y el estado cambia a **Terminada**. Pulsar **Mostrar materiales** para revisarlos o corregir una entrega. Si una corrección deja faltantes, vuelve a abrirse y recupera su estado de construcción, salvo que se haya cerrado manualmente. En Mi lista y Flota, los materiales sin faltantes se consultan desde **Materiales completados → Mostrar materiales**; los pendientes permanecen a la vista. El buscador solo recorre materiales desplegados.

La **lupa flotante abajo a la izquierda** abre **Buscar mercancía** para ubicar materiales por nombre en las tablas de los settlements de un sistema, las listas personal/flota y el catálogo. Cerrada muestra solo la lupa. Marca las coincidencias de la página actual y salta a la primera sin ocultar el resto. Usar las flechas o Enter / Mayús + Enter para recorrerlas; Escape cierra y quita las marcas. No distingue mayúsculas ni tildes. En el catálogo también busca por código, nombre en inglés y alias. Los paginadores siguen disponibles para consultar otras páginas.

1. Abrir un sistema y elegir **Comandante** en la instalación. Ambos roles pueden seleccionar su identidad o la de terceros. Si falta la propia, pulsar **Usar mi comandante**.
2. Escribir **Palada base (t)** y pulsar **Guardar palada**. Solo admite enteros positivos; guardar vacío quita la preferencia. Se conserva por comandante, incluso sin importar 008.
3. Seleccionar una fila de material. Se abre el panel lateral: **Palada** ofrece −/+; **Cantidad** ofrece un campo numérico y −/+. Enter suma la cantidad indicada. Debajo, **Cargar faltante** registra de una vez lo que falta para completar esa mercancía en la instalación, con el comandante seleccionado; no requiere escribir cantidad.
4. + suma al stock entregado y − registra una corrección vinculada a las entregas originales del comandante seleccionado. No se pide motivo; se conserva la auditoría. No se puede restar más de su saldo ni dejar negativo el stock.
5. Al confirmar una carga/corrección se cierra el panel derecho y los totales se actualizan sin recargar. La fila de una mercancía completa queda roja; vuelve a su aspecto habitual si una corrección deja faltante. Se registra toda sobreentrega y se avisa del sobrante. Las instalaciones terminadas/archivadas permiten restas; deben reabrirse/restaurarse para nuevas sumas. Un error de guardado conserva abierto el panel.
6. Comandantes y administradores encuentran edición, retiro/restauración e historial del objetivo dentro de **Objetivo y cambios** en el panel.

Una respuesta incierta conserva la solicitud y ofrece **Comprobar y reintentar** con su misma clave, incluso tras recargar. No registrar de nuevo la entrega con otra clave.

## Carga total en carrier y reparto automático

En **Mi lista** o **Flota**, usar **Tu carga en carrier**: elegir o registrar el carrier, seleccionar material, escribir el **total a bordo** y guardar. El total reemplaza el registro anterior de esa cuenta, carrier y material; 0 lo vacía. Cada carga se vincula a una de las dos listas.

El sistema propone los destinos automáticamente desde los faltantes de las instalaciones seleccionadas, siguiendo el orden visible: cubre cada faltante antes del siguiente. Con 3000 t y faltantes de 1000, 500 y 1500 t, muestra esas tres asignaciones. Agregar/quitar instalaciones o registrar entregas actualiza las propuestas al consultar la lista o pulsar **Actualizar reparto**. Los sobrantes permanecen sin destino y varios carriers propios no cubren dos veces el mismo faltante.

La carga a bordo y el stock entregado se registran por separado. Al descargar, registrar la entrega en su instalación y actualizar el total del carrier. Este reparto es planificación propia; no reserva material entre cuentas. Detalles y límites en [STOCK_Y_CARRIERS.md](STOCK_Y_CARRIERS.md).

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

El archivo original `public/assets/images/Emblema AFEC Logística Espacial.png` se utiliza en cabecera, acceso y favicon. La cabecera lo muestra a 120 px de ancho en escritorio y 96 px en móvil, con altura proporcional y espacio suficiente para la navegación. Si el navegador conserva el estilo anterior, actualizar con **Ctrl + F5**.

Paleta AFEC: fondo `#0B0E14`, gris `#C7C7C7`, naranja `#FF7100` y rojo `#CC0000`, con variantes de contraste para texto/errores. Los bordes de la cabecera y el acceso tienen animaciones CSS ligeras; las tarjetas/paneles se animan al pasar el cursor o recibir foco. Se desactivan cuando el dispositivo solicita movimiento reducido. No requieren librerías, fuentes externas ni solicitudes de red adicionales.

## Conflictos y reintentos

Si otra persona cambió el registro mientras el formulario estaba abierto, se pide actualizar los datos antes de guardar de nuevo. Si falla la conexión y el resultado es incierto, usar **Comprobar y reintentar**: se conserva la operación para confirmar el mismo guardado sin duplicarlo. No reemplazarla por otra carga idéntica.

Las contraseñas nuevas no se guardan en el almacenamiento de recuperación del navegador. Después de recargar, se consulta primero el resultado de la operación; si no existe un resultado confirmado, se pide reingresar la contraseña para reintentar con la misma clave. Si cambiaste tu propia cuenta y perdiste la respuesta, iniciá sesión con los datos nuevos y comprobá el resultado antes de repetir la operación.

## Funcionalidades todavía pendientes

La importación de mercados por CSV ya está disponible con la migración 009; los pendientes indicados a continuación se refieren a automatización externa y otros módulos.

Importación completa de sistemas/instalaciones/entregas de Sheets, clasificación de estructuras desconocidas y tiers pendientes, reservas/cargas en tránsito y un módulo completo de edición/retiro de comandantes y carriers. Estos últimos pueden seleccionarse o darse de alta al registrar una entrega; no cuentan todavía con pantalla administrativa completa. No hay sincronización automática de stock externo, registro público de cuentas ni roles personalizados. Las actualizaciones de esquema de producción se aplican expresamente mediante sus paquetes SQL.

## Registrar entregas desde Mi lista y Flota

1. Buscar la mercancía y localizar el settlement donde ya descargaste. Pulsar su **fila de destino**, o enfocarla y usar Enter/espacio; **Más destinos** permite acceder a los siguientes. **Ver sistema** abre la página de sus instalaciones.
2. Se abre el mismo panel lateral de las instalaciones, con el material y destino elegidos. **Comandante y palada** permite elegir identidad y guardar la **Palada base (t)** compartida con las instalaciones. Se despliega automáticamente cuando todavía falta configurar la palada.
3. Usar **Palada +**, escribir una **Cantidad (t)** y pulsar +/Enter, o **Cargar faltante** para completar esa mercancía. Las restas generan correcciones vinculadas a las entregas del comandante seleccionado. Al confirmar se cierra el panel y se recarga la lista; ambas listas y la instalación reflejan la misma entrega. El servidor calcula el faltante al guardar y no reparte cantidades entre destinos.
4. **Historial / corregir** permite revisar y corregir movimientos. También hay **Historial de entregas** en cada instalación seleccionada, incluso cuando ya no quedan materiales pendientes.
5. El apartado **Carga a bordo en carrier (opcional)** conserva el registro independiente de mercancía transportada. No se convierte en entrega al descargar ni se modifica al registrar estas entregas.

## Mercados del Sistema

1. Iniciar sesión y abrir **Mercados del Sistema**, o pulsar **Dónde conseguir** junto a un material requerido o en Mi lista/Flota.
2. Buscar por nombre original o de colonización, elegir sistema/estación/economía y tamaño de nave. Pulsar el nombre de una estación para consultar sus materiales. **Large** solo aparece si se registró pad L verificado.
3. Revisar cantidad, fuente y fecha: **Confirmado** hasta siete días, **Desactualizado** después, **Desconocido** si faltan datos/verificación. El stock de mercado no es una entrega y no reduce faltantes. Una mercancía ausente del CSV no implica stock cero.
4. Elegir sistema y marcar **Materiales requeridos sin proveedores** para identificar materiales de sus construcciones sin suministro positivo registrado.
5. Comandantes y administradores: desplegar **Administración: importar CSV**, cargar inventario/resumen, validar, revisar y confirmar. Repetir los mismos archivos no duplica registros. **Actualizar stock**, **Equivalencia**, **Historial** y **Datos de estación** permiten mantenimiento con motivo y control de versiones.

Si aparece actualización pendiente, aplicar primero el paquete 009 siguiendo [MERCADOS.md](MERCADOS.md). La guía incluye los archivos originales, formato, activación segura y rollback. Los CSV del 15/10/2026 no informan economía ni pads; deben completarse con información verificada.
