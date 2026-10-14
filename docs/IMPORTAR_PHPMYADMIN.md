# Instalación inicial mediante phpMyAdmin

14/10/2026. El propietario confirmó que `u969616855_afeclogisitc` está vacía y solicitó un SQL para importarlo personalmente. Este procedimiento prepara una instalación nueva; no migra proyectos, entregas ni usuarios desde Google Sheets o la demo.

## Archivo entregado

Paquete privado: `.local/deployment/hostinger-20261014-final/AFEC_Logistica_instalacion_inicial.sql`, 343385 bytes, SHA256 `4df120e9e6ddba966253fe210567a3946702cca42a6ae42b5730b9fd5ea909cd`.

Incluye 20 tablas, las seis migraciones con sus checksums, 55 mercancías, 71 recetas, 1418 cantidades de materiales, un administrador y la auditoría de la carga del catálogo. Sistemas, instalaciones, entregas y listas comienzan vacíos. Conserva las cinco observaciones AFEC y las 66 referencias comunitarias separadas, como se explica en [SOURCES.md](../resources/catalogue/SOURCES.md).

El administrador es `admin`; su contraseña exclusiva se entrega en `ACCESO_ADMINISTRADOR.txt` en la misma carpeta privada. El SQL contiene únicamente su hash de contraseña. Ambos archivos quedan fuera de Git. No copiar estas credenciales a documentación pública ni importar las cuentas de la demo.

## Importar en Hostinger

1. Abrir hPanel → Bases de datos → phpMyAdmin de `u969616855_afeclogisitc`.
2. Seleccionar esa base en el panel izquierdo y confirmar que sigue vacía. No crear otra base ni cambiar el nombre del archivo de configuración que ya se cargó. Si aparecen tablas, detener la instalación inicial y revisar el estado; este paquete no actualiza bases existentes.
3. Abrir **Importar** y seleccionar `AFEC_Logistica_instalacion_inicial.sql` desde este PC. Formato SQL y codificación UTF-8. Importar el archivo completo en una única operación; no activar la opción de interrupción parcial ni pegar bloques separados.
4. Ejecutar la importación. Debe terminar sin errores y mostrar `AFEC: INSTALACION COMPLETADA`. Si muestra `IMPORTACION OMITIDA`, comprobar base seleccionada, tablas existentes, versión del motor y bloqueo de otra migración. Requisitos: MySQL 8.0.16+ o MariaDB 10.11+.
5. Abrir `https://logistica.afec.space/`. Debe aparecer el panel de sistemas vacío. Entrar en `https://logistica.afec.space/?page=login` con los datos de `ACCESO_ADMINISTRADOR.txt`.
6. Abrir **Facilities y recetas** para consultar las 71 referencias ya precargadas. Después, **Usuarios → Crear usuario** para dar accesos de Operador o Comandante. El catálogo no necesita cargarse otra vez ni debe ejecutarse `create-admin.php` después de importar este paquete.
7. Desde **Usuarios**, usar **Restablecer contraseña** para elegir otra contraseña propia cuando corresponda. Iniciar sesión nuevamente después del cambio. Flujos de CRUD en [GUIA_USO.md](GUIA_USO.md).

La configuración MySQL sigue en `config/config.local.php` en la raíz privada de la instalación. Si después de importar persiste el 503, buscar la nueva referencia en `storage/logs/app.log`: el esquema inicial no corrige una conexión rechazada, extensiones PHP ausentes o un archivo de configuración ubicado en otra carpeta.

## Alcance, pruebas y rollback

Herramienta CLI `tools/export-install.php`, prueba reproducible `tests/install_package.py`, este documento y enlaces en las guías. No modifica las migraciones originales, la API, los datos de la demo, Sheets ni la base remota. Solo exporta una fuente local `afec_test_*` en entorno `test`, con un administrador y el catálogo inicial; rechaza fuentes con datos de negocio.

El SQL ejecuta las sentencias de esquema y datos únicamente cuando la base seleccionada está vacía, el motor cumple la versión mínima y se obtiene el mismo bloqueo de base usado por el migrador. Las comprobaciones y los datos del paquete usan una sesión SQL continua. No crea/elimina bases ni borra tablas. Se utilizan `PREPARE`/`EXECUTE`, sin rutinas almacenadas ni permisos de creación de procedimientos: [documentación de MySQL](https://dev.mysql.com/doc/refman/8.4/en/sql-prepared-statements.html).

Validado en MariaDB 11.4.11 local y PHP 8.4.26: importación completa, autenticación con la contraseña exclusiva, lectura pública, catálogo de 71 recetas y 55 mercancías, creación de sistema/facility con copia completa de objetivos y alta de operador. Reimportación rechazada con hash de esquema/datos idéntico y base ajena no vacía preservada. Migrador reconoce los seis checksums y no propone cambios. Exportación rechazada tras añadir un proyecto. 19 comprobaciones del paquete; prueba no ejecutada en MySQL ni en Hostinger.

```text
python tests/install_package.py --php RUTA_PHP --config CONFIG_PRIVADA_TEST --mysql RUTA_CLIENTE_MYSQL --output-dir .local/deployment/UNA_CARPETA_NUEVA
```

La prueba crea bases nuevas con nombres aleatorios `afec_test_install_*` en loopback, sin resetear ninguna existente. Python y los ejecutables locales se usan solo para empaquetado/pruebas, nunca en producción. Cada ejecución genera un administrador y un SQL exclusivos; no sustituir el archivo entregado por un paquete de prueba diferente conservando la contraseña anterior.

DDL puede confirmar cambios antes de terminar el archivo. Si ocurre un error, conservar el mensaje y revisar las tablas antes de repetir; no eliminar datos para forzar una reimportación. Una instalación parcial no vacía se omite. Para rollback del código, retirar únicamente exportador/documentación; las tablas importadas y su registro/checksums permanecen válidos para la aplicación actual. La restauración de una base real requiere un respaldo revisado y considerar cualquier dato posterior. No se ha ejecutado el archivo en Hostinger desde este entorno.
