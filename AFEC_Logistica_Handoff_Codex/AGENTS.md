# Instrucciones para Codex — AFEC Logística

- Leer README y docs antes de modificar cualquier código.
- Este repositorio inicialmente contiene documentación y un esquema **propuesto**, NO código completo de la herramienta.
- Distinguir hechos del código real de requisitos solicitados y decisiones propuestas. Documentar supuestos.
- No afirmar que el código de Terminal AFEC ha sido revisado hasta obtenerlo y leerlo efectivamente.
- Priorizar PHP 8.2+ (validar versión de Hostinger), PDO/MySQL, HTML/CSS/JS vanilla, sin frameworks obligatorios ni herramientas de compilación para producción.
- Backend responsable de validar, autenticar, autorizar, auditar y persistir; la interfaz nunca usa credenciales SQL.
- No permitir acciones destructivas sin confirmación, auditoría y estrategia de restauración.
- Historial append-only: correcciones por movimientos de ajuste/reversión, nunca por borrado silencioso.
- Múltiples usuarios y sistemas, clave de idempotencia en mutaciones, transacciones SQL.
- Código organizado en módulos pequeños (sin construir una plataforma innecesariamente compleja).
- Antes de cada fase: plan breve, archivos afectados, pruebas, riesgo y rollback.
- No dar por terminadas funcionalidades sin prueba reproducible. Reportar bloqueos concretos.
- Nunca introducir secretos en commits; archivo config.example.php o .env.example sin secretos.
- No alterar la Google Sheet ni el sitio actual sin aprobación explícita.
