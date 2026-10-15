# Stock por material y reparto de carriers

## Aclaración del 16/10/2026: entregas recibidas desde las listas

El propietario confirmó que «ir marcando cuánto se carga» significa **mercancía ya descargada en un settlement**. En Mi lista y Flota, cada destino de un material ofrece **Registrar entrega** y **Historial / corregir**. El diálogo identifica material, sistema y settlement, permite indicar cantidad recibida y comandante (propio por defecto), y admite datos opcionales de carrier/fecha. Guardar registra una entrega real y recarga la lista; los faltantes de ambas vistas y el progreso de la instalación se derivan de esos mismos movimientos.

Alcance/archivos: plantillas de lista/carrier, `logistics.js` para destinos paginados, `planning.js` para advertir sobreentregas, `carrier-load.js` para recuperar cargas previas, CSS y pruebas de entregas desde listas. Se reutilizan el formulario, `POST deliveries`, ledger append-only, permisos, CSRF, auditoría y recibos idempotentes existentes. No hay backend nuevo ni DDL. La revisión de riesgos se centra en destino equivocado, doble registro y confusión entre carga/entrega: el botón corresponde a un destino concreto, el diálogo lo identifica y los reintentos reutilizan la misma operación.

Los botones también aparecen al cargar **Más destinos**. El historial permanece disponible en **Instalaciones seleccionadas** aunque una mercancía ya no tenga faltantes; sus correcciones generan movimientos compensatorios vinculados al original. No se distribuye automáticamente una entrega entre settlements. Las sobreentregas se guardan completas y se avisa del sobrante. La carga a bordo previa sigue bajo **Carga a bordo en carrier (opcional)**: no se convierte en entregas ni se descuenta automáticamente; las operaciones pendientes de carrier abren ese apartado para poder recuperarlas.

Verificación reproducible: `python tests/run.py --php RUTA_PHP --config CONFIG_TEST --webroot repository --apache-root RUTA_APACHE`; el recorrido `wishlist_delivery_http.py` extrae los destinos de los botones HTML, registra entregas, comprueba ambos scopes, reintento sin duplicar, identidad/actor, destino exacto, CSRF y rollback. Rollback: revertir únicamente código conservando entregas reales, correcciones, auditoría, recibos y snapshots de carrier; nunca convertir/borrar esos registros ni restaurar balances antiguos para revertir la interfaz.

Resultado del 16/10/2026: **893 HTTP y 12 SQL aprobadas**, simulación/repetición del migrador local, 16 comprobaciones de cantidades/payload de carga rápida, sintaxis y diff-check aprobados. UI local aislada: entrega de 15 t reduce 100 a 85 t en ambas listas; corrección vinculada de 5 t deja 90 pendientes sin borrar la entrega; destino 26 cargado con «Más destinos» registra en la ubicación correcta; sobreentrega de 2 t sobre un objetivo de 1 t muestra aviso de sobrante y conserva historial desde la selección ya cubierta. Escritorio 1280 y móvil 390 sin desbordamiento, formulario con destino explícito, consola sin errores y sesión cerrada. No se registraron entregas de prueba en producción.

## Alcance y uso

La tabla muestra material, requerido, entregado, pendiente y sobrante. Seleccionar una fila con clic, Enter o espacio abre un panel lateral compartido. **Palada** tiene botones −/+; **Cantidad** añade un campo de toneladas enteras y botones −/+. Enter suma la cantidad escrita. Los controles de objetivos se encuentran en **Objetivo y cambios**, con los permisos anteriores, incluida su restauración.

La **Palada base (t)** se configura en la cabecera de cada instalación para el comandante seleccionado y se conserva por identidad. Se aceptan enteros positivos hasta 9223372036854775807; guardar vacío quita la preferencia. **Usar mi comandante** vincula la identidad propia cuando todavía falta. El selector y los reintentos conservan la recuperación de operaciones durante la sesión.

No se pide motivo en la carga o resta de stock, mercancías, objetivos, recetas o abastecimiento ordinario; el historial recibe una descripción de la operación. Sistemas e instalaciones muestran un motivo opcional tanto al crear como al editar. Las bajas, los cambios de estado y las acciones sensibles de cuentas conservan sus explicaciones y confirmaciones.

En **Mi lista** o **Flota**, **Tu carga en carrier** registra el total que la cuenta declara a bordo para un material/carrier y lo vincula a una de esas listas. Se puede seleccionar un carrier existente o registrar nombre/código. Guardar sustituye el total anterior de esa misma cuenta, material y carrier; 0 lo vacía. La carga de otras cuentas no se consulta ni se modifica desde este flujo.

El reparto se calcula al consultar/actualizar la lista según sus faltantes actuales, en el mismo orden de destinos: sistema, nombre de instalación e ID. Cubre cada faltante antes de pasar al siguiente. Con faltantes de 1000, 500 y 1500 t, una carga de 3000 t propone esas tres cantidades; con 1200 t, propone 1000 y 200 t. Agregar o quitar instalaciones cambia el reparto automáticamente en la siguiente lectura. Se excluyen objetivos retirados, instalaciones terminadas/archivadas y sistemas archivados. El excedente permanece sin destino.

Entre varios carriers de la misma cuenta y lista, el reparto sigue el orden de carrier por nombre/ID y evita cubrir dos veces un faltante. Una carga se vincula a una lista a la vez; cambiar esa selección mueve su planificación. Son propuestas calculadas, no reservas entre cuentas. Registrar una carga a bordo no crea entregas. Las entregas reales se registran desde el panel de la instalación y actualizan los faltantes; el usuario actualiza el total a bordo después de descargar. No hay traslado ni descuento de carrier inferido desde una entrega sin carrier.

## Persistencia, permisos y riesgos

Archivos principales: `quick-load.js`, `carrier-load.js`, los parciales de tabla/panel/carrier, `CommanderLoadRepository/Service`, `CarrierLoadRepository/Service`, el repositorio y controlador de logística y la allowlist de recursos Apache. PHP/PDO y JavaScript vanilla; sin dependencias nuevas de producción.

Se reutiliza el ledger auditado existente para persistir las preferencias de palada cuando faltan las columnas 008, y para conservar snapshots append-only de carga por cuenta/carrier/material. No hay DDL nuevo ni migración automática. Con 008, las columnas y la auditoría se escriben juntas. Si 008 se importa después, la última preferencia auditada prevalece sobre las columnas nuevas inicializadas; el siguiente guardado sincroniza ambas.

Las escrituras requieren autenticación revalidada, CSRF, clave idempotente y versión. Se bloquea el comandante o carrier durante el cambio. Un fallo de auditoría revierte toda la operación. Las consultas de reparto utilizan una única lectura transaccional consistente, incluyen todos los destinos sin limitarse a la primera página y mantienen precisión incluso si el objetivo excede BIGINT firmado. Nunca se modifican ni se borran movimientos originales.

Riesgos: registrar como total un incremento, atribuir stock al comandante incorrecto o confundir una propuesta con una entrega. Los formularios indican la identidad, el reemplazo del total y la separación entre carga a bordo y entregas. Una operación con respuesta incierta bloquea nuevos envíos y ofrece recuperar la misma clave, también después de recargar o cambiar de lista.

## API

- `GET quick/options`: `presets_ready=true` indica soporte funcional; `load_columns_ready` informa si existen las columnas 008.
- `POST commander/identity`: cuerpo vacío, crea/vincula solo la identidad de la cuenta autenticada; recibo `logistics.commander.identity`.
- `GET carrier/loads&scope=personal|fleet`: cargas propias y propuestas actuales, sin selector de otra cuenta.
- `POST carrier/load`: `scope`, `commodity_id`, `quantity` como cadena no negativa, `version` (`"0"` en el alta), y `carrier_id` o `carrier_name`/`carrier_code`. Recibo `logistics.carrier.load`.
- `POST quick/withdraw`: `reason` es opcional; se mantiene una descripción automática si se omite.

Los endpoints anteriores de entregas siguen compatibles para clientes API y operaciones pendientes.

## Buscador flotante de mercancías

En sistemas (también públicos), listas personal/flota y catálogo, una **lupa flotante abajo a la izquierda** abre el panel **Buscar mercancía**. Al cerrar queda únicamente el icono circular, con etiqueta accesible. Busca por nombre sin distinguir tildes ni mayúsculas, marca las filas/tarjetas y desplaza la vista a la coincidencia actual. Flechas, Enter y Mayús + Enter recorren las coincidencias; Escape o cerrar quitan las marcas y devuelven el foco al botón. En el catálogo también se incluyen código, nombre en inglés y alias. Busca solo en la página cargada; no consulta otros sistemas ni páginas del paginador.

La interfaz se comparte mediante `templates/commodity-search.php` y `public/assets/js/commodity-search.js`, cargados solo en esas vistas y permitidos en `.htaccess`. No hace solicitudes ni escrituras adicionales, no oculta materiales y conserva los clics de carga. El desplazamiento respeta la preferencia de movimiento reducido.

Prueba manual reproducible: abrir un sistema con un material repetido entre instalaciones, buscar su nombre con mayúsculas/tildes y comprobar el contador y las marcas; recorrer con Enter, Mayús + Enter y las flechas; buscar un nombre inexistente y cerrar con Escape. Buscar por código en el catálogo y por nombre en una lista. Repetir a 390 px y seleccionar una fila encontrada para abrir su panel de stock. No se necesita registrar entregas para esta comprobación. Rollback del buscador: revertir su código/vistas/estilos/allowlist; no hay datos que restaurar.

## Verificación y rollback

```text
python tests/run.py --php RUTA_PHP --config CONFIG_TEST --webroot repository --apache-root RUTA_APACHE
node tests/quick_load.mjs
python tests/load_upgrade.py --php RUTA_PHP --mysql CLIENTE_MYSQL --config CONFIG_TEST --initial-sql SQL_INICIAL_001_006
```

Todas las escrituras de prueba se ejecutan sobre bases `afec_test_*` locales. La regresión cubre permisos, CSRF, precisión, repetición idempotente, versiones obsoletas, aislamiento de cuentas, cambios de lista, sumas/restas compensatorias, reparto con sobrantes y fallo de auditoría. La prueba de actualización guarda/usa una palada antes de 008 y comprueba que se conserva después de importarla.

Resultado local del 15/10/2026: 730 comprobaciones HTTP y 12 SQL aprobadas con Apache/FastCGI y `.htaccess`, simulación/repetición del migrador, 16 aserciones JavaScript de cantidades/payloads y 8 comprobaciones del paquete 008. Lint PHP/JavaScript aprobado. Navegador sobre una base aislada: vinculación propia, escritura y guardado de 784 t, suma/resta de palada, 25 t con Enter, rechazo de caracteres no numéricos, persistencia tras recargar, registro de total de carrier y propuesta con sobrante. Vista de 390 px: lista y panel sin desbordamiento horizontal; tamaño restaurado al terminar. Las pruebas no registraron cargas sintéticas en producción.

Rollback: revertir estos cambios de código conservando auditoría, recibos, columnas 008 si existen, comandantes, carriers y movimientos. El código anterior no muestra las nuevas cargas ni interpreta preferencias guardadas solamente en auditoría; no deben borrarse al volver a desplegar esta versión. No reimportar el instalador ni restaurar balances antiguos para revertir la interfaz.
