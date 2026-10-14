# Fase 1 — base y lectura pública

Fecha: 14/10/2026. Estado: implementada y verificada localmente. No desplegada en Hostinger.

## Alcance terminado

- Bootstrap/autoload PHP sin framework, configuración privada y variables de entorno.
- PDO MySQL/MariaDB con SQL preparado; modelo inicial de 15 tablas incluyendo registro de migraciones y limitación de login.
- Sesiones con CSRF, regeneración, caducidad y revalidación de usuario/rol. Revocación por usuario inactivo o cambio de `auth_version`.
- Consulta anónima de sistemas, instalaciones, materiales y progreso. Cuenta administrativa protegida y listado de usuarios solo para administrador.
- Saldos derivados del ledger; agregación por material sin compensar faltantes con sobrantes. Cantidades grandes devueltas como texto decimal. Saldos negativos visibles como incidencia.
- Panel responsive, formulario de login/logout, tabla desplazable en móvil, HTML escapado y respuestas JSON normalizadas.
- Herramientas CLI privadas: preflight sin secretos, migración con simulación/registro de estado/checksum y creación transaccional del primer administrador.

El autor de un futuro movimiento se guardará en `created_by_user_id` y el comandante en `commander_id`. El esquema lo separa; todavía no hay endpoint para registrar entregas.

## Validación ejecutada

Entorno: PHP 8.4.26, MariaDB 11.4.11 con InnoDB, base local `afec_test_foundation`, puerto loopback 33077. No se utilizaron los datos ni las credenciales de Hostinger en las pruebas.

- 12 comprobaciones SQL: motor/tablas, FKs, unicidad por requerimiento y operación, cantidades/roles, atribución separada y rollback transaccional.
- 37 comprobaciones HTTP: lectura pública, saldos independientes, agregación, archivo, saldo negativo, precisión mayor a JavaScript Number, SQLi/XSS, métodos, JSON, límites de payload, CSRF, login, roles, cookies, revocación, logout y throttle.
- Simulación de migración y reaplicación de una versión ya aplicada sin duplicar estructuras.
- Aplicación inicial desde una base vacía de demostración mediante el migrador y creación del primer administrador.
- Navegador: panel/instalaciones en viewport de escritorio y móvil, formulario móvil real, navegación de administración, logout desde una página privada y vuelta al panel público. Sin errores ni warnings de consola en ese flujo.
- Sintaxis PHP y JavaScript revisada; ficheros privados fuera del document root de prueba.

Las primeras ejecuciones fueron interrumpidas porque el antivirus bloqueó/quitaron scripts CLI. El propietario confirmó la alerta y autorizó los archivos en su equipo. Se restauraron los scripts y las pruebas completas pasaron después. No se cambió la configuración del antivirus desde el agente.

Ejemplo de ejecución con herramientas existentes:

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST
```

La configuración de pruebas debe seleccionar entorno `test`, host `127.0.0.1`/`localhost`, base con prefijo `afec_test_`, puerto del servidor local y cookie Secure desactivada solo para ese entorno. Los fixtures se niegan a resetear una base que no cumpla estas condiciones. No ejecutar con una base real.

Los runtimes portátiles y los datos/sesiones/capturas de demostración están en rutas ignoradas. La vista previa local usa `afec_test_preview`, independiente de la base de pruebas automatizadas. Todas sus entidades y aportes son sintéticos.

## Límites y pendientes

- No está probado el servidor ni el motor real de Hostinger; `localhost:3306` se configura solo para el hosting. La configuración privada de este PC está ignorada y no viajará en un push.
- No hay altas/ediciones funcionales, carga/reversión/corrección de movimientos, administración editable ni importador.
- Los listados de esta primera fase tienen límites defensivos (100 sistemas, 200 instalaciones/usuarios); se incorporará paginación antes de ampliar los volúmenes. No se afirma validación de rendimiento con datos reales.
- Fórmula de progreso y política de reversión del esquema siguen propuestas provisionales. No se usarán para ejecutar escrituras reales sin cerrar las decisiones.
- No se auditaron Apps Script, datos de Sheets ni Terminal AFEC; siguen faltando sus fuentes.
- Las pruebas de entrega concurrente, compensación e importación se ejecutarán al implementar esos servicios. Las pruebas actuales de constraints no sustituyen esos flujos.
- No hay protección criptográfica frente a un administrador privilegiado de MySQL; las futuras mutaciones de ledger se limitarán a servicios auditados y permisos SQL posibles en el hosting.

## Rollback y siguiente fase

El punto de restauración es el commit de esta fase. No se modificaron Sheets ni base/sitio de Hostinger. La base local de ensayo es descartable; datos reales futuros no se eliminarán para volver a una versión anterior.

Siguiente fase: altas/ediciones autorizadas de sistemas, instalaciones, catálogo, comandantes/carriers y objetivos con revisiones; formularios y endpoints idempotentes. Después, entregas de terceros con transacción y atribución obtenida de sesión. Mantener pequeños hitos verificables.
