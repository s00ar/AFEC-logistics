# Despliegue PHP en Hostinger

Crear un sitio personalizado PHP/HTML vacío. PHP 8.4 recomendado; activar `pdo_mysql` y `mbstring`. HTTPS obligatorio en producción. No se usan package.json, npm, Node, Python ni procesos permanentes en producción.

## Despliegue confirmado: raíz del repositorio

El propietario confirmó que Hostinger despliega la carpeta raíz completa del repositorio. No mover archivos ni cambiar el document root a `public/` para esta instalación. En la raíz quedan `index.php` (entrada HTML), `api/index.php` (entrada JSON), `.htaccess` y todas las carpetas del proyecto. Las dos entradas delegan en los controladores existentes; no hay lógica de negocio duplicada.

El HTML de esta disposición carga `/public/assets/css/app.css`, `/public/assets/js/{app,http,planning,logistics}.js` y `/public/assets/img/afec-logistica.png`. `.htaccess` permite únicamente esas rutas, `/`, `/index.php` y `/api/index.php`; bloquea cualquier otra ruta con 403. Las carpetas privadas conservan además sus reglas locales. No sirve documentación, SQL, tests, herramientas CLI, ZIP/handoff, Git, archivos de configuración ni almacenamiento.

Requisito: Apache/LiteSpeed compatible con `mod_rewrite`, autorización de Apache 2.4 y overrides `.htaccess`. Una configuración que ignore `.htaccess` no es compatible con este despliegue. El servidor integrado de PHP ignora esas reglas y solo se usa con `-t public` en desarrollo. No quitar las reglas de protección para resolver un error de servidor.

Mantener `app.base_path=''` al servir el dominio desde su raíz; si se instala bajo un subdirectorio, configurar ese prefijo explícitamente. No usar `/public` como base_path en la disposición confirmada.

La alternativa con document root aislado `public/` continúa funcionando y usa `/assets/...`. El código distingue la entrada raíz mediante una constante interna, sin usar parámetros del cliente.

## Configuración privada persistente

Copiar `config/config.example.php` a `config/config.local.php` en el servidor y completar los valores privados, o usar `AFEC_CONFIG_FILE` para una ruta externa al webroot. El archivo local está ignorado por Git y protegido por `.htaccess`; verificar que el despliegue conserva archivos locales no versionados. Las variables `AFEC_DB_HOST`, `AFEC_DB_PORT`, `AFEC_DB_NAME`, `AFEC_DB_USER`, `AFEC_DB_PASSWORD` prevalecen sobre el archivo. Nunca se aceptan rutas de configuración de parámetros HTTP.

El archivo privado de este PC no está en Git; debe transferirse de forma privada al hosting. No reutilizar la contraseña MySQL como contraseña de la aplicación. Producción exige HTTPS y cookies Secure.

Crear `storage/sessions` y `storage/logs` en ubicación privada, escribible por PHP, con permisos restrictivos. No dar permisos de escritura a todo el repositorio. Configurar `app.base_path` si se sirve bajo un subdirectorio.

## Esquema y primer administrador

`database/migrations/001_initial.sql` es la estructura inicial; `002_user_management.sql`, `003_logistics_lists.sql` y `004_password_operation_verifier.sql` habilitan cuentas y logística de fase 3. No contienen datos reales ni cuentas predeterminadas; solo un mutex técnico de cuentas. `database/migration_registry.sql` define el registro de versiones. Antes de aplicarlos verificar motor compatible (MySQL 8.0.16+ o MariaDB 10.11+), base vacía o estado registrado, InnoDB, backup y checksums. Registrar cada versión y checksum en `schema_migrations` mediante el migrador.

MySQL puede confirmar DDL implícitamente: un rollback de transacción no deshace una instalación parcial. No repetir CREATE sobre una instalación incompleta; revisar el estado y restaurar el backup conforme al procedimiento acordado.

La herramienta CLI `tools/migrate.php` simula por defecto sin escribir. `--apply` aplica versiones pendientes y registra checksum/estado; en producción se requiere `--allow-production` además de backup y autorización. La estructura no se aplica automáticamente al hacer push. `tools/preflight.php` comprueba conexión/extensiones sin imprimir credenciales.

La fase 4 requiere también `005_facility_recipes.sql`: catálogo editable de recetas y procedencia de sus objetivos en instalaciones. No precarga cantidades del juego sin fuente confirmada. El código nuevo requiere ese esquema antes de habilitar la aplicación; planificar despliegue y migración con respaldo, sin ejecutarlos automáticamente. Las versiones previas conservan los datos si se revierte código, pero debe mantenerse 005 y su registro/checksum al preparar el rollback.

La fase 5 requiere `006_recipe_provenance.sql` y el directorio completo `resources/catalogue/`, con fuentes/checksums intactos. El CSV original conserva sus bytes mediante `.gitattributes`; JSON usa LF. El dataset es privado por HTTP y se lee desde PHP, sin consultas externas ni Python en producción. Aplicar 006 como parte de la secuencia revisada, nunca automáticamente por push.

Después de crear el administrador y aplicar el esquema, cargar las 71 referencias desde **Facilities y recetas → Cargar catálogo inicial**, con revisión y confirmación. Alternativa CLI: `php tools/seed-recipes.php --user=ADMIN` simula; `--apply --allow-production` aplica únicamente con backup y autorización de producción. Las mercancías existentes y recetas previamente cargadas se preservan; el paquete no importa proyectos ni movimientos de Sheets. Los operadores pueden ajustar las recetas después. Conservar 006 y sus registros/checksums al revertir código; no eliminar fuentes cargadas para repetir.

El primer administrador se crea mediante una herramienta CLI privada:

```text
php tools/create-admin.php --username=admin --name="Administrador AFEC" --password-file=RUTA_PRIVADA
```

Guardar el archivo de contraseña fuera del webroot y retirarlo después. El comando se niega a crear otro administrador si ya existen cuentas. Si el hosting no dispone de CLI/SSH, se preparará una inicialización alternativa revisable; no se publicará un instalador permanente.

## Verificación y rollback

Antes de habilitar el sitio: comprobar `/`, `/index.php`, API, CSS, logo y módulos JavaScript; lectura anónima, login/logout, CRUD de operador/usuarios y privacidad de listas personal/flota. Solicitar directamente `/config/config.local.php`, `/database/migrations/001_initial.sql`, `/.git/HEAD`, `/storage/`, `/tools/preflight.php`, `/README.md` y el ZIP de handoff: deben devolver 403 sin contenido privado. Si alguna ruta entrega contenido, retirar acceso público y revisar la configuración del servidor. La configuración, esquema y primer administrador son pasos necesarios antes de usar la aplicación; un push no incluye secretos ni inicializa MySQL.

El servidor y la base remotos no se han verificado desde este PC. No ejecutar automáticamente migraciones ni importaciones al desplegar código. Las pruebas locales de Apache no sustituyen la comprobación del servidor real.

La fase 3 requiere migraciones 002/003/004 y habilita cuentas, entregas/correcciones, estados, listas y abastecimiento. El rollback de una instalación real restaura una versión de archivos previamente verificada para la misma disposición raíz y conserva configuración privada, base y backups. Mantener las migraciones aplicadas y sus checksums; no usar un migrador antiguo que las desconozca. No restaurar una versión sin entrada raíz o bloqueo HTTP en un webroot que exponga todo el repositorio. Nunca eliminar registros reales para repetir una instalación. Ensayar restauración aislada antes de futuras migraciones.

La adaptación raíz está separada del commit CRUD `26e7c2e`: en desarrollo puede revertirse para volver al modo `public/`, manteniendo datos y auditoría. Todavía no hay despliegue real ejecutado por el agente.

Referencias de servidor: [banderas de mod_rewrite en Apache](https://httpd.apache.org/docs/2.4/rewrite/flags.html), en particular `[F]` para denegar solicitudes y `[L]` para detener la evaluación de la regla.
