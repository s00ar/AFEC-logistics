# AFEC Logística

Leer README.md y docs antes de modificar código. El handoff original se conserva en AFEC_Logistica_Handoff_Codex/.

- PHP + PDO MySQL/MariaDB + HTML/CSS/JavaScript vanilla; sin build ni runtime Node en producción.
- Mantener servicios, SQL y presentación separados. Autorización, validación y CSRF en servidor.
- Movimientos append-only; correcciones compensatorias transaccionales e idempotentes. Nunca editar/borrar el original.
- Antes de cada fase: alcance, archivos, pruebas, riesgo y rollback. No declarar completa sin prueba reproducible.
- No introducir credenciales en Git; usar config/config.local.php (ignorado) o AFEC_CONFIG_FILE fuera del webroot.
- No alterar Sheets ni desplegar/ejecutar migraciones en el sitio o base reales sin autorización específica.
- No inventar reglas pendientes ni afirmar que se revisaron fuentes ausentes de Sheets/Terminal AFEC.
- Preservar los archivos originales del handoff y los cambios del usuario.
