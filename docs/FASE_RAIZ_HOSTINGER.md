# Adaptación para desplegar la raíz completa

Fecha: 14/10/2026. Preparada y verificada localmente; no publicada ni aplicada en la base de Hostinger.

## Alcance y archivos

El propietario confirmó que la raíz del repositorio es el document root de Hostinger. Se agregan `index.php`, `api/index.php` y `.htaccess`, y se adapta la generación de URLs de recursos en `App`/layout. Las entradas delegan en los controladores actuales; el servicio y la API conservan la misma lógica.

El sitio usa `/` y `/api/index.php`, con CSS/JS en `/public/assets/`. La protección HTTP permite solo las entradas y archivos públicos conocidos. Todo lo demás, incluidos handoff/ZIP, Git, SQL, documentación, código privado, configuración, almacenamiento y herramientas, devuelve 403. No se cambia el esquema ni se incluyen credenciales en Git.

## Pruebas

Se agregó un modo de prueba con Apache real y PHP FastCGI. La configuración de ensayo se genera dentro de `.local/`, escucha únicamente en loopback y no cambia la configuración instalada de Apache. Las pruebas siguen exigiendo base `afec_test_*`, host local y entorno test antes de cualquier reset.

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

Entorno usado: Apache 2.4.58 existente en el equipo, PHP 8.4.26 FastCGI y MariaDB 11.4.11/InnoDB. Resultado: 12 comprobaciones SQL y 142 HTTP, migración simulada y reaplicación aprobadas. Incluye el CRUD, permisos, rollback y concurrencia de la fase 2 a través de la entrada raíz, acceso a CSS/JS y bloqueo efectivo de 19 rutas adicionales de repositorio, más configuración y bootstrap privados.

El modo `public/` también pasó la misma suite sin `--webroot repository`: 12 comprobaciones SQL y 122 HTTP. No se necesita Apache para ese modo. El servidor integrado PHP nunca se usa para exponer la raíz: ignora `.htaccess`.

El navegador integrado rechazó la reapertura de la vista previa local durante la adaptación raíz; no se eludió el bloqueo. La comprobación raíz se realizó por HTTP contra Apache; la validación visual móvil/escritorio del CRUD de fase 2 ya estaba realizada antes de esta adaptación.

## Instalación y límites

La raíz ya tiene entradas funcionales; para ponerla en marcha en Hostinger son necesarios PHP/extensiones compatibles, reglas `.htaccess` activas, HTTPS, configuración privada persistente, aplicación revisada del esquema y creación del primer administrador. La configuración de este PC está ignorada: Git no la transfiere. No se ejecutan migraciones o instalaciones desde HTTP ni automáticamente al hacer push.

No se afirma compatibilidad comprobada con el servidor remoto. Verificar allí los accesos públicos y 403 de archivos privados siguiendo [DESPLIEGUE.md](DESPLIEGUE.md). El bloqueo depende de que Apache/LiteSpeed aplique `.htaccess`; un servidor que lo ignore requiere una disposición distinta.

## Riesgo y rollback

Las rutas públicas nuevas son explícitas; agregar un recurso público futuro requiere actualizar la lista permitida y sus pruebas. Un error de reglas puede impedir servir el sitio, por lo que se valida la configuración y se conserva una versión anterior del despliegue real antes de cambiarla.

Punto previo de código: `26e7c2e` (CRUD de fase 2). En desarrollo, revertir esta adaptación devuelve el modo con document root `public/` sin alterar MySQL. Para un sitio real con la raíz expuesta, restaurar únicamente un paquete previamente verificado para ese mismo layout; conservar las entradas y protección HTTP mientras se revierte la lógica de negocio. No eliminar datos, configuración ni auditoría.
