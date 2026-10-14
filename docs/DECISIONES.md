# Decisiones y estado

## Confirmado por el propietario — 14/10/2026

- Autorizado comenzar el desarrollo por fases del monolito PHP/PDO/MySQL, HTML/CSS/JS vanilla.
- Consulta pública sin login de sistemas, instalaciones, materiales y progreso. Toda escritura requiere autenticación y autorización de servidor.
- Operadores autorizados pueden registrar entregas en nombre de otros comandantes.
- Operadores y administradores pueden crear, consultar, editar y archivar/restaurar sistemas e instalaciones, y crear, editar, retirar/restaurar objetivos de materiales. Las bajas conservan registros, revisiones y movimientos.
- En movimientos, `commander_id` identifica a quien entrega y `created_by_user_id` a quien registra. Este último siempre proviene de la sesión; el cliente no puede imponerlo. Fecha, cantidad y pareja instalación/material corresponden al movimiento.
- MySQL en Hostinger usa `localhost:3306`. Los valores privados están en `config/config.local.php`, ignorado por Git. `localhost` se refiere al servidor donde ejecuta PHP; no permite desde el PC alcanzar la base de Hostinger.

## Pendiente para las siguientes fases

- Permisos de correcciones, administración de comandantes/carriers, catálogo y usuarios. Por ahora, solo el administrador da de alta materiales de catálogo.
- Sobreentregas/sobrantes, límites de cantidad, reversión completa/parcial y saldos negativos.
- Confirmar que las cargas son entregas recibidas por la instalación. Embarques en carrier serían otro flujo y no se suman como entregas.
- Fórmula global propuesta: cantidad cubierta limitada por objetivo, ponderada por unidades; objetivos cero sin porcentaje; archivadas excluidas del progreso operativo.
- Tipos, tiers y estados; identidades/aliases y deduplicación.
- Fuentes Apps Script, export completo e historial de Sheets. Terminal AFEC sigue sin fuentes verificables.
- URL y versión real de PHP/MySQL en Hostinger, y verificación de la protección HTTP en la disposición raíz confirmada. Corte e importación requieren aprobación específica.

## Fase 1

Alcance: esquema inicial, configuración, sesiones/CSRF, roles, lectura pública responsive y consulta de cuentas para administrador. No incluye todavía altas/ediciones funcionales, registro de entregas, reversiones, importación ni publicación.

Las restricciones de reversión completa del esquema son provisionales; no hay servicio de reversión habilitado. El modelo puede ajustarse mediante una migración posterior antes del primer uso real. Los datos de prueba son sintéticos y locales.

## Fase 2

CRUD de planificación para operadores, archivo/restauración y revisiones auditadas, transacciones, control de versiones, idempotencia y formularios responsive. Listas de sistemas, instalaciones e historial paginadas de 25 en 25. No incluye aún entregas ni importación. Resultado en [FASE_2.md](FASE_2.md).

## Disposición de despliegue confirmada

El repositorio completo se despliega desde su raíz en Hostinger. La entrada HTML y la API deben funcionar desde allí, con recursos públicos y bloqueo HTTP de archivos privados. Se conserva el modo de desarrollo con document root `public/`. La configuración privada, base inicial y primer administrador siguen siendo pasos de instalación; no se incluyen secretos ni se ejecutan migraciones desde HTTP o por push.
