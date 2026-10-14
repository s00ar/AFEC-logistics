# Despliegue PHP en Hostinger

Crear un sitio personalizado PHP/HTML vacío. PHP 8.4 recomendado; activar `pdo_mysql` y `mbstring`. HTTPS obligatorio en producción. No se usan package.json, npm, Node, Python ni procesos permanentes en producción.

## Archivos y configuración

Preferir document root `public/`, dejando `src`, `templates`, `config`, `database`, `storage` y `tools` fuera. Si Hostinger usa `public_html` fijo, copiar allí únicamente los archivos públicos y ajustar las dos rutas de bootstrap a la ubicación privada verificada.

Un despliegue Git de todo el repositorio en `public_html` necesita revisar aislamiento y reglas del servidor. No asumir que `.htaccess` garantiza protección: comprobar solicitudes directas a configuración, almacenamiento y fuentes en el sitio real antes de habilitarlo.

Copiar `config/config.example.php` a un archivo privado. Puede usarse `config/config.local.php` cuando el repositorio queda fuera del webroot o `AFEC_CONFIG_FILE` para una ruta externa. Las variables `AFEC_DB_HOST`, `AFEC_DB_PORT`, `AFEC_DB_NAME`, `AFEC_DB_USER`, `AFEC_DB_PASSWORD` prevalecen sobre el archivo. Nunca se aceptan rutas de configuración de parámetros HTTP.

El archivo privado de este PC no está en Git; debe transferirse de forma privada al hosting. No reutilizar la contraseña MySQL como contraseña de la aplicación. Producción exige HTTPS y cookies Secure.

Crear `storage/sessions` y `storage/logs` en ubicación privada, escribible por PHP, con permisos restrictivos. No dar permisos de escritura a todo el repositorio. Configurar `app.base_path` si se sirve bajo un subdirectorio.

## Esquema y primer administrador

`database/migrations/001_initial.sql` es únicamente la estructura; no contiene datos reales ni cuentas predeterminadas. `database/migration_registry.sql` define el registro técnico de versiones. Antes de aplicarlos verificar motor compatible (MySQL 8.0.16+ o MariaDB 10.11+), base vacía, InnoDB, backup y checksums. Registrar la versión aplicada y el checksum del SQL en `schema_migrations`.

MySQL puede confirmar DDL implícitamente: un rollback de transacción no deshace una instalación parcial. No repetir CREATE sobre una instalación incompleta; revisar el estado y restaurar el backup conforme al procedimiento acordado.

La herramienta CLI `tools/migrate.php` simula por defecto sin escribir. `--apply` aplica versiones pendientes y registra checksum/estado; en producción se requiere `--allow-production` además de backup y autorización. La estructura no se aplica automáticamente al hacer push. `tools/preflight.php` comprueba conexión/extensiones sin imprimir credenciales.

El primer administrador se crea mediante una herramienta CLI privada:

```text
php tools/create-admin.php --username=admin --name="Administrador AFEC" --password-file=RUTA_PRIVADA
```

Guardar el archivo de contraseña fuera del webroot y retirarlo después. El comando se niega a crear otro administrador si ya existen cuentas. Si el hosting no dispone de CLI/SSH, se preparará una inicialización alternativa revisable; no se publicará un instalador permanente.

## Verificación y rollback

Comprobar lectura anónima de progreso, login/logout, administración protegida y ausencia de acceso HTTP a archivos privados. El servidor y la base remotos no se han verificado desde este PC. No ejecutar automáticamente migraciones ni importaciones al desplegar código.

Esta fase no habilita carga de entregas ni edición de objetivos. El rollback de código vuelve al commit anterior conservando base y backups. Nunca eliminar registros reales para repetir una instalación. Ensayar restauración aislada antes de futuras migraciones.
