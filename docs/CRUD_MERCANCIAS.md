# CRUD de mercancías — 14/10/2026

## Alcance, riesgo y rollback

Administración del catálogo global: alta, nombres/código, alias, desactivación, reactivación e historial. Solo Administrador, siguiendo los permisos previos del alta. Servicios, repositorio PDO, controlador JSON y presentación separados. Escrituras con CSRF, idempotencia, transacción y control de versión; bloqueo compartido con la importación de recetas.

Archivos: `CommodityRepository.php`, `CommodityService.php`, `CommodityController.php`, página `templates/commodities.php`, formularios/JavaScript de planificación y rutas HTML/API. Migración aditiva `007_commodity_aliases.sql`; paquete para phpMyAdmin `database/upgrades/AFEC_actualizar_mercancias.sql`, generado por `tools/build-commodity-upgrade.py`.

Los alias significan nombres o códigos alternativos de un mismo ID. No se fusionan IDs ni se suman objetivos o movimientos. Esta interpretación conservadora no presupone reglas para fusionar duplicados. Renombrar conserva las identidades anteriores por defecto; puede desmarcarse explícitamente para corregir una identidad errónea. Los nombres/códigos/alias quedan reservados incluso en mercancías desactivadas. La importación inicial de recetas reconoce los alias persistidos.

Riesgo: asignar equivalencias incorrectas; revisar antes de guardar y usar el historial para reconstruir cambios. Desactivar excluye la selección nueva, conservando requerimientos, entregas, recetas y abastecimiento existentes. No se modifica el ledger. La ampliación añade un campo nullable con validación JSON; no cambia filas de negocio.

Rollback: volver al código anterior conservando la columna y el registro/checksum 007. No borrar movimientos ni restablecer la instalación inicial. Para recuperar nombres/estado, editar o reactivar con motivo; los cambios quedan auditados. Un fallo parcial de DDL requiere revisar el estado antes de reintentar.

## Instalación

El propietario aplicará el SQL desde phpMyAdmin. El código detecta el esquema previo y mantiene lectura, alta sin alias y cambios de estado; los alias y renombrados que conservan identidades requieren 007. El push no ejecuta migraciones. Pasos en [DESPLIEGUE.md](DESPLIEGUE.md).

## Pruebas reproducibles

En una base local aislada `afec_test_*`, ejecutar:

```text
python tests/run.py --php RUTA_PHP --config CONFIG_TEST --webroot repository --apache-root RUTA_APACHE
python tests/commodity_upgrade.py --php RUTA_PHP --mysql RUTA_CLIENTE_MYSQL --config CONFIG_TEST --initial-sql SQL_INICIAL_001_006
```

La suite cubre permisos, CSRF, repetición idempotente, versiones obsoletas, nombres escapados, conflictos entre códigos/nombres/alias, conservación de balances y abastecimiento, baja/restauración, historial y rollback ante fallo de auditoría. Dos administradores independientes compiten por el mismo alias: solo uno puede crearlo. Las pruebas de actualización importan el paquete exacto sobre 001–006, comprueban preservación de datos, repetición y rechazo de prerrequisitos inválidos. PHP FastCGI del harness local no limita artificialmente el número de solicitudes.

Resultado local: 12 comprobaciones SQL y 380 HTTP aprobadas con Apache y `.htaccess` reales; 8 comprobaciones de actualización aprobadas sobre el paquete de instalación previo. Lint PHP de los archivos modificados y comprobación de sintaxis de `planning.js` aprobados. Se verificó que ambos archivos SQL nuevos devuelven 403 en el despliegue raíz local. La importación en la base real corresponde al propietario y sigue pendiente.

## Publicación verificada

Publicado `6c1baa2` en `main`. Hostinger verificado el 14/10/2026: panel público, API de sistemas, login administrativo, página Mercancías y API administrativa responden 200. El JavaScript con versión servido coincide byte por byte con esta publicación. Ambos SQL nuevos devuelven 403. Se cerró la sesión de comprobación y no se modificaron mercancías reales.

La API devuelve `aliases_ready=false`: la base real todavía requiere la importación manual del paquete 007 por el propietario. No se declara completada esa migración remota.
