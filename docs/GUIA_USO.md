# Uso de la versión actual

Actualizado: 14/10/2026. Corresponde a las fases 1–5. El propietario indicó que actualizar `main` en GitHub activa el despliegue automático en Hostinger; el estado de publicación y la configuración del servidor se verifican por separado.

## Ingresar al sitio de Hostinger

Abrir [AFEC Logística](https://logistica.afec.space/) para consultar el progreso público. Para escribir, entrar desde **Ingresar** o abrir [Iniciar sesión](https://logistica.afec.space/?page=login), con una cuenta creada en esa instalación.

Las cuentas de la demo local no se publican mediante Git. El primer administrador, la configuración privada de MySQL y las migraciones se preparan según [DESPLIEGUE.md](DESPLIEGUE.md). Un push publica código; no crea cuentas ni ejecuta SQL. Si el sitio devuelve 403/500 o un error de conexión, todavía no se puede dar la instalación por operativa.

Para la base vacía se entregó un SQL privado de instalación inicial con el catálogo y una cuenta `admin`. La contraseña está en `ACCESO_ADMINISTRADOR.txt` junto al SQL, fuera de Git. Importación y acceso en [IMPORTAR_PHPMYADMIN.md](IMPORTAR_PHPMYADMIN.md). Después de importar, crear las demás cuentas desde **Usuarios**. Las referencias ya vienen cargadas; no hay que precargar otra vez el catálogo.

Estado comprobado el 14/10/2026: después de cargar la configuración y el esquema, panel y API de sistemas responden 200. El propietario confirmó que puede administrar cuentas y cambiar roles. El fallo inicial de configuración y la instalación por phpMyAdmin se documentan en [DESPLIEGUE.md](DESPLIEGUE.md); los controles nuevos de identidad están en [CORRECCION_ROLES.md](CORRECCION_ROLES.md).

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
3. **Cambiar privilegios:** **Cambiar rol** en la cuenta existente → seleccionar Comandante, Operador o Administrador → motivo, por ejemplo «Promoción para coordinar construcciones» → **Guardar**. Se conserva el usuario de acceso y su identidad de comandante. La cuenta debe volver a iniciar sesión para recibir los nuevos permisos.
4. **Modificar otros datos:** **Editar usuario** → cambiar nombre visible, rol o **Cuenta activa** → motivo → guardar. Para renombrar el acceso, marcar explícitamente **Cambiar el nombre de usuario de acceso**. El formulario conserva el usuario original cuando esa opción no está marcada, aunque el navegador intente autocompletarlo con otro nombre. Los cambios revocan las sesiones existentes de esa cuenta.
5. **Eliminar:** **Eliminar usuario** → motivo → confirmar baja → guardar. Se revoca el acceso y se conservan aportes, historial y lista personal. El nombre de usuario sigue reservado.
6. **Restaurar:** localizar la cuenta eliminada → **Restaurar usuario** → motivo → guardar. Recupera acceso y sus datos.
7. **Contraseña:** **Restablecer contraseña** → contraseña nueva y motivo → guardar. Se revocan las sesiones y la contraseña anterior deja de funcionar. Comunicar la nueva contraseña al titular.

Desactivar con **Cuenta activa** permite una suspensión reversible sin marcarla eliminada. No se permite eliminar, desactivar o quitar el rol al último administrador activo; otro administrador debe cambiar tu propio rol o desactivar tu cuenta. Si editás tu propia cuenta o contraseña, iniciá sesión otra vez.

Los roles incorporados son Administrador, Operador y Comandante (identificador técnico `viewer`, conservado por compatibilidad). Se asignan desde el formulario de usuario; no se crean roles personalizados ni existe registro público automático.

| Función | Comandante | Operador | Administrador |
| --- | --- | --- | --- |
| Consultar sistemas, instalaciones, materiales y progreso | Sí; también público | Sí | Sí |
| Registrar y corregir entregas propias | Sí | Sí | Sí |
| Registrar y corregir entregas de terceros | No | Sí | Sí |
| Modificar lista personal y lista compartida de la flota | Sí | Sí | Sí |
| Agregar, editar o retirar lugares de abastecimiento | Sí | Sí | Sí |
| Crear/editar/archivar/restaurar sistemas, instalaciones y objetivos | No | Sí | Sí |
| Marcar instalaciones terminadas o reabrirlas | No | Sí | Sí |
| Administrar recetas de facilities | No | Sí | Sí |
| Crear mercancías y cargar el catálogo inicial | No | No | Sí |
| Crear, editar, eliminar/restaurar usuarios, cambiar roles y contraseñas | No | No | Sí |

Las listas personales pertenecen exclusivamente a su cuenta, incluso frente a operadores y administradores. La promoción no mueve ni elimina listas, entregas o historial. El error «Ese usuario ya existe» indica un conflicto de nombre de acceso, no una prohibición de cambiar roles; no utilizar `admin` como nombre de otra cuenta.

`tools/create-admin.php` sigue reservado al primer administrador de una instalación vacía. Las cuentas posteriores se gestionan desde la web; no editar hashes manualmente en MySQL.

## Sistemas

- **Crear:** en **Sistemas**, pulsar **+ Crear sistema**; completar nombre, descripción opcional y motivo opcional; pulsar **Guardar**. Se abre el sistema creado.
- **Consultar:** pulsar el nombre del sistema o **Ver instalaciones**. Se muestran sus instalaciones, materiales y progreso.
- **Modificar:** pulsar **Editar** en la tarjeta o **Editar sistema** dentro del sistema; cambiar los datos, completar el motivo y guardar. No se cambian sus IDs ni las instalaciones vinculadas.
- **Eliminar de la planificación activa:** pulsar **Eliminar** o **Eliminar sistema**, escribir el motivo, marcar la confirmación y guardar. Se archiva y conserva todo el historial; no hay borrado físico.
- **Restaurar:** en **Sistemas**, pulsar **Mostrar eliminados / archivados**, localizar el sistema y pulsar **Restaurar**. Escribir motivo y guardar. Las instalaciones que ya estaban archivadas conservan su estado propio.
- **Auditar:** dentro del sistema, pulsar **Ver cambios del sistema**.

## Instalaciones, bases orbitales y settlements

Se administran como instalaciones dentro de un sistema. Operadores y administradores pueden usar **Facilities y recetas** para definir plantillas por tipo, tier y variante; no se presupone una cantidad exacta del juego sin una fuente confirmada.

- **Crear:** desde la tarjeta del sistema, pulsar **+ Agregar facility**, o abrir un sistema activo → **+ Agregar instalación / facility**. Elegir la receta en **Facility / receta** para ver los materiales y cantidades que se cargarán automáticamente. Completar nombre, cuerpo, ubicación y orden visual → **Guardar**. El servidor copia tipo/tier y todos los objetivos de esa versión en una única transacción. Comienza En construcción.
- **Sin receta:** seleccionar **Sin receta: cargar objetivos manualmente** y completar tipo/tier. Después usar **+ Agregar objetivo**. Si no hay plantillas cargadas, crearlas primero desde **Facilities y recetas** o reutilizar objetivos existentes como se explica abajo.
- **Consultar:** abrir el sistema; cada instalación tiene su panel de materiales.
- **Modificar:** **Editar instalación** → cambiar datos → escribir motivo → **Guardar**. No se traslada a otro sistema con este formulario.
- **Eliminar de la planificación activa:** **Eliminar instalación** → motivo → confirmar baja → **Guardar**. Conserva historial y permite restauración.
- **Restaurar:** **Mostrar eliminadas / archivadas y objetivos retirados** → **Restaurar instalación** → motivo → guardar. El sistema padre debe estar activo; restaurarlo primero si corresponde.
- **Auditar:** pulsar **Ver cambios** en los controles de la instalación.

**Construcción:** operadores/administradores pueden pulsar **Marcar terminada**, indicar motivo y confirmar aunque queden faltantes. El distintivo pasa a **Terminada** y queda fuera de ambas listas de deseos. El registro de entregas y el porcentaje real no se alteran. Para volver a recibir entregas, usar **Reabrir construcción** y un motivo.

Las instalaciones anteriores a esta fase conservan su estado libre original y aparecen **Sin clasificar** hasta que un operador elija **Marcar en construcción** o **Marcar terminada**; no se infiere automáticamente su estado real. Archivar y terminar son acciones distintas.

## Materiales y objetivos

1. En una instalación activa, pulsar **+ Agregar objetivo**.
2. Elegir material, ingresar cantidad requerida como entero sin separadores y completar motivo; guardar.
3. Para cambiar la cantidad, usar **Editar objetivo**, ingresar nueva cantidad y motivo; guardar. No se cambian las entregas históricas.
4. Para retirarlo, usar **Retirar objetivo**, motivo y confirmación; guardar.
5. Para recuperarlo, usar **Mostrar eliminadas / archivadas y objetivos retirados** → **Restaurar objetivo** → revisar cantidad y motivo → guardar.
6. **Ver cambios** en la fila del material muestra las revisiones del objetivo.

Si falta un material en el catálogo, un administrador puede crearlo desde **Sistemas → Agregar material al catálogo**. Los operadores pueden usar el catálogo existente; no dan de alta materiales de catálogo en esta versión.

## Facilities y recetas automáticas

Solo operadores/administradores gestionan este catálogo; no es una lista de instalaciones ya construidas.

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

1. Ingresar como administrador → **Facilities y recetas**.
2. Revisar **Catálogo AFEC + comunidad**, fuentes y **Datos pendientes y criterio de carga**.
3. Pulsar **Cargar catálogo inicial**, confirmar y guardar. Carga únicamente las recetas que faltan y sus mercancías; conserva las recetas existentes, incluso editadas o archivadas. No modifica sistemas, instalaciones, objetivos ni entregas.
4. Si falta respuesta, usar **Comprobar y reintentar**, conservando la misma operación. Repetir la carga tampoco duplica sus identidades de fuente.

Operadores administran las recetas ya cargadas; solo administradores pueden cargar el paquete inicial porque incluye altas de mercancías. Sus nombres/IDs existentes se conservan. Una coincidencia ambigua o mercancía inactiva detiene la carga completa y requiere revisar ese catálogo antes de reintentar.

Los 33 materiales de estructuras sin identificar del CSV no se asignan a un tipo supuesto; las 51 filas de tipos sin cantidades no producen recetas vacías. Bio Large suma 8524 en AFEC y 8523 en la referencia comunitaria: ambas variantes se conservan. La celda TOTALES 6375 de AFEC no se usa. Detalle, fuentes y reconstrucción en [SOURCES.md](../resources/catalogue/SOURCES.md).

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

El archivo original `public/assets/images/Emblema AFEC Logística Espacial.png` se utiliza en cabecera, acceso y favicon. La cabecera lo muestra a 120 px de ancho en escritorio y 96 px en móvil, con altura proporcional y espacio suficiente para la navegación. Si el navegador conserva el estilo anterior, actualizar con **Ctrl + F5**.

Paleta AFEC: fondo `#0B0E14`, gris `#C7C7C7`, naranja `#FF7100` y rojo `#CC0000`, con variantes de contraste para texto/errores. Los bordes de la cabecera y el acceso tienen animaciones CSS ligeras; las tarjetas/paneles se animan al pasar el cursor o recibir foco. Se desactivan cuando el dispositivo solicita movimiento reducido. No requieren librerías, fuentes externas ni solicitudes de red adicionales.

## Conflictos y reintentos

Si otro operador cambió el registro mientras el formulario estaba abierto, se pide actualizar los datos antes de guardar de nuevo. Si falla la conexión y el resultado es incierto, usar **Comprobar y reintentar**: se conserva la operación para confirmar el mismo guardado sin duplicarlo. No reemplazarla por otra carga idéntica.

Las contraseñas nuevas no se guardan en el almacenamiento de recuperación del navegador. Después de recargar, se consulta primero el resultado de la operación; si no existe un resultado confirmado, se pide reingresar la contraseña para reintentar con la misma clave. Si cambiaste tu propia cuenta y perdiste la respuesta, iniciá sesión con los datos nuevos y comprobá el resultado antes de repetir la operación.

## Funcionalidades todavía pendientes

Importación completa de sistemas/instalaciones/entregas de Sheets, clasificación de estructuras desconocidas y tiers pendientes, reservas/cargas en tránsito y un módulo completo de edición/retiro de comandantes y carriers. Estos últimos pueden seleccionarse o darse de alta al registrar una entrega; no cuentan todavía con pantalla administrativa completa. No hay integración de stock externo, registro público de cuentas ni roles personalizados. El despliegue y la inicialización reales en Hostinger siguen pendientes.
