# Decisiones y estado

## Confirmado por el propietario — 14/10/2026

- Autorizado comenzar el desarrollo por fases del monolito PHP/PDO/MySQL, HTML/CSS/JS vanilla.
- Consulta pública sin login de sistemas, instalaciones, materiales y progreso. Toda escritura requiere autenticación y autorización de servidor.
- Operadores autorizados pueden registrar entregas en nombre de otros comandantes.
- Operadores y administradores pueden crear, consultar, editar y archivar/restaurar sistemas e instalaciones, y crear, editar, retirar/restaurar objetivos de materiales. Las bajas conservan registros, revisiones y movimientos.
- En movimientos, `commander_id` identifica a quien entrega y `created_by_user_id` a quien registra. Este último siempre proviene de la sesión; el cliente no puede imponerlo. Fecha, cantidad y pareja instalación/material corresponden al movimiento.
- MySQL en Hostinger usa `localhost:3306`. Los valores privados están en `config/config.local.php`, ignorado por Git. `localhost` se refiere al servidor donde ejecuta PHP; no permite desde el PC alcanzar la base de Hostinger.
- Listas personal (privada a la cuenta) y de flota (compartida por todos los usuarios autenticados). Cada usuario modifica la propia y la flota, nunca la de otros.
- Listas calculadas con faltantes actuales, excluyendo instalaciones terminadas y archivadas. La misma instalación puede estar en ambas listas sin duplicarse dentro de cada una.
- Se permite marcar Terminada manualmente aunque queden faltantes, para compensar desfasajes. Reapertura auditada; no se alteran las entregas reales.
- Se permiten correcciones mediante retiros parciales/completos vinculados al original, sin borrarlo. Se admiten sobreentregas, sin compensar automáticamente otra instalación/material.
- Todos los usuarios autenticados pueden agregar, corregir y retirar lugares de abastecimiento compartidos por mercancía.
- Autorizada la ampliación y el CRUD de usuarios con asignación de roles. Se mantiene administración de cuentas solo para administrador y la prohibición de escrituras públicas.

## Pendiente para las siguientes fases

- Administración completa de comandantes/carriers y catálogo; por ahora, solo el administrador da de alta materiales de catálogo.
- Reservas/cargas en tránsito y reconciliación de saldos negativos históricos. Los retiros nuevos no pueden exceder la entrega original ni crear un saldo negativo.
- Las cargas implementadas son entregas recibidas por la instalación; no se cuentan embarques o mercadería en tránsito como entregada.
- Fórmula global propuesta: cantidad cubierta limitada por objetivo, ponderada por unidades; objetivos cero sin porcentaje; archivadas excluidas del progreso operativo.
- Catálogo verificado de tipos/tiers, identidades/aliases y deduplicación. Los estados formales En construcción/Terminada ya están definidos e implementados.
- Fuentes Apps Script, export completo e historial de Sheets. Terminal AFEC sigue sin fuentes verificables.
- URL y versión real de PHP/MySQL en Hostinger, y verificación de la protección HTTP en la disposición raíz confirmada. Corte e importación requieren aprobación específica.

## Fase 1

Alcance: esquema inicial, configuración, sesiones/CSRF, roles, lectura pública responsive y consulta de cuentas para administrador. No incluye todavía altas/ediciones funcionales, registro de entregas, reversiones, importación ni publicación.

Las restricciones de reversión completa del esquema son provisionales; no hay servicio de reversión habilitado. El modelo puede ajustarse mediante una migración posterior antes del primer uso real. Los datos de prueba son sintéticos y locales.

La fase 3 reemplaza esa restricción provisional mediante `003_logistics_lists.sql`, habilitando compensaciones parciales repetidas y acotadas.

## Fase 2

CRUD de planificación para operadores, archivo/restauración y revisiones auditadas, transacciones, control de versiones, idempotencia y formularios responsive. Listas de sistemas, instalaciones e historial paginadas de 25 en 25. No incluye aún entregas ni importación. Resultado en [FASE_2.md](FASE_2.md).

## Fase 3

CRUD administrativo de cuentas y roles incorporados; estados de construcción; entregas propias/de terceros y retiros compensatorios; listas personal/flota; abastecimiento compartido y uso del logo. Decisiones de implementación: Comandante corrige sus entregas, operador/admin las de terceros; no hay roles personalizados ni registro público. Las bajas son reversibles y conservan referencias. Resultado y límites en [FASE_3.md](FASE_3.md).

## Fase 4 — paleta y facilities

El propietario solicitó los tonos `#0B0E14`, `#C7C7C7`, `#FF7100`, `#CC0000`, bordes CSS animados ligeros, eliminación visible y carga automática editable de materiales por facility. Se implementan plantillas versionadas de construcción para operadores/admin; sus objetivos se copian al crear cada instalación y luego se pueden ajustar independientemente. Cambiar/eliminar una receta no sobrescribe proyectos existentes. La baja de sistemas/instalaciones sigue siendo archivo reversible con historial, ahora presentada como Eliminar.

Pendiente: fuente numérica del catálogo inicial (AFEC/Sheets o referencia comunitaria); el handoff no contiene recetas. No se inventan cantidades. Fuente/variante/fecha se pueden documentar en cada plantilla. Alcance, pruebas y rollback en [FASE_4.md](FASE_4.md).

## Fase 5 — ambas fuentes aprobadas

El propietario respondió «Toma ambos datos» y aportó `AFEC_catalogo_materiales_colonizacion_verificado_parcial.csv`. Se usa ese archivo y lectura acotada de Sheets AFEC, más la planilla original de DaftMav. Se conservaron observaciones AFEC y referencias comunitarias como variantes separadas; no se sobrescriben cantidades entre fuentes. 71 recetas disponibles en la demo, con 55 mercancías, procedencia persistida y carga inicial exclusiva del administrador.

Resuelta la elección de fuente de fase 4. Persisten estructuras sin tipo/tier y cantidades de proyectos específicos que no equivalen a recetas universales. La carga conserva todos los ajustes/recetas ya existentes y no importa ni altera movimientos. Sheets se consultó solo en lectura, sin auditoría de Apps Script ni importación completa. Resultado en [FASE_5.md](FASE_5.md) y [SOURCES.md](../resources/catalogue/SOURCES.md).

## Disposición de despliegue confirmada

El repositorio completo se despliega desde su raíz en Hostinger. La entrada HTML y la API deben funcionar desde allí, con recursos públicos y bloqueo HTTP de archivos privados. Se conserva el modo de desarrollo con document root `public/`. La configuración privada, base inicial y primer administrador siguen siendo pasos de instalación; no se incluyen secretos ni se ejecutan migraciones desde HTTP o por push.


## Ampliación del CRUD de mercancías

El propietario solicitó editar nombres/códigos, desactivar y resolver equivalencias, y autorizó publicar en producción al terminar. Conservamos el permiso Administrador del alta anterior. La equivalencia implementada es alias del mismo ID, sin fusión ni suma de cantidades: no se recibió una regla aprobada para fusionar IDs. Se preservan identidades anteriores salvo corrección explícita; las bajas son reversibles y auditadas.

El propietario confirmó que importará el SQL 007 desde phpMyAdmin. El código es compatible con 006 mientras tanto; no hay migración automática al publicar. Alcance, riesgos y validación en [CRUD_MERCANCIAS.md](CRUD_MERCANCIAS.md).

## Dos roles y gestión operativa compartida — 15/10/2026

El propietario pidió eliminar Operador y confirmó: «Comandante también gestiona todo salvo cuentas y roles». Esta decisión sustituye las restricciones por rol de fases anteriores: Comandante y Administrador gestionan planificación completa, estados, objetivos, recetas, mercancías, carga inicial, abastecimiento, paladas y entregas/correcciones de terceros. Administrador conserva cuentas, roles, restablecimientos de contraseñas ajenas y avisos de ayuda. Todos pueden cambiar su propia contraseña. Se conserva el aislamiento de listas personales, cargas de carrier por cuenta y recibos privados.

Solo `admin` y `viewer` son asignables. `operator` histórico se interpreta como `viewer` en sesión, presentación y revalidación transaccional; no se alteran migraciones, movimientos ni cuentas reales al desplegar. Detalle, pruebas y rollback en [ROLES.md](ROLES.md).
