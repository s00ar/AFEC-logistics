# AFEC Logística

Aplicación de colonización de Elite Dangerous. Monolito PHP + PDO MySQL/MariaDB, HTML/CSS y JavaScript vanilla, compatible con hosting compartido. Sin build ni Node en producción.

## Estado

Fases 1–5 implementadas y verificadas localmente: autenticación, sesiones/CSRF, roles, panel público, CRUD de planificación y cuentas, estados de construcción, entregas y retiros compensatorios, listas personal/flota y abastecimiento compartido con copia de nombres. Logo/paleta AFEC, animaciones CSS ligeras y catálogo editable de recetas para cargar automáticamente los objetivos de instalaciones. Snapshot inicial con 66 referencias comunitarias y 5 observaciones AFEC, carga idempotente y ajustes preservados. Alcance y pruebas en [docs/FASE_1.md](docs/FASE_1.md), [docs/FASE_2.md](docs/FASE_2.md), [docs/FASE_3.md](docs/FASE_3.md), [docs/FASE_4.md](docs/FASE_4.md) y [docs/FASE_5.md](docs/FASE_5.md).

Pendientes: importación completa de Sheets/Apps Script, clasificación de estructuras desconocidas y tiers no confirmados, reservas/cargas en tránsito, catálogo editable completo de mercancías y pantallas administrativas completas de comandantes/carriers. Se admite selección/alta de identidades al registrar entregas. El 14/10/2026 se publicó el código en `main` y Hostinger comenzó a servirlo en [logistica.afec.space](https://logistica.afec.space/): login y sesión responden 200, pero panel/sistemas responden 503 al acceder a la persistencia. No se verificó ni modificó la configuración privada o el esquema remoto. Estado y pasos pendientes en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md). Fuentes y límites del catálogo en [resources/catalogue/SOURCES.md](resources/catalogue/SOURCES.md).

La consulta pública y los aportes registrados por operadores en nombre de terceros están confirmados en [docs/DECISIONES.md](docs/DECISIONES.md). El resto de políticas sigue documentado como pendiente. El material de origen permanece intacto en `AFEC_Logistica_Handoff_Codex/`; el diagnóstico está en `ANALISIS_AFEC_LOGISTICA.md`.

La adaptación para desplegar la raíz completa está verificada localmente con Apache. Alcance, requisitos y pruebas en [docs/FASE_RAIZ_HOSTINGER.md](docs/FASE_RAIZ_HOSTINGER.md); instalación en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

Pasos de acceso y flujos actuales en [docs/GUIA_USO.md](docs/GUIA_USO.md). La propuesta original se conserva en [docs/PROPUESTA_FASE_3.md](docs/PROPUESTA_FASE_3.md); las decisiones posteriores aprobadas están en [docs/DECISIONES.md](docs/DECISIONES.md).

## Estructura

```text
public/        HTML/API y recursos públicos
index.php      Entrada HTML cuando se despliega la raíz del repositorio
api/           Entrada JSON para el despliegue desde la raíz
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

PHP 8.4 recomendado, extensiones `pdo_mysql` y `mbstring`; MySQL 8.0.16+ o MariaDB 10.11+ con InnoDB. Para producción, HTTPS. El despliegue de Hostinger usa la raíz completa del repositorio: `index.php`, `api/index.php` y `.htaccess` están preparados para esa disposición. El servidor debe aplicar las reglas Apache/LiteSpeed de `.htaccess`; solo las entradas de aplicación y CSS/JavaScript conocidos son accesibles por HTTP. También se conserva la opción de un document root aislado en `public/`.

Copiar `config/config.example.php` a `config/config.local.php` y completar la conexión, o usar `AFEC_CONFIG_FILE` para un archivo privado fuera del webroot. Nunca subir ese archivo a Git. `localhost` en la configuración de Hostinger solo funciona cuando PHP se ejecuta allí.

Para desarrollo local, una configuración separada debe usar una base local, `environment=development` y `session.cookie_secure=false`. El servidor integrado se inicia desde el proyecto:

```text
php -S 127.0.0.1:8080 -t public
```

La producción no permite desactivar cookies Secure. El navegador recibe solo JSON/HTML, sin credenciales de MySQL.

## API de esta fase

Entrada HTTP: `/api/index.php?route=...` en ambos modos de despliegue (las operaciones no requieren rewrite). En el despliegue desde la raíz, `.htaccess` se usa para bloquear archivos privados.

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
| GET / POST | `facility/recipes` | Operador/admin; listar / crear receta de facility |
| GET / PATCH / DELETE | `facility/recipe&id=ID` | Operador/admin; consultar / editar materiales / archivar receta |
| POST / GET | `facility/recipe/restore&id=ID` / `facility/recipe/history&id=ID` | Operador/admin; restaurar / auditar receta |
| GET / POST | `facility/catalogue` / `facility/catalogue/import` | Administrador; revisar / cargar snapshot inicial confirmado |
| PATCH / DELETE | `installation&id=ID` | Operador/admin; editar / archivar |
| POST | `installation/restore&id=ID` | Operador/admin; restaurar |
| PUT / DELETE | `requirement&installation_id=ID&commodity_id=ID` | Operador/admin; guardar/restaurar / retirar objetivo |
| GET | `planning/history&type=TYPE&id=ID` | Operador/admin; historial de system/installation/requirement; objetivo requiere `commodity_id` |
| POST | `commodities` | Administrador; alta de material |
| GET / POST | `admin/users` | Administrador; listar / crear cuenta |
| GET / PATCH / DELETE | `admin/user&id=ID` | Administrador; consultar / editar / eliminar reversiblemente |
| POST | `admin/user/password&id=ID`, `admin/user/restore&id=ID` | Administrador; contraseña / restauración |
| GET | `admin/roles` | Administrador; roles incorporados |
| PATCH | `installation/status&id=ID` | Operador/admin; estado manual de construcción |
| GET | `wishlist&scope=SCOPE` | Autenticado; scope personal o fleet, nunca otra cuenta |
| GET | `wishlist/destinations&scope=SCOPE&commodity_id=ID` | Autenticado; destinos paginados |
| POST / DELETE | `wishlist/entries&installation_id=ID` / `wishlist/entry&installation_id=ID` | Autenticado; añadir / quitar; scope en JSON |
| GET / POST | `sources&commodity_id=ID` / `sources` | Autenticado; consultar / agregar abastecimiento |
| PATCH / DELETE | `source&id=ID` | Autenticado; editar / retirar lugar |
| POST | `source/restore&id=ID` | Autenticado; restaurar lugar |
| POST | `deliveries&installation_id=ID&commodity_id=ID` | Autenticado; Comandante propio, operador/admin terceros |
| POST | `movement/withdraw&id=ID` | Autenticado; retiro compensatorio propio o autorizado |
| GET | `movements&installation_id=ID`, `logistics/options` | Autenticado; historial / identidades activas |
| GET | `operation&scope=...&key=...` | Autenticado; recibo exclusivo del actor, sin contraseñas |

IDs y cantidades se devuelven como cadenas decimales para conservar precisión. Las entregas guardan `commander_id` y `created_by_user_id` separadamente. `quantity` es una cadena entera positiva hasta el máximo BIGINT firmado; una corrección genera su delta negativo. `construction_status` admite `under_construction` o `completed`; completar requiere motivo y confirmación, incluso con faltantes. Listas/abastecimiento requieren login, mientras el progreso permanece público. El rol técnico `viewer` se presenta como Comandante y dispone de logística propia.

Las escrituras de planificación requieren `X-CSRF-Token` e `Idempotency-Key` (16–100 caracteres ASCII alfanuméricos, `_` o `-`). Editar/retirar/restaurar requiere la `version` recibida al consultar y un `reason`. Un objetivo nuevo usa versión `"0"` y `required_quantity` como cadena decimal; las bajas requieren `confirmed=true`. Repetir exactamente la operación con la misma clave devuelve el resultado guardado. Los listados admiten `list_page`; sistemas e instalaciones archivadas requieren operador/admin e `include_archived=1`.

## Pruebas y despliegue

Las pruebas están en `tests/`: fixtures protegidos para una base `afec_test_*` en loopback y entorno `test`, verificaciones SQL y recorrido HTTP. Python es únicamente un ejecutor de pruebas; no forma parte del backend ni del despliegue.

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST
```

Para probar la raíz completa con Apache instalado, reglas `.htaccess` reales y PHP FastCGI:

```text
python tests/run.py --php RUTA_PHP --config RUTA_CONFIG_PRIVADA_TEST --webroot repository --apache-root RUTA_APACHE
```

El servidor integrado de PHP ignora `.htaccess`: usarlo únicamente con `-t public`, nunca exponiendo el repositorio completo.

Simular/aplicar estructura con `php tools/migrate.php` / `php tools/migrate.php --apply`. En producción se exige además `--allow-production` y un backup revisado. El migrador detecta versiones modificadas o incompletas; no intenta revertir automáticamente DDL.

La guía de preparación del servidor y rollback está en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md). No conectar despliegues automáticos a producción antes de validar layout, configuración privada, esquema y respaldos.
