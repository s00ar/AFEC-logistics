<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$apply = in_array('--apply', $argv, true);
if ($apply && $app->config['app']['environment'] === 'production' && !in_array('--allow-production', $argv, true)) {
    fwrite(STDERR, "Producción requiere backup revisado y la opción explícita --allow-production.\n"); exit(1);
}
$db = null;
$lock = null;
$exitCode = 0;
try {
    $db = $app->database->connection();
    $version = (string)$db->query('SELECT VERSION()')->fetchColumn();
    preg_match('/^(\d+\.\d+\.\d+)/', $version, $match);
    $minimum = stripos($version, 'MariaDB') !== false ? '10.11.0' : '8.0.16';
    if (!isset($match[1]) || version_compare($match[1], $minimum, '<')) { throw new RuntimeException('Se requiere MySQL 8.0.16+ o MariaDB 10.11+.'); }
    if ($apply) {
        $lock = 'afec-migrate-' . substr(hash('sha256', $app->config['database']['name']),0,48);
        $q = $db->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$lock]);
        if ((int)$q->fetchColumn() !== 1) { $lock=null; throw new RuntimeException('Ya hay una migración en curso.'); }
    }
    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $hasRegistry = in_array('schema_migrations',$tables,true);
    if (!$hasRegistry && $tables !== []) { throw new RuntimeException('La base contiene tablas ajenas; usar una base vacía o revisar el estado.'); }
    $applied = $hasRegistry ? $db->query('SELECT version,checksum,status FROM schema_migrations')->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC) : [];
    $files = glob(dirname(__DIR__).'/database/migrations/*.sql'); sort($files);
    $names = array_map('basename',$files);
    foreach ($applied as $name=>$entry) {
        if (!in_array($name,$names,true) || $entry['status']!=='applied') { throw new RuntimeException('Hay migraciones desconocidas o incompletas; revisar antes de reintentar.'); }
    }
    $pending=[];
    foreach ($files as $file) {
        $name=basename($file);
        if (isset($applied[$name])) {
            if (!hash_equals($applied[$name]['checksum'],hash_file('sha256',$file))) { throw new RuntimeException('Se modificó una migración aplicada: '.$name); }
        } else { $pending[]=$file; }
    }
    if (!$apply) {
        echo "Simulación: no se escribió ningún dato.\n";
        foreach ($pending as $file) { echo 'Pendiente: '.basename($file).PHP_EOL; }
    } else {
        if (!$hasRegistry) { $db->exec(file_get_contents(dirname(__DIR__).'/database/migration_registry.sql')); }
        foreach ($pending as $file) {
            $name=basename($file);
            $q=$db->prepare("INSERT INTO schema_migrations (version,checksum,status) VALUES (?,?,'running')");
            $q->execute([$name,hash_file('sha256',$file)]);
            // Plain SQL only: no routines or semicolons in literals. DDL commits implicitly.
            // An incomplete migration is recorded and must never be blindly replayed.
            $sql=preg_replace('/^\s*--.*$/m','',file_get_contents($file));
            foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') { $db->exec($statement); } }
            $q=$db->prepare("UPDATE schema_migrations SET status='applied',applied_at=UTC_TIMESTAMP(6) WHERE version=?");
            $q->execute([$name]); echo 'Aplicada: '.$name.PHP_EOL;
        }
    }
    if ($pending===[]) { echo "No hay migraciones pendientes.\n"; }
} catch (Throwable $error) {
    $app->logError($error,'migration-'.bin2hex(random_bytes(8)));
    fwrite(STDERR,($error instanceof PDOException ? 'Falló MySQL. Revisar conexión, permisos y migraciones; no se reintentan cambios parciales.' : $error->getMessage()).PHP_EOL);
    $exitCode=1;
} finally {
    if ($db!==null && $lock!==null) { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
}
exit($exitCode);
