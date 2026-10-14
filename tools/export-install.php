<?php
declare(strict_types=1);

// Offline packaging tool. Never connect it to an existing production database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use Afec\Services\RecipeCatalogue;

function installLiteral(?string $value): string
{
    return $value === null ? 'NULL' : "'" . str_replace("'", "''", $value) . "'";
}

function installStatement(string $sql): string
{
    return 'SET @afec_install_sql = IF(@afec_install_allowed = 1, ' . installLiteral($sql) . ", 'DO 0');\n"
        . "PREPARE afec_install_statement FROM @afec_install_sql;\nEXECUTE afec_install_statement;\nDEALLOCATE PREPARE afec_install_statement;\n\n";
}

$app = null;
try {
    $options = getopt('', ['output:']);
    $root = dirname(__DIR__);
    $app = require $root . '/src/bootstrap.php';
    $configuration = $app->config;
    if ($configuration['app']['environment'] !== 'test'
        || !in_array($configuration['database']['host'], ['localhost', '127.0.0.1'], true)
        || !str_starts_with($configuration['database']['name'], 'afec_test_')) {
        throw new RuntimeException('Exportar solo una instalación limpia en una base afec_test_* local y entorno test.');
    }
    $output = $options['output'] ?? '';
    if (!is_string($output) || $output === '' || !is_dir(dirname($output)) || file_exists($output)) {
        throw new RuntimeException('Indicar --output=ARCHIVO_NUEVO dentro de .local; crear previamente su carpeta privada.');
    }
    $privateDirectory = realpath($root . '/.local');
    $outputDirectory = realpath(dirname($output));
    if ($privateDirectory === false || $outputDirectory === false
        || !str_starts_with(strtolower($outputDirectory . DIRECTORY_SEPARATOR), strtolower($privateDirectory . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('El paquete con el acceso inicial debe permanecer en .local, fuera de Git.');
    }
    $catalogue = new RecipeCatalogue($root . '/resources/catalogue');
    $database = $app->database->connection();
    $database->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $database->beginTransaction();
    $emptyTables = ['commanders', 'carriers', 'systems', 'installations', 'requirements', 'movements',
        'requirement_revisions', 'import_runs', 'import_items', 'login_attempts', 'wishlist_entries', 'commodity_sources'];
    foreach ($emptyTables as $table) {
        if ((int)$database->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== 0) {
            throw new RuntimeException('La fuente contiene datos de negocio o de prueba; exportación cancelada.');
        }
    }
    $user = $database->query('SELECT username, role, active, deleted_at, commander_id FROM users')->fetchAll();
    if (count($user) !== 1 || $user[0]['role'] !== 'admin' || !(bool)$user[0]['active']
        || $user[0]['deleted_at'] !== null || $user[0]['commander_id'] !== null) {
        throw new RuntimeException('Se requiere exclusivamente el administrador inicial de la nueva instalación.');
    }
    $counts = ['users' => 1, 'commodities' => count($catalogue->data['commodities']),
        'facility_recipes' => count($catalogue->data['recipes']), 'facility_recipe_materials' => array_sum(array_map(
            static fn(array $recipe): int => count($recipe['materials']), $catalogue->data['recipes'])),
        'operations' => 1, 'audit_log' => 1 + count($catalogue->data['commodities']) + count($catalogue->data['recipes'])];
    foreach ($counts as $table => $count) {
        if ((int)$database->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() !== $count) {
            throw new RuntimeException('La instalación fuente no coincide con el catálogo inicial.');
        }
    }
    $query = $database->prepare('SELECT COUNT(*) FROM facility_recipes WHERE source_snapshot_sha256 = ? AND version = 1 AND archived_at IS NULL');
    $query->execute([$catalogue->hash]);
    if ((int)$query->fetchColumn() !== $counts['facility_recipes']) {
        throw new RuntimeException('La fuente contiene ajustes o un catálogo distinto.');
    }
    $files = glob($root . '/database/migrations/*.sql'); sort($files);
    $registry = $database->query('SELECT version, checksum, status FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    if (count($registry) !== count($files)) { throw new RuntimeException('Registro de migraciones inesperado.'); }
    foreach ($files as $file) {
        $entry = $registry[basename($file)] ?? null;
        if (!$entry || $entry['status'] !== 'applied' || !hash_equals(hash_file('sha256', $file), $entry['checksum'])) {
            throw new RuntimeException('Migración incompleta o modificada; no exportar.');
        }
    }

    $sql = "-- AFEC Logistica: instalacion inicial para phpMyAdmin.\n"
        . "-- Seleccionar la base VACIA del sitio antes de importar este archivo completo.\n"
        . "-- Incluye un administrador exclusivo; el acceso se entrega por separado.\n"
        . "-- Sin DROP TABLE, TRUNCATE, proyectos, entregas ni cuentas de la demo.\n"
        . "-- No reanudar si ocurre un error: DDL no es transaccional. Revisar el estado primero.\n"
        . '-- Catalogo SHA256: ' . $catalogue->hash . "\n\n"
        . "SET @afec_previous_sql_mode = @@SESSION.sql_mode;\n"
        . "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,NO_BACKSLASH_ESCAPES';\n"
        . "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;\n"
        . "SET SESSION time_zone = '+00:00';\n"
        . "SET @afec_install_major = CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED);\n"
        . "SET @afec_install_minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED);\n"
        . "SET @afec_install_patch = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 3), '.', -1) AS UNSIGNED);\n"
        . "SET @afec_install_version_ok = IF(LOCATE('MariaDB', VERSION()) > 0,\n"
        . "    (@afec_install_major > 10 OR (@afec_install_major = 10 AND @afec_install_minor >= 11)),\n"
        . "    (@afec_install_major > 8 OR (@afec_install_major = 8 AND (@afec_install_minor > 0 OR @afec_install_patch >= 16))));\n"
        . "SET @afec_install_lock_name = CONCAT('afec-migrate-', LEFT(SHA2(DATABASE(), 256), 48));\n"
        . "SET @afec_install_lock = GET_LOCK(@afec_install_lock_name, 0);\n"
        . "SET @afec_install_allowed = (DATABASE() IS NOT NULL AND @afec_install_version_ok = 1\n"
        . "    AND @afec_install_lock = 1 AND (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()) = 0);\n\n";
    foreach ([$root . '/database/migration_registry.sql', ...$files] as $file) {
        $sql .= '-- ' . basename($file) . "\n";
        // The same restricted plain SQL format as tools/migrate.php.
        $source = preg_replace('/^\s*--.*$/m', '', file_get_contents($file));
        foreach (explode(';', $source) as $statement) {
            if (trim($statement) !== '') { $sql .= installStatement(trim($statement)); }
        }
    }
    $sql .= "START TRANSACTION;\n\n";
    foreach (array_keys($counts) as $table) {
        $columns = $database->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
        $names = array_column($columns, 'Field');
        $types = array_column($columns, 'Type', 'Field');
        $rows = $database->query('SELECT * FROM `' . $table . '` ORDER BY ' . implode(', ', array_map(
            static fn(array $column): string => '`' . $column['Field'] . '`', array_values(array_filter($columns,
                static fn(array $column): bool => $column['Key'] === 'PRI')))))->fetchAll();
        foreach (array_chunk($rows, 50) as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $cells = [];
                foreach ($names as $name) {
                    $value = $row[$name];
                    if ($value === null) { $cells[] = 'NULL'; }
                    elseif (preg_match('/^(?:var)?binary|blob/i', $types[$name])) { $cells[] = "X'" . bin2hex((string)$value) . "'"; }
                    else { $cells[] = installLiteral((string)$value); }
                }
                $values[] = '(' . implode(', ', $cells) . ')';
            }
            $sql .= installStatement('INSERT INTO `' . $table . '` (`' . implode('`, `', $names) . "`) VALUES\n" . implode(",\n", $values));
        }
    }
    $migrationValues = array_map(static fn(string $file): string => '(' . installLiteral(basename($file)) . ', '
        . installLiteral(hash_file('sha256', $file)) . ", 'applied', UTC_TIMESTAMP(6))", $files);
    $sql .= installStatement('INSERT INTO schema_migrations (version, checksum, status, applied_at) VALUES ' . implode(', ', $migrationValues));
    $sql .= "COMMIT;\n\n";
    $checks = [];
    foreach ($counts + ['schema_migrations' => count($files), 'systems' => 0, 'movements' => 0] as $table => $count) {
        $checks[] = '(SELECT COUNT(*) FROM `' . $table . '`) = ' . $count;
    }
    $report = "SELECT CASE WHEN " . implode(' AND ', $checks)
        . " THEN 'AFEC: INSTALACION COMPLETADA' ELSE 'AFEC: INSTALACION INCOMPLETA; REVISAR ERRORES' END AS resultado";
    $sql .= 'SET @afec_install_sql = IF(@afec_install_allowed = 1, ' . installLiteral($report)
        . ", 'SELECT ''AFEC: IMPORTACION OMITIDA; BASE NO VACIA, VERSION NO COMPATIBLE O BLOQUEO ACTIVO'' AS resultado');\n"
        . "PREPARE afec_install_statement FROM @afec_install_sql;\nEXECUTE afec_install_statement;\nDEALLOCATE PREPARE afec_install_statement;\n"
        . "DO RELEASE_LOCK(@afec_install_lock_name);\nSET SESSION sql_mode = @afec_previous_sql_mode;\n";
    $database->commit();
    if (file_put_contents($output, $sql, LOCK_EX) === false) { throw new RuntimeException('No se pudo escribir el paquete privado.'); }
    @chmod($output, 0600);
    echo json_encode(['file' => $output, 'sha256' => hash_file('sha256', $output), 'bytes' => filesize($output),
        'admin_username' => $user[0]['username'], 'counts' => $counts, 'migrations' => count($files)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $error) {
    if (isset($database) && $database->inTransaction()) { $database->rollBack(); }
    fwrite(STDERR, $error instanceof PDOException ? "Error en la fuente local; no se generó un paquete.\n" : $error->getMessage() . PHP_EOL);
    exit(1);
}
