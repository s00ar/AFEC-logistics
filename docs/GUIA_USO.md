# Uso de la versión actual

Actualizado: 14/10/2026. Corresponde a la demo local y al CRUD implementado en fase 2; no implica que exista un sitio o una cuenta instalada en Hostinger.

## Ingresar a la demo

1. Abrir `http://127.0.0.1:8077/?page=login` en este PC, con los servicios locales activos.
2. Usar `admin` para probar la administración o `operator` para probar los permisos de operador. Existe también `viewer`, con permiso de lectura.
3. La contraseña sintética de estas cuentas está en `.local/preview-password.txt`, ignorado por Git. Copiar el contenido sin el salto de línea y pulsar **Iniciar sesión**. No usar la contraseña MySQL para entrar a la aplicación.
4. Si ya se inició sesión, pulsar **Salir** para cambiar de cuenta. La consulta pública no muestra botones de escritura.

Las tres cuentas y su contraseña son exclusivamente de la demo. Una instalación de producción requiere crear su propio primer administrador; no incluye cuentas predeterminadas.

## Usuarios

Actualmente **Usuarios**, visible solo para administrador, muestra nombre, usuario, rol y estado. Todavía no contiene botones para crear/editar cuentas ni cambiar contraseñas.

`tools/create-admin.php` crea únicamente el primer administrador cuando no hay cuentas. No sirve para agregar usuarios posteriores. No editar hashes o insertar cuentas manualmente en MySQL: la próxima fase propuesta incorpora alta de usuarios, roles, desactivación y restablecimiento de acceso desde la administración.

## Sistemas

- **Crear:** en **Sistemas**, pulsar **+ Crear sistema**; completar nombre, descripción opcional y motivo opcional; pulsar **Guardar**. Se abre el sistema creado.
- **Consultar:** pulsar el nombre del sistema. Se muestran sus instalaciones, materiales y progreso.
- **Modificar:** pulsar **Editar** en la tarjeta o **Editar sistema** dentro del sistema; cambiar los datos, completar el motivo y guardar. No se cambian sus IDs ni las instalaciones vinculadas.
- **Eliminar de la planificación activa:** pulsar **Archivar** o **Archivar sistema**, escribir el motivo, marcar la confirmación y guardar. Se conserva todo el historial; no hay borrado físico.
- **Restaurar:** en **Sistemas**, pulsar **Mostrar también archivados**, localizar el sistema y pulsar **Restaurar**. Escribir motivo y guardar. Las instalaciones que ya estaban archivadas conservan su estado propio.
- **Auditar:** dentro del sistema, pulsar **Ver cambios del sistema**.

## Instalaciones, bases orbitales y settlements

Se administran como instalaciones dentro de un sistema. El campo **Tipo** es texto libre en esta versión; todavía no hay catálogo de tipos ni recetas automáticas.

- **Crear:** abrir un sistema activo → **+ Agregar instalación** → completar nombre y, cuando corresponda, cuerpo, ubicación, tier, tipo, estado y orden visual → **Guardar**. El nombre y el orden son obligatorios; la pantalla propone orden 0.
- **Consultar:** abrir el sistema; cada instalación tiene su panel de materiales.
- **Modificar:** **Editar instalación** → cambiar datos → escribir motivo → **Guardar**. No se traslada a otro sistema con este formulario.
- **Eliminar de la planificación activa:** **Archivar instalación** → motivo → confirmar archivo → **Guardar**.
- **Restaurar:** **Mostrar archivadas y retirados** → **Restaurar instalación** → motivo → guardar. El sistema padre debe estar activo; restaurarlo primero si corresponde.
- **Auditar:** pulsar **Ver cambios** en los controles de la instalación.

El campo **Estado** permite escribir, por ejemplo, `En construcción` o `Terminada`. Hoy es un metadato libre: no cambia automáticamente el porcentaje, no cierra objetivos ni alimenta una lista de deseos. La ampliación propuesta añadirá estados validados y visibles con una regla de cierre acordada.

## Materiales y objetivos

1. En una instalación activa, pulsar **+ Agregar objetivo**.
2. Elegir material, ingresar cantidad requerida como entero sin separadores y completar motivo; guardar.
3. Para cambiar la cantidad, usar **Editar objetivo**, ingresar nueva cantidad y motivo; guardar. No se cambian las entregas históricas.
4. Para retirarlo, usar **Retirar objetivo**, motivo y confirmación; guardar.
5. Para recuperarlo, usar **Mostrar archivadas y retirados** → **Restaurar objetivo** → revisar cantidad y motivo → guardar.
6. **Ver cambios** en la fila del material muestra las revisiones del objetivo.

Si falta un material en el catálogo, un administrador puede crearlo desde **Sistemas → Agregar material al catálogo**. Los operadores pueden usar el catálogo existente; no dan de alta materiales de catálogo en esta versión.

## Conflictos y reintentos

Si otro operador cambió el registro mientras el formulario estaba abierto, se pide actualizar los datos antes de guardar de nuevo. Si falla la conexión y el resultado es incierto, usar **Comprobar y reintentar**: se conserva la operación para confirmar el mismo guardado sin duplicarlo. No reemplazarla por otra carga idéntica.

## Funcionalidades todavía pendientes

Alta/edición de usuarios, registro/corrección de entregas, gestión de comandantes/carriers, lista de deseos, ubicaciones compartidas de abastecimiento e importación de Sheets. Los estados formales y el uso visual del logo recibido forman parte de la ampliación propuesta, todavía sin implementar.
