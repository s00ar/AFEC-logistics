# AFEC Logística

Aplicación de colonización de Elite Dangerous. Monolito PHP + PDO MySQL/MariaDB, HTML/CSS y JavaScript vanilla, compatible con hosting compartido. Sin build ni Node en producción.

## Estado

Fases 1 y 2 implementadas y verificadas localmente: autenticación, sesiones/CSRF, roles, esquema relacional, panel público y CRUD de sistemas, instalaciones y objetivos para operadores/administradores, con archivo/restauración e historial. Materiales nuevos y consulta de cuentas solo por administrador. Alcance y resultados en [docs/FASE_1.md](docs/FASE_1.md) y [docs/FASE_2.md](docs/FASE_2.md).

Todavía no están habilitados registro/reversión de entregas, administración editable de cuentas, comandantes/carriers ni importación de Sheets. No se ha desplegado desde este entorno ni aplicado el esquema en Hostinger.

La consulta pública y los aportes registrados por operadores en nombre de terceros están confirmados en [docs/DECISIONES.md](docs/DECISIONES.md). El resto de políticas sigue documentado como pendiente. El material de origen permanece intacto en `AFEC_Logistica_Handoff_Codex/`; el diagnóstico está en `ANALISIS_AFEC_LOGISTICA.md`.

## Estructura

```text
public/        HTML/API y recursos públicos
src/           Configuración, sesión, servicios y repositorios PDO
templates/     Presentación HTML
config/        Ejemplo y configuración local ignorada
database/      SQL versionado
storage/       Sesiones y logs privados (ignorados)
tools/         Preparación privada por CLI
tests/         Pruebas contra una base local aislada
docs/          Decisiones y despliegue
```

## Requisitos y configuración

PHP 8.4 recomendado, extensiones `pdo_mysql` y `mbstring`; MySQL 8.0.16+ o MariaDB 10.11+ con InnoDB. Para producción, HTTPS y un document root que exponga solo `public/`.

Copiar `config/config.example.php` a `config/config.local.php` y completar la conexión, o usar `AFEC_CONFIG_FILE` para un archivo privado fuera del webroot. Nunca subir ese archivo a Git. `localhost` en la configuración de Hostinger solo funciona cuando PHP se ejecuta allí.

Para desarrollo local, una configuración separada debe usar una base local, `environment=development` y `session.cookie_secure=false`. El servidor integrado se inicia desde el proyecto:

```text
php -S 127.0.0.1:8080 -t public
```

La producción no permite desactivar cookies Secure. El navegador recibe solo JSON/HTML, sin credenciales de MySQL.

## API de esta fase

Entrada: `public/api/index.php?route=...` (funciona sin reglas de rewrite).

| Método | route | Acceso |
| --- | --- | --- |
| GET | `session` | Sesión propia y token CSRF |
| POST | `login`, `logout` | Token CSRF; login devuelve errores genéricos y limita intentos |
| GET | `systems` | Público |
| GET | `system&id=ID` | Público; instalaciones y requerimientos |
| GET | `installation&id=ID` | Público; metadatos de instalación |
| GET | `commodities` | Catálogo activo público |
| POST | `systems` | Operador/admin; crear sistema |
| PATCH / DELETE | `system&id=ID` | Operador/admin; editar / archivar |
| POST | `system/restore&id=ID` | Operador/admin; restaurar |
| POST | `installations&system_id=ID` | Operador/admin; crear instalación |
| PATCH / DELETE | `installation&id=ID` | Operador/admin; editar / archivar |
| POST | `installation/restore&id=ID` | Operador/admin; restaurar |
| PUT / DELETE | `requirement&installation_id=ID&commodity_id=ID` | Operador/admin; guardar/restaurar / retirar objetivo |
| GET | `planning/history&type=system|installation|requirement&id=ID` | Operador/admin; historial; objetivo requiere `commodity_id` |
| POST | `commodities` | Administrador; alta de material |
| GET | `admin/users` | Administrador |

IDs y cantidades se devuelven como cadenas decimales para conservar precisión. Las operaciones nuevas de entrega usarán `commander_id` y `created_by_user_id`, separado del usuario autenticado que las registra.

Las escrituras de planificación requieren `X-CSRF-Token` e `Idempotency-Key` (16–100 caracteres ASCII alfanuméricos, `_` o `-`). Editar/retirar/restaurar requiere la `version` recibida al consultar y un `reason`. Un objetivo nuevo usa versión `"0"` y `required_quantity` como cadena decimal; las bajas requieren `confirmed=true`. Repetir exactamente la operación con la misma clave devuelve el resultado guardado. Los listados admiten `list_page`; sistemas e instalaciones archivadas requieren operador/admin e `include_archived=1`.

## Pruebas y despliegue

Las pruebas están en `tests/`: fixtures protegidos para una base `afec_test_*` en loopback y entorno `test`, verificaciones SQL y recorrido HTTP. Python es únicamente un ejecutor de pruebas; no forma parte del backend ni del despliegue.

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST
```

Simular/aplicar estructura con `php tools/migrate.php` / `php tools/migrate.php --apply`. En producción se exige además `--allow-production` y un backup revisado. El migrador detecta versiones modificadas o incompletas; no intenta revertir automáticamente DDL.

La guía de preparación del servidor y rollback está en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md). No conectar despliegues automáticos a producción antes de validar layout, configuración privada, esquema y respaldos.
