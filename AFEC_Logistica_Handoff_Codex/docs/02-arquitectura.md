# Arquitectura recomendada

Navegador -> HTTPS -> PHP (rutas HTML + API JSON en mismo dominio) -> PDO -> MySQL/MariaDB de Hostinger.

- `/public/index.php`: entrypoint. `/public/assets`: CSS y JS; `public/api/index.php` o router PHP único para `/api`.
- `/src`: servicios de aplicación, repositorios PDO, autorización, validación y lógica de negocio.
- `/config`: plantilla de configuración sin secretos, configuración real fuera del document root.
- `/database`: migraciones SQL versionadas y seeds no sensibles.
- `/tests`: pruebas unitarias/integración y comandos de verificación.
- El webroot debe apuntar a `/public`; si hosting compartido no permite cambiar document root, ajustar estructura con `.htaccess` y bloquear acceso a `config`, `src`, `database`, `vendor`.
- Emplear PDO prepared statements, transacciones, índices, claves foráneas, UTC para timestamps.
- Autenticación inicial propuesta: email/usuario + contraseña, sesión PHP con cookie `HttpOnly`, `Secure` bajo HTTPS, `SameSite=Lax`; password_hash/password_verify.
- Roles propuestos: `admin` (usuarios/catálogos/sistemas), `operator` (cargas y edición permitida), `viewer` (solo lectura). Verificar con usuario si visitantes anónimos pueden leer.
- Sesiones + comprobaciones CSRF para POST/PATCH/DELETE; limitar intentos de login; revalidar permisos en backend.
- Sin build React obligatorio. Evitar dependencia de un proceso Node en producción.

## Comparativa de alternativas consideradas
- PHP + MySQL + JS: un hosting, menor número de servicios; recomendado inicialmente.
- React/Vite + PHP API + MySQL: UI más estructurada si crece, pero añade build/dependencias; conservar como opción.
- React + Supabase: auth y Postgres administrados; servicio externo y aprendizaje adicional.
- Firebase: conocido por el usuario, pero document store exige modelar relaciones y agregaciones de modo distinto.

## Pendiente de verificación
Plan Hostinger: soporte PHP/MySQL, creación de BD/usuario, SSL, backups, cron, límites, ruta de document root y permisos. No asumir que un plan concreto ofrece características no confirmadas.
